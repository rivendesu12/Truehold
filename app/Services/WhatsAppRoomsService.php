<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Rooms agencies only send on WhatsApp (Fausto, Vic, Fab), from the
 * "WhatsApp rooms" tab of Room targets. A Cowork task copies what the groups
 * say into that tab once a day; everything that is a rule lives here:
 * whether a room still counts, its monthly price, deposit and defaults.
 *
 * Columns: A Agency, B Status, C Area, D Street, E Postcode, F Room,
 * G Price, H Per (pcm/pw), I Price for 2, J Room type, K Available from,
 * L Posted, M Last seen, N Photos link, O Bathrooms, P Notes.
 */
class WhatsAppRoomsService
{
    use Concerns\ReadsSupplierSheets;

    protected const CACHE_KEY = 'properties_whatsapp_rooms';

    public static function isConfigured(): bool
    {
        return ! empty(config('services.supplier_targets.spreadsheet_id'))
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
        // A failed read serves the last good copy, never "no rooms".
        if ($rows === null) {
            return collect((array) Cache::get(self::CACHE_KEY . '_last_good', []));
        }

        Cache::put(self::CACHE_KEY, $rows->all(), (int) config('suppliers.whatsapp.cache_timeout', 900));
        Cache::put(self::CACHE_KEY . '_last_good', $rows->all(), now()->addDays(14));

        return $rows;
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    protected function fetch(): ?Collection
    {
        $token = $this->accessToken();
        if (! $token) {
            return null;
        }

        $range = "'" . config('suppliers.whatsapp.tab', 'WhatsApp rooms') . "'!A1:P1000";
        try {
            $response = Http::withToken($token)->timeout(30)->get(
                'https://sheets.googleapis.com/v4/spreadsheets/' . config('services.supplier_targets.spreadsheet_id')
                    . '/values/' . rawurlencode($range)
            );
        } catch (\Throwable $e) {
            Log::warning('WhatsApp rooms read failed', ['error' => $e->getMessage()]);
            return null;
        }
        if (! $response->successful()) {
            Log::warning('WhatsApp rooms tab returned an error', ['status' => $response->status()]);
            return null;
        }

        $rooms = collect(array_slice((array) $response->json('values', []), 1))
            ->map(fn ($row) => $this->mapRow((array) $row))
            ->filter()
            ->values();

        return $this->attachCoordinates($rooms)->values();
    }

    /** One row of the tab as a listing, or null when it does not count. */
    public function mapRow(array $row): ?array
    {
        $get = fn (int $i) => trim((string) ($row[$i] ?? ''));

        $key = strtolower($get(0));
        $agency = config('suppliers.whatsapp.agencies.' . $key);
        if (! $agency) {
            return null;
        }

        // Crossed out, let, taken: gone.
        if (strtolower($get(1)) !== 'available') {
            return null;
        }

        // Not seen in the group for too long: gone too.
        $seen = self::date($get(12)) ?? self::date($get(11));
        if (! $seen || $seen->lt(now()->subDays((int) $agency['fresh_days'])->startOfDay())) {
            return null;
        }

        $postcode = strtoupper(preg_replace('/\s+/', ' ', $get(4)));
        $amount = self::money($get(6));
        if ($postcode === '' || $amount === null) {
            return null;
        }
        $weekly = str_contains(strtolower($get(7)), 'w');
        $price = $weekly ? round($amount * 52 / 12, 2) : $amount;
        $couples = self::money($get(8));
        $couplesPrice = $couples === null ? null : ($weekly ? round($couples * 52 / 12, 2) : $couples);

        $area = ucwords(strtolower($get(2)));
        $street = ucwords(strtolower($get(3)));
        $room = strtoupper($get(5));
        $typeRaw = strtolower($get(9));
        $roomType = match (true) {
            str_contains($typeRaw, 'ensuite') || str_contains($typeRaw, 'en-suite') => 'ensuite',
            str_contains($typeRaw, 'studio') => null,
            str_contains($typeRaw, 'single') && ! str_contains($typeRaw, 'double') => 'single',
            default => 'double',
        };
        $from = self::date($get(10));
        $weeks = (int) $agency['deposit_weeks'];

        $folder = str_contains($get(13), 'drive.google.com') ? $get(13) : null;
        $photoIds = $folder ? app(SupplierPhotoService::class)->photosForRoom($folder, $room) : [];
        $photoUrls = array_map(fn ($id) => route('supplier.photo', ['fileId' => $id], false), $photoIds);

        $title = trim(($street !== '' ? $street : $postcode) . ($area !== '' ? ', ' . $area : '')) . ($room !== '' ? ' — Room ' . $room : '');

        return [
            'id' => 'wa-' . substr(sha1($key . '|' . $postcode . '|' . strtolower($street) . '|' . $room), 0, 20),
            'source' => 'whatsapp_sheet',
            'title' => $title,
            // Seen by clients: no agency, no group.
            'description' => trim(implode(' ', array_filter([
                ucfirst($roomType ?? 'studio') . ' room' . ($area !== '' ? ' in ' . $area : '') . '.',
                $weeks === 0 ? 'No deposit.' : null,
                $couplesPrice ? 'Couples welcome.' : null,
            ]))),
            'price' => $price,
            'property_type' => str_contains($typeRaw, 'studio') ? 'Studio' : 'Room',
            'room1_type' => $roomType,
            'location' => $area !== '' ? $area : $postcode,
            'postcode' => $postcode,
            'latitude' => null,
            'longitude' => null,
            'link' => null,
            'url' => null,
            'agent_name' => $agency['name'],
            'landlord_name' => $agency['name'],
            'status' => 'available',
            'available_date' => ($from && $from->isFuture() ? $from : now())->toDateString(),
            'deposit' => $weeks === 0 ? 0 : round($price * 12 / 52 * $weeks),
            'couples_ok' => $couplesPrice ? 'Yes' : null,
            'couples_price' => $couplesPrice,
            'total_rooms' => (int) config('suppliers.whatsapp.default_total_rooms', 4),
            'bathrooms' => is_numeric($get(14)) ? (float) $get(14) : (int) config('suppliers.whatsapp.default_bathrooms', 1),
            'room_count' => 1,
            'source_property' => $street !== '' ? $street : $postcode,
            'source_room' => $room !== '' ? $room : null,
            // For agents only (Sigou card): where the photos are.
            'agent_note' => $photoUrls ? null : 'Photos in the ' . $agency['group'] . ' WhatsApp group',
            'first_photo_url' => $photoUrls[0] ?? null,
            'photos' => $photoUrls ?: null,
            'all_photos' => $photoUrls ? implode(', ', $photoUrls) : null,
            'photo_count' => count($photoUrls),
            'drive_folder_url' => $folder,
            'last_seen' => $seen->toDateString(),
            'updatable' => false,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /** "24/09/2026", "24/09/26", "2026-09-24", "24 Sep". */
    public static function date(string $value): ?Carbon
    {
        $value = trim($value);
        if ($value === '' || preg_match('/^now$/i', $value)) {
            return null;
        }
        try {
            if (preg_match('#^(\d{1,2})/(\d{1,2})(?:/(\d{2,4}))?$#', $value, $m)) {
                $year = ! empty($m[3]) ? ((int) $m[3] < 100 ? 2000 + (int) $m[3] : (int) $m[3]) : now()->year;
                return Carbon::create($year, (int) $m[2], (int) $m[1])->startOfDay();
            }

            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function money(string $value): ?float
    {
        $clean = preg_replace('/[^0-9.]/', '', str_replace(',', '', $value));

        return $clean !== '' && is_numeric($clean) && (float) $clean > 50 ? (float) $clean : null;
    }
}
