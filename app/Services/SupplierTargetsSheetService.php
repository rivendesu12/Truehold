<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Google\Service\Sheets as GoogleSheets;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Supplier rooms from the "Room targets" spreadsheet, Targets tab.
 *
 * Targets is the only place every agency is merged into one normalised shape —
 * Banksia and Soreva included, which no API or importer covers. Read with
 * valueRenderOption=FORMULA so the Pictures Link hyperlink survives (the
 * display value is just the word "View").
 *
 * Rows are mapped to the same array shape the Harbor Ops feed produces, so the
 * list view, map and detail pages consume them unchanged.
 */
class SupplierTargetsSheetService
{
    protected const CACHE_KEY = 'properties_supplier_targets';

    // 0-based column indices in the Targets tab.
    private const COL_AREA = 0;
    private const COL_POSTCODE = 1;
    private const COL_PROPERTY = 2;
    private const COL_PRICE = 3;
    private const COL_AVAILABLE = 4;
    private const COL_ROOM_TYPE = 5;
    private const COL_MAX_OCCUPANCY = 6;
    private const COL_PICTURES = 7;
    private const COL_ROOM_NO = 8;
    private const COL_SUPPLIER = 9;
    private const COL_BEDS = 10;
    private const COL_BATHS = 11;
    private const COL_BILLS = 12;
    private const COL_CONTRACT = 13;

    protected ?string $spreadsheetId;
    protected string $tab;
    protected int $cacheTimeout;
    protected int $windowDays;
    protected array $suppliers;

    public function __construct()
    {
        $this->spreadsheetId = config('services.supplier_targets.spreadsheet_id');
        $this->tab = (string) config('services.supplier_targets.tab', 'Targets');
        $this->cacheTimeout = (int) config('services.supplier_targets.cache_timeout', 900);
        $this->windowDays = (int) config('services.supplier_targets.window_days', 62);
        $this->suppliers = collect(explode(',', (string) config('services.supplier_targets.suppliers', '')))
            ->map(fn ($s) => strtolower(trim($s)))
            ->filter()
            ->all();
    }

    public static function isConfigured(): bool
    {
        return ! empty(config('services.supplier_targets.spreadsheet_id'))
            && ! empty(config('services.supplier_targets.credentials_path'));
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

    /**
     * Available supplier rooms, mapped to the shared property shape.
     * Never throws: a sheet problem must not take the property feed down.
     */
    public function getAllProperties(): Collection
    {
        if (! self::isConfigured()) {
            return collect();
        }

        $cached = Cache::get(self::CACHE_KEY);

        if ($cached !== null) {
            return collect($cached);
        }

        try {
            $rows = $this->fetch();
        } catch (\Throwable $e) {
            Log::error('Supplier targets sheet read failed', ['error' => $e->getMessage()]);

            // Never cache a failure. Storing an empty result took every
            // Banksia, AP and Javier room off the site and kept them off,
            // because each retry failed the same way and re-cached the
            // emptiness. The last good copy is far better than nothing.
            return collect($this->lastGood());
        }

        Cache::put(self::CACHE_KEY, $rows->all(), $this->cacheTimeout);
        $this->rememberLastGood($rows);

        return $rows;
    }

    protected function fetch(): Collection
    {
        $client = new GoogleClient();
        $client->setScopes([GoogleSheets::SPREADSHEETS_READONLY]);
        $client->setAuthConfig(config('services.supplier_targets.credentials_path'));

        $sheets = new GoogleSheets($client);
        $response = $sheets->spreadsheets_values->get(
            $this->spreadsheetId,
            $this->tab,
            ['valueRenderOption' => 'FORMULA']
        );

        $rows = $response->getValues() ?? [];
        if (count($rows) < 2) {
            return collect();
        }

        $out = collect();

        foreach (array_slice($rows, 1) as $row) {
            $supplier = trim((string) ($row[self::COL_SUPPLIER] ?? ''));
            $property = trim((string) ($row[self::COL_PROPERTY] ?? ''));

            // Blank spacer rows are interleaved throughout the tab.
            if ($supplier === '' || $property === '') {
                continue;
            }

            // Substring match: the sheet uses "Javier (FENIX)" / "Javier (JMS)",
            // so an allowlist entry of "javier" has to catch both.
            if ($this->suppliers) {
                $needle = strtolower($supplier);
                $matches = false;
                foreach ($this->suppliers as $allowed) {
                    if (str_contains($needle, $allowed)) {
                        $matches = true;
                        break;
                    }
                }
                if (! $matches) {
                    continue;
                }
            }

            $availableRaw = trim((string) ($row[self::COL_AVAILABLE] ?? ''));
            $availableDate = $this->parseAvailability($availableRaw);
            if ($availableDate === null) {
                continue; // blank, unparseable, or beyond the horizon
            }

            $out->push($this->mapRow($row, $supplier, $property, $availableRaw, $availableDate));
        }

        return $this->attachCoordinates($this->fillMissingPrices($out))->values();
    }

    /**
     * The Targets tab's Price column has gaps; the AP portfolio workbook does not.
     * Fill any blank price from there before the rows reach the site.
     */
    protected function fillMissingPrices(Collection $rows): Collection
    {
        if ($rows->every(fn ($r) => ! empty($r['price']))) {
            return $rows;
        }

        $ap = app(ApPortfolioPriceService::class);
        $lookup = $ap->prices();
        if (! $lookup) {
            return $rows;
        }

        return $rows->map(function (array $row) use ($ap, $lookup) {
            if (! empty($row['price'])) {
                return $row;
            }

            // The room code is appended to the title, so use the raw parts.
            $price = $ap->priceFor(
                $lookup,
                $row['source_property'] ?? $row['title'],
                $row['source_room'] ?? null,
                $row['postcode'] ?? null
            );

            if ($price !== null) {
                $row['price'] = $price;
                $row['price_from'] = 'ap_portfolio';
            }

            return $row;
        });
    }

    /**
     * Fill in lat/long from the postcode, in one bulk lookup for the whole batch.
     * Without coordinates these rooms can never be plotted on the map view.
     */
    protected function attachCoordinates(Collection $rows): Collection
    {
        if ($rows->isEmpty()) {
            return $rows;
        }

        $geocoder = app(PostcodeGeocoder::class);
        $coords = $geocoder->lookupMany($rows->pluck('postcode')->filter()->all());

        return $rows->map(function (array $row) use ($geocoder, $coords) {
            $key = $geocoder->normalise((string) ($row['postcode'] ?? ''));
            if ($key !== '' && isset($coords[$key])) {
                $row['latitude'] = $coords[$key]['lat'];
                $row['longitude'] = $coords[$key]['lng'];
            }
            return $row;
        });
    }

    /**
     * "now" means today. Otherwise dd/mm/yyyy, shown only if within the window.
     * Past dates are still shown (upper bound only), matching the sheet's own sync.
     */
    protected function parseAvailability(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        if (preg_match('/\bnow\b/i', $value)) {
            return Carbon::today();
        }

        foreach (['d/m/Y', 'd/m/y', 'j/n/Y'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date && $date->lessThanOrEqualTo(Carbon::today()->addDays($this->windowDays))) {
                    return $date;
                }
                return null;
            } catch (\Throwable $e) {
                // try the next format
            }
        }

        return null;
    }

    protected function mapRow(array $row, string $supplier, string $property, string $availableRaw, Carbon $availableDate): array
    {
        $cell = fn (int $i) => trim((string) ($row[$i] ?? ''));

        $roomNo = $cell(self::COL_ROOM_NO);
        $area = $cell(self::COL_AREA);
        $postcode = $cell(self::COL_POSTCODE);

        $folderUrl = $this->extractHyperlink($cell(self::COL_PICTURES));
        $photoIds = app(SupplierPhotoService::class)->photosForRoom($folderUrl, $roomNo);
        // Relative, so cached rows survive a domain change and never go mixed-content.
        $photoUrls = array_map(fn ($id) => route('supplier.photo', ['fileId' => $id], false), $photoIds);

        $title = $property . ($roomNo !== '' ? ' — Room ' . $roomNo : '');

        $descriptionParts = array_filter([
            $cell(self::COL_ROOM_TYPE) ? ucfirst($cell(self::COL_ROOM_TYPE)) : null,
            $cell(self::COL_BEDS) ? $cell(self::COL_BEDS) . ' bed' : null,
            $cell(self::COL_BATHS) ? $cell(self::COL_BATHS) . ' bath' : null,
            $cell(self::COL_BILLS) ? 'Bills: ' . $cell(self::COL_BILLS) : null,
            $cell(self::COL_CONTRACT) ? 'Contract: ' . $cell(self::COL_CONTRACT) : null,
            'Available: ' . $availableRaw,
        ]);

        return [
            // Stable across syncs so caching and links do not churn.
            'id' => 'sheet-' . substr(sha1(strtolower($supplier . '|' . $property . '|' . $roomNo)), 0, 20),
            'title' => $title,
            'location' => $area !== '' ? $area : $postcode,
            'postcode' => $postcode,
            'latitude' => null,
            'longitude' => null,
            'price' => $this->parsePrice($cell(self::COL_PRICE)),
            'description' => implode(' · ', $descriptionParts),
            'property_type' => $cell(self::COL_ROOM_TYPE) ?: 'Room',
            'available_date' => $availableDate->toDateString(),
            'photo_count' => count($photoUrls),
            'first_photo_url' => $photoUrls[0] ?? null,
            'all_photos' => $photoUrls,
            // No source advert, so properties:check-availability skips these by design.
            'url' => null,
            'pictures_folder_url' => $this->extractHyperlink($cell(self::COL_PICTURES)),
            'agent_name' => $supplier,
            'agent_id' => null,
            'management_company' => $supplier,
            'source_property' => $property,
            'source_room' => $roomNo !== '' ? $roomNo : null,
            'total_rooms' => $cell(self::COL_BEDS) ?: null,
            'max_occupancy' => $cell(self::COL_MAX_OCCUPANCY) ?: null,
            'status' => 'available',
            'source' => 'supplier_sheet',
            'updated_at' => now()->toIso8601String(),
            'updatable' => false,
        ];
    }

    protected function parsePrice(string $value): ?float
    {
        $clean = preg_replace('/[^0-9.]/', '', $value);
        return $clean === '' ? null : (float) $clean;
    }

    /** Pictures Link is =HYPERLINK("url","View"); pull the url out. */
    protected function extractHyperlink(string $value): ?string
    {
        if (preg_match('/HYPERLINK\(\s*"([^"]+)"/i', $value, $m)) {
            return $m[1];
        }
        return str_starts_with($value, 'http') ? $value : null;
    }
}
