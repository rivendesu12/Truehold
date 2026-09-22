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
            ->map(fn ($v) => strtolower(trim($v)))
            ->filter()
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
        } elseif (! in_array($status, $this->availableStatuses, true)) {
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
     * Get all properties from the Harbor Ops API (cached).
     */
    public function getAllProperties(): Collection
    {
        return Cache::remember(self::CACHE_KEY, $this->cacheTimeout, function () {
            $notAccepting = $this->notAcceptingListingIds();

            $feed = $this->fetchAllListings()
                ->reject(fn ($p) => isset($notAccepting[(string) ($p['id'] ?? '')]))
                ->filter(fn ($p) => $this->isAvailable($p));

            // Supplier rooms come from the Room targets sheet, not this API. They
            // carry no source advert, so the availability rules above do not apply
            // to them — the sheet's own "Available From" gate does.
            $supplier = app(SupplierTargetsSheetService::class)->getAllProperties();

            $all = $this->geocodeMissing($this->fillMissingPrices($feed->concat($supplier)));

            // Attach the nearest station, its fare zone and a walking estimate,
            // so "zone 3" and "near a tube" are facts rather than inferences
            // from distance to the centre. Needs coordinates, so it runs last.
            $transport = app(TransportIndex::class);
            if ($transport->isAvailable()) {
                $all = $all->map(fn ($p) => $transport->annotate($p));
            }

            // What each listing is worth to us, so "prioritise commission"
            // can rank by money rather than by a yes/no flag.
            $commission = app(CommissionRates::class);
            $all = $all->map(fn ($p) => $commission->annotate($p));

            // What the listing actually offers: bedrooms for a whole flat,
            // house size and room type for a room. The feed states these only
            // for rooms, so whole flats had no bedroom count at all.
            $all = $all->map(fn ($p) => array_merge($p, \App\Support\RoomFacts::extract($p)));

            if (config('services.harborops.require_title_and_price', true)) {
                $all = $all->filter(
                    fn ($p) => trim((string) ($p['title'] ?? '')) !== '' && ! empty($p['price'])
                );
            }

            return $all->values();
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
