<?php

namespace App\Services;

use App\Support\XlsxReader;
use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDrive;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Prices from the AP / Horizon portfolio workbook.
 *
 * The Targets tab is a derived summary and its Price column is sometimes blank
 * (Barnando Gardens Room C, for one) while the source workbook has the figure.
 * The feed's own spreadsheet-sourced rows are missing prices too. This reads
 * the authoritative workbook and exposes a lookup so those gaps can be filled.
 *
 * The file is a real .xlsx in Drive, not a Google Sheet, so the Sheets API
 * returns 400 on it — it has to be downloaded and parsed.
 */
class ApPortfolioPriceService
{
    private const SHEET = 'Property Portfolio';

    // 1-based columns in that tab.
    private const COL_PROPERTY = 3;
    private const COL_ROOM = 4;
    private const COL_POSTCODE = 6;
    private const COL_PRICE = 9;
    // Tenant columns. Only whether a room has a tenant, and that tenant's
    // age, are ever taken from them; names and phones are never read out.
    private const COL_TENANT = 10;
    private const COL_DOB = 12;

    public static function isConfigured(): bool
    {
        return ! empty(config('services.ap_portfolio.file_id'))
            && ! empty(config('services.supplier_targets.credentials_path'));
    }

    /**
     * Lookup keyed by "property|room" and also "property" alone, so a row with
     * an unmatched room code can still fall back to the property's own pricing.
     *
     * @return array<string, float>
     */
    public function prices(): array
    {
        if (! self::isConfigured()) {
            return [];
        }

        return Cache::remember('ap_portfolio_prices', now()->addHours(6), function () {
            try {
                return $this->build();
            } catch (\Throwable $e) {
                Log::warning('AP portfolio price load failed', ['error' => $e->getMessage()]);
                return [];
            }
        });
    }

    protected function build(): array
    {
        $path = $this->download();
        if (! $path) {
            return [];
        }

        $rows = XlsxReader::rows($path, self::SHEET);
        $out = [];
        $byProperty = [];

        foreach ($rows as $row) {
            $property = $this->norm((string) ($row[self::COL_PROPERTY] ?? ''));
            $price = $this->parsePrice((string) ($row[self::COL_PRICE] ?? ''));

            if ($property === '' || $price === null) {
                continue;
            }

            $room = $this->norm((string) ($row[self::COL_ROOM] ?? ''));
            if ($room !== '') {
                $out[$property . '|' . $room] = $price;
            }

            $postcode = $this->norm((string) ($row[self::COL_POSTCODE] ?? ''));
            if ($postcode !== '' && $room !== '') {
                $out['pc:' . $postcode . '|' . $room] = $price;
            }

            $byProperty[$property][] = $price;
        }

        // Property-level fallback: the cheapest room, so we never overstate.
        foreach ($byProperty as $property => $prices) {
            $out[$property] = min($prices);
        }

        return $out;
    }

    /**
     * Who lives in each property: per property, the let rooms and the age of
     * each tenant. Nothing that identifies anyone.
     *
     * @return array<string, array<int, array{room:string, age:?int}>>
     */
    public function households(): array
    {
        if (! self::isConfigured()) {
            return [];
        }

        return Cache::remember('ap_portfolio_households', now()->addHours(6), function () {
            try {
                $path = $this->download();
                if (! $path) {
                    return [];
                }
                $out = [];
                foreach (XlsxReader::rows($path, self::SHEET) as $row) {
                    $property = $this->norm((string) ($row[self::COL_PROPERTY] ?? ''));
                    if ($property === '' || trim((string) ($row[self::COL_TENANT] ?? '')) === '') {
                        continue;
                    }
                    // The header row of the Horizon section has "Full name" there.
                    if (str_contains(strtolower((string) ($row[self::COL_TENANT] ?? '')), 'name')) {
                        continue;
                    }
                    $out[$property][] = [
                        'room' => $this->norm((string) ($row[self::COL_ROOM] ?? '')),
                        'age' => self::age($row[self::COL_DOB] ?? null),
                    ];
                }
                return $out;
            } catch (\Throwable $e) {
                Log::warning('AP portfolio household load failed', ['error' => $e->getMessage()]);
                return [];
            }
        });
    }

    /**
     * The flatmates a room would have: tenants in the property's other rooms.
     *
     * @return array{count:int, ages:?string, source:string}|null
     */
    public function householdFor(?string $property, ?string $room): ?array
    {
        $p = $this->norm((string) $property);
        $r = $this->norm((string) $room);
        if ($p === '') {
            return null;
        }

        $all = $this->households();
        $tenants = $all[$p] ?? null;
        if ($tenants === null && strlen($p) >= 6) {
            foreach ($all as $key => $list) {
                if (str_contains($key, $p) || str_contains($p, $key)) {
                    $tenants = $list;
                    break;
                }
            }
        }
        if (! $tenants) {
            return null;
        }

        $others = array_values(array_filter($tenants, fn ($t) => $t['room'] !== $r));
        $ages = array_values(array_filter(array_column($others, 'age')));
        sort($ages);

        return [
            'count' => count($others),
            'ages' => $ages ? ($ages[0] === end($ages) ? (string) $ages[0] : $ages[0] . ' to ' . end($ages)) : null,
            'source' => 'agency sheet',
        ];
    }

    /** Age in years from a date of birth: an Excel serial or dd/mm/yyyy. */
    public static function age(mixed $dob): ?int
    {
        $dob = trim((string) $dob);
        try {
            if (is_numeric($dob) && (float) $dob > 1000) {
                $date = \Carbon\Carbon::createFromTimestamp(((float) $dob - 25569) * 86400);
            } elseif (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $dob, $m)) {
                $date = \Carbon\Carbon::create((int) $m[3], (int) $m[2], (int) $m[1]);
            } else {
                return null;
            }
        } catch (\Throwable $e) {
            return null;
        }
        $age = $date->diffInYears(now());

        return $age >= 16 && $age <= 90 ? (int) $age : null;
    }

    /** Resolve a price for a listing, or null. */
    public function priceFor(array $lookup, ?string $property, ?string $room, ?string $postcode): ?float
    {
        $p = $this->norm((string) $property);
        $r = $this->norm((string) $room);
        $pc = $this->norm((string) $postcode);

        foreach ([
            $p !== '' && $r !== '' ? $p . '|' . $r : null,
            $pc !== '' && $r !== '' ? 'pc:' . $pc . '|' . $r : null,
            $p !== '' ? $p : null,
        ] as $key) {
            if ($key !== null && isset($lookup[$key])) {
                return $lookup[$key];
            }
        }

        // Last resort: a property name in the sheet that contains ours, or vice
        // versa ("Barnando Gardens" vs "Barnando Gardens, Wapping").
        if ($p !== '' && strlen($p) >= 6) {
            foreach ($lookup as $key => $value) {
                if (str_contains($key, '|') || str_starts_with($key, 'pc:')) {
                    continue;
                }
                if (str_contains($key, $p) || str_contains($p, $key)) {
                    return $value;
                }
            }
        }

        return null;
    }

    protected function download(): ?string
    {
        $dir = storage_path('app/ap-portfolio');
        $path = $dir . '/portfolio.xlsx';

        if (is_file($path) && filemtime($path) > time() - 21600) {
            return $path;
        }

        $credentials = config('services.supplier_targets.credentials_path');
        $fileId = config('services.ap_portfolio.file_id');

        try {
            $client = new GoogleClient();
            $client->setScopes([GoogleDrive::DRIVE_READONLY]);
            $client->setAuthConfig($credentials);
            $drive = new GoogleDrive($client);

            $response = $drive->files->get($fileId, ['alt' => 'media', 'supportsAllDrives' => true]);
            $body = (string) $response->getBody();

            if ($body === '') {
                return is_file($path) ? $path : null;
            }

            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            @file_put_contents($path, $body);

            return $path;
        } catch (\Throwable $e) {
            Log::warning('AP portfolio download failed', ['error' => $e->getMessage()]);
            return is_file($path) ? $path : null;
        }
    }

    /** Handles both "£1,025" and "850.0". */
    protected function parsePrice(string $value): ?float
    {
        $clean = preg_replace('/[^0-9.]/', '', $value);
        if ($clean === '' || ! is_numeric($clean)) {
            return null;
        }
        $price = (float) $clean;
        return $price > 0 ? $price : null;
    }

    protected function norm(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? '';
        return trim($value, " \t\n\r\0\x0B.,");
    }
}
