<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Rooms from landlords who advertise on SpareRoom but are not in the Harbor
 * Ops feed.
 *
 * We are given one advert per advertiser. Each advert page lists that
 * advertiser's other adverts, so a crawl from the seed finds their whole
 * portfolio — and keeps finding it, which means their new rooms appear without
 * anyone pasting a link. Every page also exposes the advertiser's own user id,
 * and an advert is only accepted if that id matches the one configured, so a
 * crawl cannot drift onto a neighbouring advertiser's stock.
 *
 * The pages carry exactly the fields the rest of the site already works with —
 * price, room type, deposit, terms, bills, flatmates, total rooms, couples,
 * smoking, age limits — plus precise coordinates, so zone, nearest station and
 * journey times all attach with no extra work. They also carry "the advertiser
 * is not currently accepting applications", which is the signal that a room
 * has gone.
 */
class SpareRoomAdvertService
{
    protected const CACHE_KEY = 'properties_spareroom_direct';
    protected const BASE = 'https://www.spareroom.co.uk';

    protected const AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) '
        . 'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36';

    public static function isConfigured(): bool
    {
        return ! empty(config('suppliers.spareroom.advertisers'));
    }

    public function getAllProperties(): Collection
    {
        if (! self::isConfigured()) {
            return collect();
        }

        // Crawling is dozens of requests to someone else's site, several
        // seconds apart by design. Never during a page load: a web request
        // reads the cache and, if it is cold, serves without these rooms
        // until the scheduled warm-up fills it in.
        if (! app()->runningInConsole()) {
            return collect(Cache::get(self::CACHE_KEY, []));
        }

        $cached = Cache::get(self::CACHE_KEY);
        if ($cached !== null) {
            return collect($cached);
        }

        $rooms = $this->crawlAll();

        // A crawl SpareRoom refused comes back empty or thin: keep serving the
        // last good one rather than dropping these agencies from the site.
        $lastGood = (array) Cache::get(self::CACHE_KEY . '_last_good', []);
        if ($rooms->count() < count($lastGood) / 2) {
            Log::warning('SpareRoom crawl came back thin; serving the last good copy', ['got' => $rooms->count(), 'had' => count($lastGood)]);
            Cache::put(self::CACHE_KEY, $lastGood, now()->addHour());

            return collect($lastGood);
        }

        Cache::put(self::CACHE_KEY, $rooms->all(), (int) config('suppliers.spareroom.cache_timeout', 21600));
        Cache::put(self::CACHE_KEY . '_last_good', $rooms->all(), now()->addDays(14));

        return $rooms;
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return Collection<int, array> */
    public function crawlAll(?callable $progress = null): Collection
    {
        $out = collect();

        $advertisers = array_merge(
            (array) config('suppliers.spareroom.advertisers', []),
            (array) config('suppliers.feed_agencies', []),
        );
        foreach ($advertisers as $advertiser) {
            // Switched off for now, kept in the config.
            if (($advertiser['active'] ?? true) === false) {
                continue;
            }
            $out = $out->concat($this->crawlAdvertiser($advertiser, $progress));
        }

        // The same room can be advertised twice; keep one of each.
        return $out->unique('id')->values();
    }

    /**
     * Breadth-first from the seed adverts, following only adverts that belong
     * to this advertiser.
     */
    public function crawlAdvertiser(array $advertiser, ?callable $progress = null): Collection
    {
        $userId = (string) ($advertiser['user_id'] ?? '');
        // Their own listings page names every advert they have up; the seeds
        // and "more from this advertiser" links fill in if it is unavailable.
        $queue = array_values(array_unique(array_merge(
            $userId !== '' ? $this->advertiserAdvertIds($userId) : [],
            array_map('strval', $advertiser['seeds'] ?? []),
        )));
        $seen = [];
        $found = collect();
        $cap = (int) config('suppliers.spareroom.max_adverts_per_advertiser', 40);
        $delay = (int) config('suppliers.spareroom.delay_ms', 700);

        while ($queue && count($seen) < $cap) {
            $advertId = array_shift($queue);

            if (isset($seen[$advertId])) {
                continue;
            }
            $seen[$advertId] = true;

            $html = $this->fetch($advertId);
            if ($html === null) {
                $progress && $progress($advertiser['name'], $advertId, 'unreachable');
                continue;
            }

            // Withdrawn adverts sometimes still render, so identity is checked
            // before anything is trusted.
            $pageUser = $this->userId($html);
            if ($userId !== '' && $pageUser !== null && $pageUser !== $userId) {
                $progress && $progress($advertiser['name'], $advertId, "belongs to {$pageUser}, skipped");
                continue;
            }

            foreach ($this->siblingAdvertIds($html) as $sibling) {
                if (! isset($seen[$sibling])) {
                    $queue[] = $sibling;
                }
            }

            $property = $this->parse($html, $advertId, $advertiser);

            if ($property === null) {
                $progress && $progress($advertiser['name'], $advertId, 'let or unparseable');
                continue;
            }

            $found->push($property);
            $progress && $progress($advertiser['name'], $advertId, 'ok: ' . $property['title']);

            if ($delay > 0) {
                usleep($delay * 1000);
            }
        }

        return $found;
    }

    /**
     * Every advert id on an advertiser's own SpareRoom page (/u{id}), ten to
     * a page.
     *
     * @return array<int, string>
     */
    public function advertiserAdvertIds(string $userId, int $maxPages = 8): array
    {
        $ids = [];
        $delay = (int) config('suppliers.spareroom.delay_ms', 700);

        for ($page = 0; $page < $maxPages; $page++) {
            try {
                $response = Http::withHeaders(['User-Agent' => self::AGENT])->timeout(30)
                    ->get(self::BASE . '/u' . $userId, ['offset' => $page * 10]);
            } catch (\Throwable $e) {
                break;
            }
            if (! $response->successful()) {
                break;
            }
            preg_match_all('/data-listing-id="(\d+)"/', $response->body(), $m);
            $new = array_diff(array_unique($m[1]), $ids);
            if (! $new) {
                break;
            }
            $ids = array_merge($ids, array_values($new));
            if (count($m[1]) < 10) {
                break;
            }
            usleep($delay * 1000);
        }

        return $ids;
    }

    protected function fetch(string $advertId): ?string
    {
        try {
            $response = Http::withHeaders(['User-Agent' => self::AGENT])
                ->timeout(30)
                ->retry(2, 500, throw: false)
                ->get(self::BASE . '/flatshare/flatshare_detail.pl', [
                    'flatshare_id' => $advertId,
                    'mode' => 'details',
                ]);
        } catch (\Throwable $e) {
            Log::warning('SpareRoom advert fetch failed', ['advert' => $advertId, 'error' => $e->getMessage()]);
            return null;
        }

        return $response->successful() ? $response->body() : null;
    }

    /**
     * The advertiser's own id, taken from the report-this-ad link, which is the
     * only place on the page that states it unambiguously.
     */
    public function userId(string $html): ?string
    {
        return preg_match('/offending_user_id=(\d+)/', $html, $m) ? $m[1] : null;
    }

    /** Advert ids under "More from the same advertiser". */
    public function siblingAdvertIds(string $html): array
    {
        $position = strpos($html, 'More from the same advertiser');
        if ($position === false) {
            return [];
        }

        $block = substr($html, $position, 6000);
        preg_match_all('/flatshare(?:_id=|\/[a-z_]+\/)(\d{6,9})/', $block, $m);

        return array_values(array_unique($m[1] ?? []));
    }

    /**
     * Turn an advert page into the property shape the rest of the site uses.
     * Returns null when the room is not actually available.
     */
    public function parse(string $html, string $advertId, array $advertiser): ?array
    {
        // Giaco's rule: this wording means the room has gone, whatever else
        // the page says.
        if (stripos($html, 'not currently accepting applications') !== false) {
            return null;
        }

        $lines = $this->textLines($html);
        $price = $this->price($lines, $html);
        $title = $this->title($html);

        if ($title === null || $price === null) {
            return null;
        }

        [$lat, $lng] = $this->coordinates($html);

        $totalRooms = $this->intAfter($lines, 'Total # rooms');
        $household = $this->household($lines);
        $roomType = $this->roomType($lines);
        $photos = $this->photos($html);

        return [
            'id' => 'spareroom-' . $advertId,
            'source' => 'spareroom_direct',
            'external_ref' => $advertId,
            'title' => $title,
            'description' => $this->description($lines),
            'price' => $price,
            'property_type' => $this->propertyType($title, $roomType),
            'room1_type' => $roomType,
            'location' => $this->location($lines, $html),
            'postcode' => $this->outcode($html) ?? $this->keyFeatures($lines)['outcode'],
            'latitude' => $lat,
            'longitude' => $lng,
            'link' => self::BASE . '/' . $advertId,
            'url' => self::BASE . '/' . $advertId,
            'agent_name' => $advertiser['name'] ?? null,
            'landlord_name' => $advertiser['name'] ?? null,
            'paying' => ! empty($advertiser['pays_commission']) ? 'yes' : 'no',
            'status' => 'available',
            'available_date' => $this->availableDate($lines),
            'room_count' => 1,
            'total_rooms' => $totalRooms,
            'housemates' => $household['count'] ?? $this->intAfter($lines, 'flatmates'),
            'household' => $household,
            'deposit' => $this->moneyAfter($lines, 'Deposit'),
            'bills_included' => $this->valueAfter($lines, 'Bills included?'),
            'min_term' => $this->valueAfter($lines, 'Minimum term'),
            'max_term' => $this->valueAfter($lines, 'Maximum term'),
            'furnishings' => $this->valueAfter($lines, 'Furnishings'),
            'parking' => $this->valueAfter($lines, 'Parking'),
            'garden' => $this->valueAfter($lines, 'Garden/patio'),
            'couples_ok' => $this->valueAfter($lines, 'Couples OK?'),
            'smoking_ok' => $this->valueAfter($lines, 'Smoking OK?'),
            'pets_ok' => $this->valueAfter($lines, 'Pets suitable?'),
            'references' => $this->valueAfter($lines, 'References?'),
            'min_age' => $this->intAfter($lines, 'Min age'),
            'max_age' => $this->intAfter($lines, 'Max age'),
            'gender' => $this->valueAfter($lines, 'Gender'),
            'pref_occupation' => $this->occupation($lines),
            'first_photo_url' => $photos[0] ?? null,
            'photos' => $photos ?: null,
            'all_photos' => $photos ? implode(', ', $photos) : null,
            'photo_count' => count($photos),
            'updatable' => false,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Who lives there now, from the "Current household" block: how many,
     * their ages, genders and occupation, as the advertiser states them
     * ("3 Females, 3 Males", "22 to 31", "Professionals"). Nobody's name.
     *
     * @return array{count:?int, ages:?string, gender:?string, occupation:?string, smokers:?string, pets:?string}|null
     */
    protected function household(array $lines): ?array
    {
        $start = array_search('Current household', $lines, true);
        if ($start === false) {
            return null;
        }
        $block = [];
        foreach (array_slice($lines, $start + 1, 30) as $line) {
            if (preg_match('/^New (flat|house)mate preferences$/i', $line)) {
                break;
            }
            $block[] = $line;
        }

        $count = $this->intAfter($block, 'housemates') ?? $this->intAfter($block, 'flatmates');
        $out = [
            'count' => $count,
            'ages' => $this->valueAfter($block, 'Ages') ?? $this->valueAfter($block, 'Age'),
            'gender' => $this->valueAfter($block, 'Gender'),
            'occupation' => $this->valueAfter($block, 'Occupation'),
            'smokers' => $this->valueAfter($block, 'Smoker?'),
            'pets' => $this->valueAfter($block, 'Any pets?'),
        ];

        return array_filter($out, fn ($v) => $v !== null && $v !== '') ? $out : null;
    }

    /**
     * The page is a flat sequence of labels and values once the markup is
     * removed, which is what makes it readable without a DOM library.
     *
     * @return array<int, string>
     */
    protected function textLines(string $html): array
    {
        $html = preg_replace('/<script\b.*?<\/script>/is', ' ', $html);
        $html = preg_replace('/<style\b.*?<\/style>/is', ' ', $html);
        $text = html_entity_decode(strip_tags($html, "\n"), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/<[^>]*>/', "\n", $text);

        $lines = [];
        foreach (preg_split('/\R/u', $text) as $line) {
            $line = trim(preg_replace('/[ \t]+/u', ' ', $line));
            if ($line !== '' && (! $lines || end($lines) !== $line)) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /** The value printed immediately after a label. */
    protected function valueAfter(array $lines, string $label): ?string
    {
        foreach ($lines as $i => $line) {
            if (strcasecmp($line, $label) === 0) {
                return $lines[$i + 1] ?? null;
            }
        }

        return null;
    }

    protected function intAfter(array $lines, string $label): ?int
    {
        $value = $this->valueAfter($lines, $label);

        return is_numeric($value) ? (int) $value : null;
    }

    protected function moneyAfter(array $lines, string $label): ?float
    {
        $value = (string) $this->valueAfter($lines, $label);
        $value = preg_replace('/[^0-9.]/', '', $value);

        return is_numeric($value) ? (float) $value : null;
    }

    protected function price(array $lines, string $html = ''): ?float
    {
        // The advert's own figure, from the page's summary ("All Saints :
        // £170 pw (inc bills)") or its price list. The text lines can miss
        // it and pick up a "from £170" belonging to another advert, and a
        // weekly rent then showed as monthly.
        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // "£982-£1,046 pcm" for several rooms: the lower end, the one we can
        // honour, as for every other source.
        $amount = '£\s?([\d,]+(?:\.\d+)?)(?:\s*[-–]\s*£\s?[\d,]+(?:\.\d+)?)?[\s\x{00A0}]*p(cm|w)\b';
        foreach ([
            '/<meta[^>]+property="og:description"[^>]+content="[^"]*?' . $amount . '/iu',
            '/class="feature-list__key">\s*' . $amount . '/iu',
        ] as $pattern) {
            if ($decoded !== '' && preg_match($pattern, $decoded, $m)) {
                return self::monthly((float) str_replace(',', '', $m[1]), $m[2]);
            }
        }

        foreach ($lines as $line) {
            // "£839 pcm" and "£195 pw" both appear; weekly is converted.
            if (preg_match('/£\s?([\d,]+(?:\.\d+)?)[\s\x{00A0}]*p(cm|w)\b/iu', $line, $m)) {
                return self::monthly((float) str_replace(',', '', $m[1]), $m[2]);
            }
        }

        return null;
    }

    /** Per calendar month: a weekly rent is x 52 / 12. */
    public static function monthly(float $amount, string $period): float
    {
        return strtolower($period) === 'w' || strtolower($period) === 'pw'
            ? round($amount * 52 / 12, 2)
            : $amount;
    }

    /** "double" / "single" / "twin", printed just after the price. */
    protected function roomType(array $lines): ?string
    {
        foreach ($lines as $i => $line) {
            if (preg_match('/£\s?[\d,]+(?:\.\d+)?\s*p(?:cm|w)/i', $line)) {
                $next = strtolower(trim((string) ($lines[$i + 1] ?? '')));
                if (in_array($next, ['double', 'single', 'twin', 'ensuite', 'en suite'], true)) {
                    return str_replace(' ', '', $next);
                }
            }
        }

        return null;
    }

    protected function title(string $html): ?string
    {
        // og:title first: the <title> tag sometimes carries the advertiser's
        // emoji mangled ("ðŸ%8F Poplar Single Room").
        if (preg_match('/<meta[^>]+property="og:title"[^>]+content="([^"]+)"/i', $html, $og)) {
            $title = trim(html_entity_decode($og[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($title !== '' && ! preg_match('/Ã|ðŸ|â€/u', $title)) {
                return $title;
            }
        }

        if (! preg_match('/<title>(.*?)<\/title>/is', $html, $m)) {
            return null;
        }

        $title = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // "'Room name' Room to Rent from SpareRoom"
        $title = preg_replace('/\s*Room to Rent from SpareRoom\s*$/i', '', $title);
        $title = trim($title, " \t\n\r\0\x0B'\"");

        return $title !== '' ? $title : null;
    }

    /**
     * The advert body, which sits between the reference number and the
     * "Flat share" heading that starts the structured section.
     */
    protected function description(array $lines): ?string
    {
        $start = null;
        $end = null;

        foreach ($lines as $i => $line) {
            if ($start === null && preg_match('/^Ad reference no\.?$/i', $line)) {
                $start = $i + 2;
            }
            if ($start !== null && $i > $start && preg_match('/^(Flat share|Whole property|Area info)$/i', $line)) {
                $end = $i;
                break;
            }
        }

        if ($start === null) {
            return null;
        }

        $body = array_slice($lines, $start, ($end ?? $start + 60) - $start);
        $body = array_filter($body, fn ($l) => ! in_array($l, ['Free to', 'contact', 'Share', 'Hide', 'Save', '⸻'], true));

        $text = trim(implode(' ', $body));

        return $text !== '' ? mb_substr($text, 0, 4000) : null;
    }

    /**
     * The advert's own photographs, from SpareRoom's image host.
     *
     * Cards were falling back to a placeholder because nothing was collected
     * here. The page serves the same photo at several sizes; only the large
     * ones are kept, in the order they appear, so the first is the one the
     * advertiser chose as their main image.
     *
     * @return array<int, string>
     */
    protected function photos(string $html): array
    {
        preg_match_all(
            '#https://photos\d*\.spareroom\.co\.uk/images/flatshare/listings/large/[^\s"\'<>]+\.(?:jpe?g|png|webp)#i',
            $html,
            $m
        );

        $photos = [];
        foreach ($m[0] ?? [] as $url) {
            if (! in_array($url, $photos, true)) {
                $photos[] = $url;
            }
        }

        // If only thumbnails are present, take those rather than none.
        if (! $photos && preg_match_all(
            '#https://photos\d*\.spareroom\.co\.uk/images/flatshare/listings/[^\s"\'<>]+\.(?:jpe?g|png|webp)#i',
            $html,
            $m2
        )) {
            $photos = array_values(array_unique($m2[0]));
        }

        return array_slice($photos, 0, 12);
    }

    /**
     * Where the room is, in words. The structured "London N15" line is
     * preferred; failing that the title or the outcode, because "Location not
     * specified" on a card that does have a location is just a worse card.
     */
    protected function location(array $lines, string $html = ''): ?string
    {
        // The advert's own key features: "Flat share | All Saints | E14 Area
        // info | Langdon Park Station". The area name is what agents say.
        $key = $this->keyFeatures($lines);
        if ($key['area']) {
            return $key['area'];
        }

        foreach ($lines as $line) {
            if (preg_match('/^London\s+([A-Z]{1,2}\d{1,2}[A-Z]?)$/', $line, $m)) {
                return 'London ' . $m[1];
            }
        }

        // Failing an area name, the station SpareRoom prints next to the map.
        if ($key['station']) {
            return $key['station'];
        }

        if ($outcode = $this->outcode($html)) {
            return 'London ' . $outcode;
        }

        // A district in this advert's own title is trustworthy; one found
        // anywhere else on the page is not, and guessing produced rooms
        // labelled with a district four miles from where they are.
        $title = $lines[0] ?? '';
        if (preg_match('/\b([A-Z]{1,2}\d{1,2}[A-Z]?)\b/', (string) $title, $m)
            && in_array(strtoupper(preg_replace('/\d.*/', '', $m[1])), [
                'E', 'EC', 'N', 'NW', 'SE', 'SW', 'W', 'WC', 'BR', 'CR', 'DA',
                'EN', 'HA', 'IG', 'KT', 'RM', 'SM', 'TW', 'UB', 'WD',
            ], true)) {
            return 'London ' . strtoupper($m[1]);
        }

        return null;
    }

    /**
     * The key-features block at the top of the advert: property type, area,
     * "E14 Area info" (district and link on one line, or "Area info" alone),
     * then the nearest station. Only this advert's own, never the panels of
     * other adverts further down.
     *
     * @return array{area: ?string, outcode: ?string, station: ?string}
     */
    public function keyFeatures(array $lines): array
    {
        $out = ['area' => null, 'outcode' => null, 'station' => null];
        foreach (array_slice($lines, 0, 80) as $i => $line) {
            if (! preg_match('/^(?:([A-Z]{1,2}\d{1,2}[A-Z]?)\s+)?Area info$/i', $line, $m)) {
                continue;
            }
            $out['outcode'] = ! empty($m[1]) ? strtoupper($m[1]) : null;
            $station = preg_replace('/\s+Station$/i', '', trim((string) ($lines[$i + 1] ?? '')));
            if ($station !== '' && ! str_contains(strtolower($station), 'tube map')) {
                $out['station'] = $station;
            }
            // The line before is the area, unless it is the district alone
            // (then the area is one further up) or the property type.
            $j = $i - 1;
            if (isset($lines[$j]) && preg_match('/^[A-Z]{1,2}\d{1,2}[A-Z]?$/', $lines[$j])) {
                $out['outcode'] ??= $lines[$j];
                $j--;
            }
            $area = trim((string) ($lines[$j] ?? ''));
            if ($area !== '' && mb_strlen($area) <= 40
                && ! preg_match('/share$|^whole|^studio|bed (flat|house)|^flat$|^house$|^room/i', $area)) {
                $out['area'] = $area;
            }
            break;
        }

        return $out;
    }

    protected function outcode(string $html): ?string
    {
        // Only the keywords meta tag, which names this advert's own district.
        // The looser fallback that used to sit here matched the first
        // district-shaped string anywhere on the page — including the
        // "more from this advertiser" panel — so one advert's SE27 was
        // stamped onto rooms in Greenwich, Clapham and Royal Victoria. A
        // wrong postcode is worse than none: the card falls back to the
        // station, which is accurate and is what an agent reads anyway.
        if (preg_match('/flatshare London ([A-Z]{1,2}\d{1,2}[A-Z]?)\b/', $html, $m)) {
            return $m[1];
        }

        return null;
    }

    /** @return array{0: ?float, 1: ?float} */
    protected function coordinates(string $html): array
    {
        $lat = preg_match('/(?:lat|latitude)["\':=\s]+(-?5\d\.\d{3,})/i', $html, $m) ? (float) $m[1] : null;
        $lng = preg_match('/(?:lng|lon|longitude)["\':=\s]+(-?\d{1,2}\.\d{3,})/i', $html, $m2) ? (float) $m2[1] : null;

        // London only, so a coordinate outside a generous box is a mis-parse.
        if ($lat === null || $lng === null || $lat < 51.0 || $lat > 52.0 || $lng < -1.0 || $lng > 0.8) {
            return [null, null];
        }

        return [$lat, $lng];
    }

    protected function availableDate(array $lines): ?string
    {
        $value = $this->valueAfter($lines, 'Availability');

        foreach ([$value, $this->valueAfter($lines, 'Available')] as $candidate) {
            $candidate = trim((string) $candidate);

            if ($candidate === '' || strcasecmp($candidate, 'Available') === 0) {
                continue;
            }

            if (stripos($candidate, 'now') !== false) {
                return now()->toDateString();
            }

            try {
                return \Carbon\Carbon::parse($candidate)->toDateString();
            } catch (\Throwable $e) {
                // Not a date; fall through.
            }
        }

        return null;
    }

    /** The tenant-preference occupation, not the current household's. */
    protected function occupation(array $lines): ?string
    {
        $seenPreferences = false;

        foreach ($lines as $line) {
            if (preg_match('/^New flatmate preferences$/i', $line)) {
                $seenPreferences = true;
            }
            if ($seenPreferences && strcasecmp($line, 'Occupation') === 0) {
                return $this->valueAfter(array_slice($lines, array_search($line, $lines, true)), 'Occupation');
            }
        }

        return null;
    }

    protected function propertyType(string $title, ?string $roomType): string
    {
        $haystack = strtolower($title);

        if (str_contains($haystack, 'studio')) {
            return 'Studio';
        }

        if (preg_match('/\b\d\s?-?\s?bed(?:room)?\s+(?:flat|house|apartment)\b/', $haystack)
            && ! preg_match('/\b(room|ensuite|en suite|double|single)\b/', $haystack)) {
            return 'Flat';
        }

        return 'Room';
    }
}
