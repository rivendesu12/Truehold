<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Javier's rooms (he trades as JMS and FENIX), from his own "JAVIER VACANCY"
 * sheet, ROOMS tab. Replaces the copy Ali's scraper used to make of it.
 *
 * Giaco's rule for what is available: status MOVE OUT or APT BREAK. MOVE
 * OUT/HOLD is held for someone, and a blank status is not a vacancy.
 *
 * Columns B-N are the letting facts and R-S map each property to its photo
 * folder. O, P and T hold the tenant's name, phone and WhatsApp and are
 * never requested.
 */
class JavierSheetService
{
    use Concerns\ReadsSupplierSheets;

    protected const CACHE_KEY = 'properties_javier_sheet';

    public static function isConfigured(): bool
    {
        return ! empty(config('suppliers.javier.spreadsheet_id'))
            && ! empty(config('services.supplier_targets.credentials_path'));
    }

    public function getAllProperties(): Collection
    {
        if (! self::isConfigured()) {
            return collect();
        }

        $cached = Cache::get(self::CACHE_KEY);
        if ($cached !== null) {
            return collect($cached);
        }

        $rows = $this->fetch();

        // Empty is a failure or a broken sheet, never "Javier has nothing":
        // serve the last good copy rather than drop his rooms.
        if ($rows === null) {
            return collect((array) Cache::get(self::CACHE_KEY . '_last_good', []));
        }

        Cache::put(self::CACHE_KEY, $rows->all(), (int) config('suppliers.javier.cache_timeout', 900));
        if ($rows->isNotEmpty()) {
            Cache::put(self::CACHE_KEY . '_last_good', $rows->all(), now()->addDays(14));
        }

        return $rows;
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** Null when the read failed, so a failure is never cached as "no rooms". */
    protected function fetch(): ?Collection
    {
        $token = $this->accessToken();
        if (! $token) {
            return null;
        }

        $id = config('suppliers.javier.spreadsheet_id');
        $tab = "'" . config('suppliers.javier.tab', 'ROOMS') . "'";

        try {
            // Google wants ranges=..&ranges=.., not PHP's ranges[0]=.. form.
            $query = 'ranges=' . rawurlencode($tab . '!B1:N600') . '&ranges=' . rawurlencode($tab . '!R1:S600');
            $response = Http::withToken($token)->timeout(30)->get(
                'https://sheets.googleapis.com/v4/spreadsheets/' . $id . '/values:batchGet?' . $query
            );
        } catch (\Throwable $e) {
            Log::warning('Javier sheet fetch failed', ['error' => $e->getMessage()]);
            return null;
        }

        if (! $response->successful()) {
            Log::warning('Javier sheet returned an error', ['status' => $response->status()]);
            return null;
        }

        $rows = (array) $response->json('valueRanges.0.values', []);
        $folders = [];
        foreach ((array) $response->json('valueRanges.1.values', []) as $f) {
            $name = self::norm((string) ($f[0] ?? ''));
            $url = trim((string) ($f[1] ?? ''));
            if ($name !== '' && str_starts_with($url, 'http')) {
                $folders[$name] = $url;
            }
        }

        if (count($rows) < 2) {
            return null;
        }

        $out = collect();
        foreach (array_slice($rows, 1) as $row) {
            if ($room = $this->mapRow($row, $folders)) {
                $out->push($room);
            }
        }

        return $this->attachCoordinates($out)->values();
    }

    /** B Company, C Status, D Date available, E Confirmed?, G Tube, H Property, I Postcode, J Type, K Room #, L 1P, M 2P, N Bills. */
    public function mapRow(array $row, array $folders = []): ?array
    {
        $get = fn (int $i) => trim((string) ($row[$i] ?? ''));

        $company = strtoupper($get(0));
        $agent = config('suppliers.sheet_companies.' . strtolower($company));
        if (! $agent || ! str_starts_with($agent, 'Javier')) {
            return null; // month headings, blank rows, other companies
        }

        if (! self::isAvailable($get(1))) {
            return null;
        }

        $property = $get(6);
        $postcode = strtoupper($get(7));
        $price = self::money($get(10));
        if ($property === '' || $price === null) {
            return null;
        }

        $from = self::date($get(2));
        $window = (int) config('services.supplier_targets.window_days', 62);
        if ($from && Carbon::parse($from)->gt(now()->addDays($window))) {
            return null;
        }

        $roomNo = $get(9);
        $typeRaw = strtolower($get(8));
        $roomType = match (true) {
            str_contains($typeRaw, 'ensuite') || str_contains($typeRaw, 'en-suite') => 'ensuite',
            str_contains($typeRaw, 'single') => 'single',
            str_contains($typeRaw, 'double') => 'double',
            default => null,
        };
        $couplesPrice = self::money($get(11));
        $bills = $get(12);
        $tube = $get(5);

        $folder = $folders[self::norm($property)] ?? null;
        if (! $folder) {
            foreach ($folders as $name => $url) {
                if (str_starts_with(self::norm($property), $name) || str_starts_with($name, self::norm($property))) {
                    $folder = $url;
                    break;
                }
            }
        }
        $photoIds = $folder ? app(SupplierPhotoService::class)->photosForRoom($folder, $roomNo) : [];
        $photoUrls = array_map(fn ($id) => route('supplier.photo', ['fileId' => $id], false), $photoIds);

        $confirmed = $get(3);
        $title = $property . ($roomNo !== '' ? ' — Room ' . $roomNo : '');

        return [
            'id' => 'javier-' . sha1(strtolower($company . '|' . $property . '|' . $roomNo)),
            'source' => 'javier_sheet',
            'title' => $title,
            // Not the agency: the description reaches clients.
            'description' => trim(implode(' ', array_filter([
                $roomType ? ucfirst($roomType) . ' room' : 'Room',
                'at ' . $property . ',',
                $postcode !== '' ? $postcode . '.' : null,
                $tube !== '' ? 'Near ' . $tube . '.' : null,
                $bills !== '' ? 'Bills: ' . $bills . '.' : null,
            ]))),
            'price' => $price,
            'property_type' => str_contains($typeRaw, 'studio') ? 'Studio' : 'Room',
            'room1_type' => $roomType ?? (str_contains($typeRaw, 'studio') ? null : 'double'),
            'location' => $tube !== '' ? trim(explode('/', $tube)[0]) : 'London',
            'postcode' => $postcode !== '' ? $postcode : null,
            'latitude' => null,
            'longitude' => null,
            'link' => null,
            'url' => null,
            'agent_name' => $agent,
            'landlord_name' => $agent,
            'paying' => 'yes',
            'status' => 'available',
            'available_date' => $from ?? now()->toDateString(),
            'availability_note' => stripos($confirmed, 'not confirmed') !== false ? 'Move-out not confirmed yet' : null,
            'room_count' => 1,
            'couples_ok' => $couplesPrice ? 'Yes' : null,
            'couples_price' => $couplesPrice,
            'bills_included' => stripos($bills, 'all included') !== false ? 'Yes' : ($bills !== '' ? 'No' : null),
            'bills_note' => $bills !== '' ? $bills : null,
            'source_property' => $property,
            'source_room' => $roomNo !== '' ? $roomNo : null,
            'first_photo_url' => $photoUrls[0] ?? null,
            'photos' => $photoUrls ?: null,
            'all_photos' => $photoUrls ? implode(', ', $photoUrls) : null,
            'photo_count' => count($photoUrls),
            'drive_folder_url' => $folder,
            'updatable' => false,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /** MOVE OUT or APT BREAK; never anything on hold, never blank. */
    public static function isAvailable(string $status): bool
    {
        $s = strtoupper(preg_replace('/\s+/', ' ', trim($status)));
        if ($s === '' || str_contains($s, 'HOLD')) {
            return false;
        }

        return $s === 'MOVE OUT' || str_starts_with($s, 'APT BREAK');
    }

    /** "Tue 01/10/26" or "01/10/2026". */
    public static function date(string $value): ?string
    {
        if (! preg_match('#(\d{1,2})/(\d{1,2})/(\d{2,4})#', $value, $m)) {
            return null;
        }
        $year = (int) $m[3] < 100 ? 2000 + (int) $m[3] : (int) $m[3];
        try {
            $date = Carbon::create($year, (int) $m[2], (int) $m[1]);
        } catch (\Throwable $e) {
            return null;
        }

        return $date->lt(now()->startOfDay()) ? now()->toDateString() : $date->toDateString();
    }

    public static function money(string $value): ?float
    {
        $clean = preg_replace('/[^0-9.]/', '', $value);

        return $clean !== '' && is_numeric($clean) && (float) $clean > 100 ? (float) $clean : null;
    }

    protected static function norm(string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', strtolower($s)));
    }
}
