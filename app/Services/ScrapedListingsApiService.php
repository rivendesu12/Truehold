<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Primary property feed: Harbor Ops scraped-listings public API.
 *
 * Pages through GET /api/public/scraped-listings (per-tenant public API key,
 * scraped_listings:read scope) and maps rows to the same property-array shape
 * the Google Sheets feed produces, so PropertyFromSheet and the views work
 * unchanged.
 */
class ScrapedListingsApiService
{
    use Concerns\FiltersPropertyCollection;

    protected const CACHE_KEY = 'properties_harborops_all';
    protected const PAGE_SIZE = 200; // API clamps limit to 1-200
    protected const MAX_PAGES = 50;  // safety cap: 10k listings

    // Statuses that mean the room can be let, whatever the env allowlist says.
    // ROLLING and APT BREAK are available rooms; the env can add, not remove.
    protected const ALWAYS_AVAILABLE = ['available', 'rolling', 'apt break'];

    protected ?string $baseUrl;
    protected ?string $apiKey;
    protected int $cacheTimeout;
    protected ?string $portalDomain;
    protected array $availableStatuses;
    protected bool $includeBlankStatus;
    protected int $maxAgeDays;

    public function __construct()
    {
        $this->baseUrl = config('services.harborops.base_url');
        $this->apiKey = config('services.harborops.api_key');
        $this->cacheTimeout = (int) config('services.harborops.cache_timeout', 300);
        $this->portalDomain = config('services.harborops.portal_domain');

        $this->availableStatuses = collect(explode(',', (string) config('services.harborops.available_statuses', 'available')))
            ->merge(self::ALWAYS_AVAILABLE)
            ->map(fn ($v) => strtolower(trim($v)))
            ->filter()
            ->unique()
            ->all();
        $this->includeBlankStatus = (bool) config('services.harborops.include_blank_status', false);
        $this->maxAgeDays = (int) config('services.harborops.max_age_days', 0);
    }

    /**
     * Should this listing be shown?
     *
     * The feed has no status meaning "let", so availability is an allowlist of
     * statuses plus an optional freshness cut-off.
     */
    protected function isAvailable(array $property): bool
    {
        $status = strtolower(trim((string) ($property['status'] ?? '')));

        if ($status === '') {
            if (! $this->includeBlankStatus) {
                return false;
            }
        } elseif (! collect($this->availableStatuses)->contains(fn ($ok) => str_starts_with($status, $ok))) {
            // Prefix match: the sheet appends dates, e.g. "APT BREAK 01/10".
            return false;
        }

        if ($this->maxAgeDays > 0) {
            $seen = $property['updated_at'] ?? $property['created_at'] ?? null;
            if ($seen) {
                try {
                    if (\Carbon\Carbon::parse($seen)->diffInDays(now()) > $this->maxAgeDays) {
                        return false;
                    }
                } catch (\Throwable $e) {
                    // Unparseable date: keep the listing rather than hide it silently.
                }
            }
        }

        return true;
    }

    public static function isConfigured(): bool
    {
        return !empty(config('services.harborops.base_url'))
            && !empty(config('services.harborops.api_key'));
    }

    /**
     * Spreadsheet rows sometimes carry their photo list as one JSON string
     * ('["https://...","https://..."]') rather than a list, so the site saw a
     * single "photo" that was forty links glued together. Always a list of
     * distinct URLs from here on, with all_photos and first_photo_url to match.
     */
    public static function normalisePhotos(array $p): array
    {
        $raw = $p['photos'] ?? null;
        $list = [];

        $take = function ($value) use (&$list, &$take) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    $take($v);
                }
                return;
            }
            $value = trim((string) $value);
            if ($value === '') {
                return;
            }
            if ($value[0] === '[') {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    $take($decoded);
                    return;
                }
            }
            if (str_contains($value, ',') && ! str_starts_with($value, 'data:')) {
                foreach (preg_split('/\s*,\s*(?=https?:|\/)/', $value) as $part) {
                    $part = trim($part, " \t\n\r\"'[]");
                    if ($part !== '') {
                        $list[] = $part;
                    }
                }
                return;
            }
            $list[] = trim($value, "\"'[] ");
        };

        $take($raw);
        if (! $list && ! empty($p['all_photos'])) {
            $take($p['all_photos']);
        }

        $list = array_values(array_unique(array_filter($list, fn ($u) => preg_match('#^(https?://|/)#', $u))));
        if (! $list) {
            return $p;
        }

        $p['photos'] = $list;
        $p['all_photos'] = implode(', ', $list);
        if (empty($p['first_photo_url']) || str_starts_with((string) $p['first_photo_url'], '[')) {
            $p['first_photo_url'] = $list[0];
        }
        $p['photo_count'] = count($list);

        return $p;
    }

    /**
     * Ali's scrape of the Javier / Smart Share sheets ("spreadsheet" rows) is
     * not used: it stopped refreshing in August (12 Melrose House still showed
     * weeks after leaving Javier's list), and we read the agencies' sheets
     * ourselves (Room targets, Soreva).
     */
    protected function isAliSheetCopy(array $p): bool
    {
        return ($p['source'] ?? null) === 'spreadsheet';
    }

    /**
     * Get all properties from the Harbor Ops API (cached).
     */
    public function getAllProperties(): Collection
    {
        return Cache::remember(self::CACHE_KEY, $this->cacheTimeout, function () {
            $notAccepting = $this->notAcceptingListingIds();

            $feed = $this->fetchAllListings()
                ->reject(fn ($p) => isset($notAccepting[(string) ($p['id'] ?? '')]))
                ->filter(fn ($p) => $this->isAvailable($p))
                ->reject(fn ($p) => $this->isAliSheetCopy($p))
                ->map(fn ($p) => self::normalisePhotos($p));

            // Supplier rooms come from the Room targets sheet, not this API. They
            // carry no source advert, so the availability rules above do not apply
            // to them — the sheet's own "Available From" gate does.
            $supplier = app(SupplierTargetsSheetService::class)->getAllProperties();

            // Javier's own sheet is the whole of his vacancy list; Room targets
            // only copies a couple of his rooms, so it gives way when his sheet
            // has been read.
            $javier = app(JavierSheetService::class)->getAllProperties();
            if ($javier->isNotEmpty()) {
                $supplier = $supplier->reject(fn ($p) => str_starts_with((string) ($p['agent_name'] ?? ''), 'Javier'))->values();
            }

            // Suppliers we source ourselves rather than through Harbor Ops:
            // Soreva Living's sheet, and landlords who only advertise on
            // SpareRoom. Both already carry their own availability rules, so
            // the feed's status allowlist does not apply to them.
            $direct = app(SorevaSheetService::class)->getAllProperties()
                ->concat($javier)
                ->concat(app(SpareRoomAdvertService::class)->getAllProperties());

            // Some of these advertisers are also scraped into the Harbor Ops
            // feed, so the same advert arrives twice. Our own copy wins: it
            // carries the right agency name and the commission flag, which the
            // feed's copy does not.
            $feed = $this->rejectDuplicatesOf($feed, $direct);

            $all = $this->geocodeMissing($this->fillMissingPrices($feed->concat($supplier)->concat($direct)));

            // Attach the nearest station, its fare zone and a walking estimate,
            // so "zone 3" and "near a tube" are facts rather than inferences
            // from distance to the centre. Needs coordinates, so it runs last.
            $transport = app(TransportIndex::class);
            if ($transport->isAvailable()) {
                $all = $all->map(fn ($p) => $transport->annotate($p));
            }

            // The occupation columns sometimes hold a person's name where a
            // tenant-type should be, so they are normalised before anything
            // filters on them or renders them.
            $all = $all->map(fn ($p) => $this->normaliseOccupation($p));

            // Name the agency behind each spreadsheet row, and pick up the
            // photo folder, both of which the feed hides in its raw data.
            $all = $all->map(fn ($p) => $this->attributeSheetRow($p));

            // Real pictures for the spreadsheet-sourced rows. Those arrive with
            // no photo fields at all but many carry a Drive folder, so their
            // photographs exist and were simply never resolved — the cards
            // were showing a stock image of someone else's flat instead.
            $all = $this->attachDrivePhotos($all);

            // What each listing is worth to us, so "prioritise commission"
            // can rank by money rather than by a yes/no flag.
            $commission = app(CommissionRates::class);
            $all = $all->map(fn ($p) => $commission->annotate($p));

            // What the listing actually offers: bedrooms for a whole flat,
            // house size and room type for a room. The feed states these only
            // for rooms, so whole flats had no bedroom count at all.
            $all = $all->map(fn ($p) => array_merge($p, \App\Support\RoomFacts::extract($p)));

            // The upstream feed sometimes scrapes the same advert twice, and has
            // returned two rows sharing one listing id. Either breaks links and
            // shows an agent the same room twice, so one of each survives —
            // matched on the source advert, never on the title, because four
            // rooms in one house legitimately share a generic title.
            $all = $this->deduplicate($all);

            if (config('services.harborops.require_title_and_price', true)) {
                $all = $all->filter(
                    fn ($p) => trim((string) ($p['title'] ?? '')) !== '' && ! empty($p['price'])
                );
            }

            return $all->values();
        });
    }

    /**
     * One row per listing id, and one row per source advert.
     *
     * Where two rows describe the same advert the richer one is kept: more
     * photographs, then a stated agency, then a price. An agent comparing two
     * identical cards cannot tell which to send.
     */
    protected function deduplicate(Collection $properties): Collection
    {
        $richness = function (array $p): int {
            return (int) ($p['photo_count'] ?? 0) * 10
                + (! empty($p['agent_name']) ? 5 : 0)
                + (! empty($p['price']) ? 2 : 0)
                + (! empty($p['description']) ? 1 : 0);
        };

        $best = [];

        foreach ($properties as $property) {
            // An advert id groups rows across sources; the listing id catches
            // the upstream feed repeating itself with no advert to match on.
            $key = $this->spareRoomAdvertId($property)
                ?? ('id:' . (string) ($property['id'] ?? uniqid('row', true)));

            if (! isset($best[$key]) || $richness($property) > $richness($best[$key])) {
                $best[$key] = $property;
            }
        }

        return collect(array_values($best));
    }

    /**
     * Drop feed rows that are the same SpareRoom advert as one we sourced
     * directly.
     *
     * The feed writes the reference as "spareroom:18427068" and we write
     * "18427068", so the ids match once the prefix is stripped. Matching on
     * the advert id rather than the title matters: four rooms in the same
     * Wembley house share one generic title at four different rents, and
     * collapsing those would lose real stock.
     */
    protected function rejectDuplicatesOf(Collection $feed, Collection $direct): Collection
    {
        $ids = $direct
            ->map(fn ($p) => $this->spareRoomAdvertId($p))
            ->filter()
            ->flip();

        if ($ids->isEmpty()) {
            return $feed;
        }

        return $feed->reject(function (array $property) use ($ids) {
            $id = $this->spareRoomAdvertId($property);

            return $id !== null && $ids->has($id);
        });
    }

    /** The numeric SpareRoom advert id, however the source spells it. */
    protected function spareRoomAdvertId(array $property): ?string
    {
        $ref = (string) ($property['external_ref'] ?? '');

        if (preg_match('/(?:^|:)(\d{6,9})$/', $ref, $m)) {
            return $m[1];
        }

        foreach (['link', 'url'] as $field) {
            $value = (string) ($property[$field] ?? '');

            if (preg_match('/flatshare_id=(\d{6,9})/', $value, $m)
                || preg_match('#spareroom\.co\.uk/(\d{6,9})\b#', $value, $m)) {
                return $m[1];
            }
        }

        return null;
    }

    /**
     * Keep only recognisable tenant-type values in the occupation columns.
     *
     * Upstream column alignment slips, and when it does a real tenant's name
     * lands in pref_occupation — "Sol Lip Jang / Daewoong Kim" was being
     * served on a public page, and the students filter was matching against
     * people's names. The field has three real values; anything else is a
     * mis-mapping and is dropped rather than shown.
     */
    protected function normaliseOccupation(array $property): array
    {
        foreach (['occupation', 'pref_occupation'] as $field) {
            $value = trim((string) ($property[$field] ?? ''));

            if ($value === '') {
                continue;
            }

            $recognised = preg_match(
                '/available to all|student|professional|working|employed|any\b|no preference/i',
                $value
            );

            if (! $recognised) {
                $property[$field] = null;
            }
        }

        return $property;
    }

    /**
     * Recover the agency and the photo folder from a spreadsheet row's raw data.
     *
     * The feed maps almost none of what it scrapes: 82 rows arrived with no
     * agency name at all, so they could not be filtered by agency and a
     * commission search could never reach them — which is why Javier's E14
     * en-suites were missing. Their raw data names the operating company
     * (JMS and FENIX are both Javier) and carries a folder link to the
     * photographs.
     *
     * Their raw data also carries tenant names and phone numbers. Those are
     * read past and never copied onto the listing.
     */
    protected function attributeSheetRow(array $property): array
    {
        $raw = $property['raw_row'] ?? null;

        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        if (! is_array($raw)) {
            return $property;
        }

        if (empty($property['agent_name'])) {
            $company = strtolower(trim((string) ($raw['Company'] ?? $raw['company'] ?? '')));
            $mapped = config('suppliers.sheet_companies')[$company] ?? null;

            if ($mapped === null && $company !== '') {
                // An unmapped company is still better named than anonymous.
                $mapped = ucwords($company);
            }

            if ($mapped !== null) {
                $property['agent_name'] = $mapped;
                $property['landlord_name'] = $property['landlord_name'] ?: $mapped;
            }
        }

        foreach (['Folder link', 'folder link', 'Folder Link'] as $key) {
            $link = trim((string) ($raw[$key] ?? ''));

            if ($link !== '' && empty($property['drive_folder_url'])) {
                $folder = $this->extractSheetHyperlink($link);

                if ($folder !== null) {
                    $property['drive_folder_url'] = $folder;
                }
                break;
            }
        }

        if (empty($property['postcode'])) {
            $postcode = trim((string) ($raw['Post Code'] ?? $raw['Postcode'] ?? ''));
            if ($postcode !== '') {
                $property['postcode'] = strtoupper($postcode);
            }
        }

        return $property;
    }

    /** A folder link may be a bare url or a =HYPERLINK() formula. */
    protected function extractSheetHyperlink(string $value): ?string
    {
        if (preg_match('/HYPERLINK\(\s*"([^"]+)"/i', $value, $m)) {
            $value = $m[1];
        }

        return str_starts_with($value, 'http') ? $value : null;
    }

    /**
     * Resolve Drive folders into servable photo URLs.
     *
     * Only for rows that have no photos of their own: the feed's own SpareRoom
     * rows already carry theirs. Photos are streamed through the supplier-photo
     * proxy because the folders are private, and the route is given relative so
     * a cached row survives a domain change and can never go mixed-content.
     */
    protected function attachDrivePhotos(Collection $properties): Collection
    {
        $photos = app(SupplierPhotoService::class);

        // Only a console run may go and fetch; a page request uses the cache.
        $blocking = app()->runningInConsole();

        return $properties->map(function (array $property) use ($photos, $blocking) {
            $hasPhotos = ! empty($property['all_photos'])
                || ! empty($property['photos'])
                || (! empty($property['first_photo_url']) && $property['first_photo_url'] !== 'N/A');

            if ($hasPhotos) {
                return $property;
            }

            $folder = ($property['drive_room_folder'] ?? null) ?: ($property['drive_folder_url'] ?? null);
            if (empty($folder)) {
                return $property;
            }

            try {
                // In a web request, only what is already cached: a page load
                // must never wait on Drive. The warm-up command and the
                // scheduler do the fetching.
                $ids = $blocking
                    ? $photos->photosForRoom($folder, $property['source_room'] ?? null)
                    : $photos->cachedPhotosForRoom($folder, $property['source_room'] ?? null);
            } catch (\Throwable $e) {
                // A folder we cannot read must not take the whole feed down.
                Log::warning('Drive photo lookup failed', [
                    'listing' => $property['id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
                return $property;
            }

            if (! $ids) {
                return $property;
            }

            $urls = array_map(fn ($id) => route('supplier.photo', ['fileId' => $id], false), $ids);

            $property['photos'] = $urls;
            $property['all_photos'] = implode(', ', $urls);
            $property['first_photo_url'] = $urls[0];
            $property['photo_count'] = count($urls);

            return $property;
        });
    }

    /**
     * Fill blank prices from the AP / Horizon portfolio workbook.
     *
     * The feed's spreadsheet-sourced rows arrive without a price even though the
     * source workbook has one, so the card renders "N/A". Match on the property
     * name in the title plus the postcode.
     */
    protected function fillMissingPrices(Collection $properties): Collection
    {
        if ($properties->every(fn ($p) => ! empty($p['price']))) {
            return $properties;
        }

        $ap = app(ApPortfolioPriceService::class);
        $lookup = $ap->prices();
        if (! $lookup) {
            return $properties;
        }

        return $properties->map(function (array $property) use ($ap, $lookup) {
            if (! empty($property['price'])) {
                return $property;
            }

            // Titles look like "7 Marina Point, 14 Lanark Square" or
            // "Broxbourne House — Room A"; the workbook keys on the building.
            $title = (string) ($property['title'] ?? '');
            $name = trim(preg_split('/[—,|]/u', $title)[0] ?? $title);

            $postcode = $property['postcode'] ?? null;
            if (! $postcode && preg_match('/\b([A-Z]{1,2}[0-9][A-Z0-9]?\s*[0-9][A-Z]{2})\b/i', $title . ' ' . ($property['location'] ?? ''), $m)) {
                $postcode = $m[1];
            }

            $price = $ap->priceFor($lookup, $name, $property['source_room'] ?? null, $postcode);

            if ($price !== null) {
                $property['price'] = $price;
                $property['price_from'] = 'ap_portfolio';
            }

            return $property;
        });
    }

    /**
     * Fill in coordinates for listings the feed gives none for.
     *
     * The spreadsheet-sourced rows in the feed carry no lat/long, so they can
     * never appear on the map — but most do have a full postcode in the title
     * or location. Extract it and geocode in one bulk lookup.
     */
    protected function geocodeMissing(Collection $properties): Collection
    {
        $geocoder = app(PostcodeGeocoder::class);
        $pattern = '/\b([A-Z]{1,2}[0-9][A-Z0-9]?)\s*([0-9][A-Z]{2})\b/i';

        $wanted = [];
        $perRow = [];

        foreach ($properties as $i => $property) {
            if (! empty($property['latitude']) && ! empty($property['longitude'])) {
                continue;
            }

            $haystack = ($property['title'] ?? '') . ' '
                . ($property['location'] ?? '') . ' '
                . ($property['description'] ?? '');

            if (preg_match($pattern, $haystack, $m)) {
                $postcode = $geocoder->normalise($m[1] . $m[2]);
                $perRow[$i] = $postcode;
                $wanted[] = $postcode;
            }
        }

        if (! $wanted) {
            return $properties;
        }

        $coords = $geocoder->lookupMany($wanted);

        return $properties->map(function (array $property, $i) use ($perRow, $coords) {
            if (isset($perRow[$i], $coords[$perRow[$i]])) {
                $property['latitude'] = $coords[$perRow[$i]]['lat'];
                $property['longitude'] = $coords[$perRow[$i]]['lng'];
                $property['geocoded_from_postcode'] = true;
            }
            return $property;
        });
    }

    /**
     * Every listing the feed returns, with no availability filtering applied.
     * Used by properties:check-availability so the checker is not filtered by
     * the data it is itself producing.
     */
    public function getAllPropertiesUnfiltered(): Collection
    {
        return Cache::remember(self::CACHE_KEY . '_unfiltered', $this->cacheTimeout, function () {
            return $this->fetchAllListings();
        });
    }

    /**
     * Listing ids whose source advert is not taking enquiries, keyed for O(1)
     * lookup. Missing table (before migration) must not break the feed.
     */
    protected function notAcceptingListingIds(): array
    {
        try {
            return \Illuminate\Support\Facades\DB::table('property_availability')
                ->where('accepting_applications', false)
                ->pluck('listing_id')
                ->mapWithKeys(fn ($id) => [(string) $id => true])
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get a single property by ID (scraped_listings row uuid).
     */
    public function getPropertyById($id): ?array
    {
        return $this->getAllProperties()->firstWhere('id', (string) $id);
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CACHE_KEY . '_unfiltered');
        Log::info('Harbor Ops properties cache cleared', ['cache_key' => self::CACHE_KEY]);
    }

    /**
     * Page through the API until has_more is false.
     *
     * @throws \RuntimeException when the API is unreachable and nothing was fetched,
     *                           so callers can fall back to another source.
     */
    protected function fetchAllListings(): Collection
    {
        $endpoint = rtrim($this->baseUrl, '/') . '/api/public/scraped-listings';
        $properties = collect();
        $offset = 0;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->timeout(30)
                ->retry(2, 250, throw: false)
                ->get($endpoint, [
                    'limit' => self::PAGE_SIZE,
                    'offset' => $offset,
                    'sort' => 'created_at.desc',
                ]);

            if ($response->failed()) {
                Log::error('Harbor Ops scraped-listings request failed', [
                    'status' => $response->status(),
                    'offset' => $offset,
                    'body' => mb_substr($response->body(), 0, 500),
                ]);

                if ($properties->isEmpty()) {
                    throw new \RuntimeException(
                        'Harbor Ops scraped-listings API request failed with status ' . $response->status()
                    );
                }

                // Partial fetch: serve what we have rather than nothing
                break;
            }

            $json = $response->json();
            $rows = $json['data'] ?? [];

            foreach ($rows as $row) {
                if (is_array($row)) {
                    $properties->push($this->mapRowToProperty($row));
                }
            }

            $hasMore = (bool) ($json['pagination']['has_more'] ?? false);
            if (!$hasMore || count($rows) === 0) {
                break;
            }

            $offset += self::PAGE_SIZE;
        }

        Log::info('Properties fetched from Harbor Ops scraped-listings API', [
            'count' => $properties->count(),
            'pages' => $page + 1,
        ]);

        return $properties;
    }

    /**
     * Map a scraped_listings row to the property-array shape the views expect
     * (same shape the Google Sheets feed produces).
     */
    protected function mapRowToProperty(array $row): array
    {
        $property = $row;

        $property['id'] = (string) ($row['id'] ?? '');

        // Views and PropertyFromSheet::url read the listing URL from 'link'
        $property['link'] = $row['url'] ?? null;

        // Rows carry landlord_id (uuid), not a display name
        $property['agent_id'] = $row['landlord_id'] ?? null;
        $property['agent_name'] = $row['agent_name'] ?? ($row['landlord_name'] ?? null);

        // Login-gated deep link into Harbor Ops (step 4 of the integration doc)
        if (!empty($row['landlord_id']) && !empty($this->portalDomain)) {
            $property['landlord_profile_url'] = 'https://' . $this->portalDomain
                . '/go/landlord/' . $row['landlord_id'];
        }

        foreach (['latitude', 'longitude'] as $coord) {
            $value = $row[$coord] ?? null;
            if (is_string($value)) {
                $value = str_replace(',', '.', trim($value));
            }
            $property[$coord] = is_numeric($value) ? (float) $value : null;
        }

        $property['photo_count'] = is_numeric($row['photo_count'] ?? null)
            ? (int) $row['photo_count']
            : 0;

        $property['status'] = $row['status'] ?? null;
        $property['updated_at'] = $row['updated_at'] ?? null;
        $property['created_at'] = $row['created_at'] ?? null;
        $property['updatable'] = false;

        return $property;
    }
}
