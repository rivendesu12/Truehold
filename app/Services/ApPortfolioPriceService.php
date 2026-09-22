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
