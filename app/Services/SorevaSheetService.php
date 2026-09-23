<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Soreva Living's rooms, from their own Google Sheet.
 *
 * Read from the "Letting agent List" tab, which is the one they keep for
 * letting agents. Columns A–K are the letting facts; columns L onwards hold
 * tenant names, phone numbers, dates of birth and email addresses, so the
 * range read here stops at K deliberately. None of that belongs on a public
 * property site and none of it is fetched.
 *
 * The Property column holds =HYPERLINK() formulas pointing at each property's
 * photo folder, so the sheet is read as formulas rather than display values —
 * read as values, the links are invisible and every room looks photoless.
 *
 * Their status column is its own vocabulary — AVAILABLE, AVAILABLE 01/09/2025,
 * BOOKED, ON HOLD/RELOCATION, LEASE COMFIRMED — and only a status beginning
 * with AVAILABLE means a room can be let.
 */
class SorevaSheetService
{
    protected const CACHE_KEY = 'properties_soreva_sheet';

    /** Stops before the tenant contact columns. */
    protected const RANGE = 'A1:K1000';

    public static function isConfigured(): bool
    {
        return ! empty(config('suppliers.soreva.spreadsheet_id'))
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

        // An empty read is either a genuine empty sheet or a failure we have
        // already logged; either way the last good copy beats a blank site.
        if ($rows->isEmpty()) {
            $previous = $this->lastGood();

            if ($previous) {
                Log::warning('Soreva sheet came back empty; serving the last good copy', [
                    'rooms' => count($previous),
                ]);

                return collect($previous);
            }
        }

        Cache::put(self::CACHE_KEY, $rows->all(), (int) config('suppliers.soreva.cache_timeout', 900));
        $this->rememberLastGood($rows);

        return $rows;
    }


    /**
     * The last result this source returned successfully, kept well beyond the
     * working cache so a bad afternoon cannot empty the site.
     *
     * @return array<int, array>
     */
    protected function lastGood(): array
    {
        return (array) Cache::get(self::CACHE_KEY . '_last_good', []);
    }

    protected function rememberLastGood(Collection $rows): void
    {
        if ($rows->isNotEmpty()) {
            Cache::put(self::CACHE_KEY . '_last_good', $rows->all(), now()->addDays(14));
        }
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    protected function fetch(): Collection
    {
        $token = $this->accessToken();
        if (! $token) {
            return collect();
        }

        $id = config('suppliers.soreva.spreadsheet_id');
        $tab = config('suppliers.soreva.tab', 'Letting agent List');

        try {
            $response = Http::withToken($token)->timeout(30)->get(
                'https://sheets.googleapis.com/v4/spreadsheets/' . $id
                    . '/values/' . rawurlencode($tab) . '!' . self::RANGE,
                // FORMULA, not FORMATTED_VALUE: the latter returns "Colmer Road"
                // for a =HYPERLINK() cell and throws the folder link away.
                ['valueRenderOption' => 'FORMULA']
            );
        } catch (\Throwable $e) {
            Log::warning('Soreva sheet fetch failed', ['error' => $e->getMessage()]);
            return collect();
        }

        if (! $response->successful()) {
            Log::warning('Soreva sheet returned an error', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 300),
            ]);
            return collect();
        }

        $rows = $response->json('values') ?? [];
        // Titles and a colour key sit above the header row ("Status",
        // "Property", ...); rooms start below it.
        $header = 0;
        foreach ($rows as $i => $row) {
            if (strcasecmp(trim((string) ($row[0] ?? '')), 'status') === 0) {
                $header = $i;
                break;
            }
        }
        $rows = array_slice($rows, $header + 1);

        $properties = collect();

        foreach ($rows as $row) {
            $property = $this->mapRow($row);
            if ($property !== null) {
                $properties->push($property);
            }
        }

        return $this->attachCoordinates($properties);
    }

    /**
     * Columns: Status, Property, Managing Company, Rent, Room Number,
     * Postcode, Area, Couples, LIVING.R, N.Rooms, N.Bathrooms.
     */
    protected function mapRow(array $row): ?array
    {
        // Reading formulas means a linked cell arrives as =HYPERLINK(url, label),
        // so the label is what we want for display and the url for photos.
        $raw = fn (int $i) => trim((string) ($row[$i] ?? ''));
        $get = fn (int $i) => $this->cellLabel($raw($i));

        $status = $get(0);
        $property = $get(1);
        $folderUrl = $this->extractHyperlink($raw(1));
        // The room number links to that room's own folder where they have one.
        $roomFolderUrl = $this->extractHyperlink($raw(4));
        $rent = $get(3);
        $room = $get(4);
        $postcode = strtoupper($get(5));
        $area = $get(6);

        if ($property === '' || $postcode === '') {
            return null;
        }

        if (! $this->isAvailable($status)) {
            return null;
        }

        $price = $this->parsePrice($rent);
        if ($price === null) {
            return null;
        }

        $roomType = $this->roomType($room);
        $rooms = is_numeric($get(9)) ? (int) $get(9) : null;

        // Their property links point at the photo folders, so the same
        // private-Drive proxy the other suppliers use serves these too.
        $photoFolder = $roomFolderUrl ?: $folderUrl;
        $photoIds = $photoFolder
            ? app(SupplierPhotoService::class)->photosForRoom($photoFolder, $room)
            : [];
        $photoUrls = array_map(
            fn ($id) => route('supplier.photo', ['fileId' => $id], false),
            $photoIds
        );

        $title = trim($property . ($room !== '' ? ' — ' . $room : ''));

        return [
            'id' => 'soreva-' . sha1(strtolower($property . '|' . $room . '|' . $postcode)),
            'source' => 'soreva_sheet',
            'title' => $title,
            'description' => trim(implode(' ', array_filter([
                $roomType ? ucfirst($roomType) . ' room' : null,
                'at ' . $property . ',',
                $area !== '' ? ucwords(strtolower($area)) . ',' : null,
                $postcode . '.',
                $rooms ? $rooms . '-bedroom property.' : null,
                // Deliberately not the agency name: the description is shown
                // to clients, and naming the supplier on a link an agent
                // shares hands over the sourcing relationship.
            ]))),
            'price' => $price,
            'property_type' => $this->propertyType($room),
            'room1_type' => $roomType,
            'location' => $area !== '' ? ucwords(strtolower($area)) : 'London',
            'postcode' => $postcode,
            'latitude' => null,
            'longitude' => null,
            'link' => null,
            'url' => null,
            'agent_name' => 'Soreva Living',
            'landlord_name' => 'Soreva Living',
            'paying' => config('suppliers.soreva.pays_commission') ? 'yes' : 'no',
            'status' => 'available',
            'available_date' => $this->availableFrom($status),
            'room_count' => 1,
            'total_rooms' => $rooms,
            'couples_ok' => $get(7) !== '' ? $get(7) : null,
            'source_room' => $room !== '' ? $room : null,
            'first_photo_url' => $photoUrls[0] ?? null,
            'photos' => $photoUrls ?: null,
            'all_photos' => $photoUrls ? implode(', ', $photoUrls) : null,
            'photo_count' => count($photoUrls),
            'drive_folder_url' => $folderUrl,
            'drive_room_folder' => $roomFolderUrl,
            'updatable' => false,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * The text a person sees in the cell: the label of a =HYPERLINK(), or the
     * cell itself when it is plain.
     */
    protected function cellLabel(string $value): string
    {
        if (preg_match('/HYPERLINK\(\s*"[^"]*"\s*,\s*"([^"]*)"/i', $value, $m)) {
            return trim($m[1]);
        }

        // A formula we cannot read is not a value; better blank than "=A1*2".
        return str_starts_with($value, '=') ? '' : $value;
    }

    protected function extractHyperlink(string $value): ?string
    {
        if (preg_match('/HYPERLINK\(\s*"([^"]+)"/i', $value, $m)) {
            return $m[1];
        }

        return str_starts_with($value, 'http') ? $value : null;
    }

    protected function isAvailable(string $status): bool
    {
        $prefix = (string) config('suppliers.soreva.available_prefix', 'available');

        return str_starts_with(strtolower(trim($status)), strtolower($prefix));
    }

    /**
     * "AVAILABLE 01/09/2025", "Available from 04/Oct/2026" and "AVAILABLE NOW
     * - 2 MONTHS" all occur; the date is taken where one is written, and
     * today otherwise.
     */
    protected function availableFrom(string $status): ?string
    {
        if (preg_match('#(\d{1,2})[/ -]([A-Za-z]{3,9})[/ -](\d{2,4})#', $status, $m)) {
            try {
                return \Carbon\Carbon::parse($m[1] . ' ' . $m[2] . ' ' . ((int) $m[3] < 100 ? 2000 + (int) $m[3] : $m[3]))->toDateString();
            } catch (\Throwable $e) {
                // Fall through to the numeric form.
            }
        }

        if (preg_match('#(\d{1,2})/(\d{1,2})/(\d{2,4})#', $status, $m)) {
            $year = (int) $m[3];
            $year += $year < 100 ? 2000 : 0;

            try {
                return \Carbon\Carbon::create($year, (int) $m[2], (int) $m[1])->toDateString();
            } catch (\Throwable $e) {
                // Unparseable; treat as available now.
            }
        }

        return now()->toDateString();
    }

    /** Rent is written as "900", "1,080" or a range "2500-2600". */
    protected function parsePrice(string $rent): ?float
    {
        $rent = str_replace(',', '', $rent);

        if (preg_match('/(\d+(?:\.\d+)?)\s*[-–]\s*(\d+(?:\.\d+)?)/', $rent, $m)) {
            // Quote the lower end: it is the one we can honour.
            return (float) min($m[1], $m[2]);
        }

        return preg_match('/(\d+(?:\.\d+)?)/', $rent, $m) ? (float) $m[1] : null;
    }

    /** Room codes: M1 master, D6 double, ENS2 en-suite, S1 single. */
    protected function roomType(string $room): ?string
    {
        $room = strtoupper(trim($room));

        if ($room === '') {
            return null;
        }
        if (str_contains($room, 'BED FLAT') || str_contains($room, 'STUDIO')) {
            return null;
        }
        if (str_starts_with($room, 'ENS')) {
            return 'ensuite';
        }
        if (str_starts_with($room, 'M')) {
            return 'double';
        }
        if (str_starts_with($room, 'D')) {
            return 'double';
        }
        if (str_starts_with($room, 'S')) {
            return 'single';
        }
        if (str_starts_with($room, 'T')) {
            return 'twin';
        }

        return null;
    }

    protected function propertyType(string $room): string
    {
        $room = strtoupper($room);

        if (str_contains($room, 'STUDIO')) {
            return 'Studio';
        }
        if (str_contains($room, 'BED FLAT') || str_contains($room, 'WHOLE')) {
            return 'Flat';
        }

        return 'Room';
    }

    /** Every row has a full postcode, so one bulk lookup places them all. */
    protected function attachCoordinates(Collection $properties): Collection
    {
        if ($properties->isEmpty()) {
            return $properties;
        }

        $geocoder = app(PostcodeGeocoder::class);
        $coords = $geocoder->lookupMany(
            $properties->pluck('postcode')->filter()->map(fn ($p) => $geocoder->normalise($p))->unique()->all()
        );

        return $properties->map(function (array $property) use ($geocoder, $coords) {
            $key = $geocoder->normalise((string) $property['postcode']);

            if (isset($coords[$key])) {
                $property['latitude'] = $coords[$key]['lat'];
                $property['longitude'] = $coords[$key]['lng'];
                $property['geocoded_from_postcode'] = true;
            }

            return $property;
        });
    }

    /** Signed JWT exchange, same service account as the other supplier sheets. */
    protected function accessToken(): ?string
    {
        $path = config('services.supplier_targets.credentials_path');

        if (! $path || ! is_file($path)) {
            return null;
        }

        return Cache::remember('soreva_sheets_token', 3000, function () use ($path) {
            $creds = json_decode((string) file_get_contents($path), true);

            if (empty($creds['client_email']) || empty($creds['private_key'])) {
                return null;
            }

            $encode = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');

            $unsigned = $encode(['alg' => 'RS256', 'typ' => 'JWT']) . '.' . $encode([
                'iss' => $creds['client_email'],
                'scope' => 'https://www.googleapis.com/auth/spreadsheets.readonly',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => time(),
                'exp' => time() + 3600,
            ]);

            if (! openssl_sign($unsigned, $signature, $creds['private_key'], 'sha256')) {
                return null;
            }

            $jwt = $unsigned . '.' . rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

            $response = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            return $response->successful() ? $response->json('access_token') : null;
        });
    }
}
