<?php

namespace App\Services;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Wildcards: rooms that letting agents advertise on SpareRoom as free to
 * contact. Not our partners, so riskier, but agents want to fill rooms and
 * will usually do a deal with an agency that brings them a tenant.
 *
 * Crawled from the console nightly (market:crawl), never during a page load. Stored in their own table and offered only in Sigou's search,
 * in their own section; they never appear in the listings agents browse.
 *
 * SpareRoom has a "free to contact" filter but no "agents only" one, so the
 * search is free-to-contact rooms around central London and each card's
 * advertiser role decides. Offered: zones 1-3, and a number on the advert
 * (the page shows a "Call" contact method when the advertiser has one). We
 * only record that there is a number, never the number itself.
 */
class MarketListingsService
{
    protected const BASE = 'https://www.spareroom.co.uk';
    protected const AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) '
        . 'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36';

    /** Listings not seen again within this many days are dropped. */
    public const KEEP_DAYS = 3;

    /** Adverts are opened again after this long (to catch a changed room). */
    protected const REREAD_DAYS = 7;

    /** SpareRoom's own zone search; our nearest-station zone must agree. */
    protected const MAX_ZONE = 3;

    /** SpareRoom lists 1,000 results per search, 10 to a page. */
    protected const PAGES_PER_SEARCH = 100;

    /** Rent bands start as below and above this (pcm), then halve as needed. */
    protected const TOP_RENT = 2500;

    /** A single price still over 1,000 is split by what is on offer. */
    protected const KINDS = [
        ['showme_1beds' => '', 'showme_buddyup_properties' => '', 'room_types' => 'double'],
        ['showme_1beds' => '', 'showme_buddyup_properties' => '', 'room_types' => 'single'],
        ['showme_rooms' => ''],
    ];

    protected CookieJar $jar;

    public function __construct(protected SpareRoomAdvertService $adverts)
    {
        $this->jar = new CookieJar();
    }

    /**
     * Every current wildcard, shaped like a feed listing so the same search
     * code applies. Reads the database only.
     */
    public function all(): Collection
    {
        try {
            return DB::table('market_listings')
                ->where('last_seen_at', '>=', now()->subDays(self::KEEP_DAYS))
                ->get()
                ->map(fn ($row) => $this->shape($row))
                ->filter(fn ($p) => $p && self::offered($p))
                ->values();
        } catch (\Throwable $e) {
            // No table yet, or the database is unhappy: no wildcards, no error.
            return collect();
        }
    }

    public function find(string $token): ?array
    {
        $row = DB::table('market_listings')->where('token', $token)->first();

        return $row ? $this->shape($row) : null;
    }

    protected function shape(object $row): ?array
    {
        $data = json_decode((string) $row->data, true);
        if (! is_array($data)) {
            return null;
        }

        return array_merge($data, [
            'id' => 'market-' . $row->token,
            'token' => $row->token,
            'source' => 'spareroom_market',
            'market' => true,
            'agent_name' => $row->agency,
            'agency' => $row->agency,
            'spareroom_id' => $row->spareroom_id,
            'paying' => 'no',
            'last_seen_at' => $row->last_seen_at,
        ]);
    }

    /**
     * Crawl every free-to-contact room in zones 1-3, keep the agents whose
     * advert has a number.
     *
     * SpareRoom shows at most 1,000 results per search, and London has far
     * more, so the search is split into rent bands small enough to be seen
     * whole. Adverts already read in the last week are not opened again, just
     * marked as still listed, so after the first run a night costs little more
     * than the search pages.
     *
     * @return array{bands:int, capped:int, pages:int, cards:int, agents:int, fetched:int, known:int, saved:int, no_number:int, outside:int, failed:int, stopped:?string}
     */
    public function crawl(int $maxPages = 1500, int $maxAdverts = 5000, int $delayMs = 2500, ?callable $progress = null): array
    {
        $stats = ['bands' => 0, 'capped' => 0, 'pages' => 0, 'cards' => 0, 'agents' => 0, 'fetched' => 0, 'known' => 0,
            'saved' => 0, 'no_number' => 0, 'outside' => 0, 'failed' => 0, 'stopped' => null];

        $bands = $this->bands($delayMs, $stats);
        if (! $bands) {
            $stats['stopped'] ??= 'could not start the search';
            return $stats;
        }

        $agentIds = [];
        foreach ($bands as $band) {
            for ($page = 0; $page < self::PAGES_PER_SEARCH && $stats['pages'] < $maxPages; $page++) {
                usleep($delayMs * 1000);
                $html = $this->get('/flatshare/?offset=' . ($page * 10) . '&search_id=' . $band['id'] . '&sort_by=by_day&mode=list');
                if ($html === null) {
                    // Past 1,000 results SpareRoom refuses; anywhere else it is a block.
                    if ($page === 0) {
                        $stats['stopped'] = 'search page refused';
                    }
                    break;
                }

                $cards = $this->cards($html);
                if (! $cards) {
                    break; // past the last page
                }
                $stats['pages']++;
                $stats['cards'] += count($cards);

                foreach ($cards as $id => $card) {
                    // Agents only, and free to contact (an early-bird ad needs a
                    // paid membership for its first week).
                    if ($card['role'] === 'agent' && $card['early_bird'] === '') {
                        $agentIds[$id] = $card;
                    }
                }
                $progress && $progress('page', $stats['pages'], count($agentIds));
            }
            if ($stats['stopped']) {
                break;
            }
        }

        $stats['agents'] = count($agentIds);

        // Read in the last week: still listed, nothing to open.
        $known = DB::table('market_listings')
            ->whereIn('spareroom_id', array_map('strval', array_keys($agentIds)))
            ->where('updated_at', '>=', now()->subDays(self::REREAD_DAYS))
            ->pluck('spareroom_id')->all();
        foreach (array_chunk($known, 500) as $chunk) {
            DB::table('market_listings')->whereIn('spareroom_id', $chunk)->update(['last_seen_at' => now()]);
        }
        $stats['known'] = count($known);
        $toRead = array_diff(array_map('strval', array_keys($agentIds)), $known);

        foreach (array_slice(array_values($toRead), 0, $maxAdverts) as $n => $id) {
            usleep($delayMs * 1000);
            $html = $this->get('/' . $id);
            $stats['fetched']++;

            if ($html === null) {
                $stats['failed']++;
                // A block rather than a bad advert: stop and keep what we have.
                if ($stats['failed'] >= 5 && $stats['failed'] > $stats['saved'] + $stats['no_number'] + $stats['outside']) {
                    $stats['stopped'] = 'too many advert pages refused';
                    break;
                }
                continue;
            }

            $listing = $this->parseAdvert($html, $id, $agentIds[$id]);
            if (! $listing) {
                continue;
            }
            // Stored either way, so tomorrow does not open it again; only
            // offered() rows reach Sigou.
            $this->store($id, $listing);
            match (true) {
                ! $listing['has_phone'] => $stats['no_number']++,
                ! self::inZones($listing) => $stats['outside']++,
                default => $stats['saved']++,
            };
            $progress && $progress('advert', $n + 1, $stats['saved']);
        }

        // Only prune after a crawl that worked, so a blocked run cannot empty
        // the section.
        if (! $stats['stopped'] && $stats['pages'] > 0) {
            DB::table('market_listings')->where('last_seen_at', '<', now()->subDays(self::KEEP_DAYS))->delete();
        }

        return $stats;
    }

    /** Offered to agents: a number on the advert, zones 1-3. */
    public static function offered(array $p): bool
    {
        return ! empty($p['has_phone']) && self::inZones($p);
    }

    /** SpareRoom placed it in zones 1-3; unless its nearest station says otherwise. */
    protected static function inZones(array $p): bool
    {
        return ! is_numeric($p['zone'] ?? null) || (int) $p['zone'] <= self::MAX_ZONE;
    }

    /**
     * Searches that each fit in SpareRoom's 1,000 results: rent bands, halved
     * while full, down to a single price; a single price still full is split
     * into double rooms, single rooms and whole flats. Anything still full
     * after that is taken as it is (counted as capped).
     *
     * @return array<int, array{id:string, min:int, max:?int, full:bool}>
     */
    protected function bands(int $delayMs, array &$stats): array
    {
        $todo = [[0, self::TOP_RENT, []], [self::TOP_RENT, null, []]];
        $out = [];

        while ($todo) {
            [$min, $max, $kind] = array_shift($todo);
            usleep($delayMs * 1000);
            $search = $this->startSearch($min, $max, $kind);
            if (! $search) {
                $stats['stopped'] = 'could not start the search';
                return [];
            }

            if ($search['full'] && ! $kind) {
                if ($max === null) {
                    array_unshift($todo, [$min, $min * 2, []], [$min * 2, null, []]);
                    continue;
                }
                if ($max - $min > 1) {
                    $mid = intdiv($min + $max, 2);
                    array_unshift($todo, [$min, $mid, []], [$mid, $max, []]);
                    continue;
                }
                foreach (array_reverse(self::KINDS) as $k) {
                    array_unshift($todo, [$min, $max, $k]);
                }
                continue;
            }

            if ($search['full']) {
                $stats['capped']++;
                Log::info('Market search still over 1,000 results', ['min' => $min, 'max' => $max, 'kind' => $kind]);
            }
            $out[] = ['id' => $search['id'], 'min' => $min, 'max' => $max, 'full' => $search['full']];
        }
        $stats['bands'] = count($out);

        return $out;
    }

    /**
     * SpareRoom's search is a form post that redirects to ?search_id=. Rent is
     * per calendar month; SpareRoom's max is inclusive, so a band's top is
     * one pound under the next band's bottom.
     *
     * @return array{id:string, full:bool}|null
     */
    protected function startSearch(int $minRent = 0, ?int $maxRent = null, array $kind = []): ?array
    {
        try {
            $response = Http::withHeaders(['User-Agent' => self::AGENT])
                ->withOptions(['cookies' => $this->jar, 'allow_redirects' => ['track_redirects' => true]])
                ->timeout(25)
                ->asForm()
                ->post(self::BASE . '/flatshare/search.pl', array_merge([
                    'flatshare_type' => 'offered',
                    'location_type' => 'zone',
                    'min_zone' => 1,
                    'max_zone' => self::MAX_ZONE,
                    'showme_rooms' => 'Y',
                    'showme_1beds' => 'Y',
                    'showme_buddyup_properties' => 'Y',
                    'free_to_contact' => 'Y',
                    'min_rent' => $minRent ?: '',
                    'max_rent' => $maxRent !== null ? $maxRent - 1 : '',
                    'per' => 'pcm',
                    'posted_by' => '',
                    'searchtype' => 'advanced',
                    'action' => 'search',
                ], $kind));
        } catch (\Throwable $e) {
            Log::warning('Market search failed to start', ['error' => $e->getMessage()]);
            return null;
        }

        $id = null;
        $history = (array) $response->header('X-Guzzle-Redirect-History');
        foreach (array_merge($history, [$response->effectiveUri()?->__toString() ?? '']) as $url) {
            if (preg_match('/search_id=(\d+)/', (string) $url, $m)) {
                $id = $m[1];
                break;
            }
        }
        $body = $response->body();
        if (! $id && preg_match('/search_id=(\d+)/', $body, $m)) {
            $id = $m[1];
        }
        if (! $id) {
            return null;
        }

        return ['id' => $id, 'full' => self::isFull($body)];
    }

    /** "Showing 1-10 of 1000+ results": more than one search can show. */
    public static function isFull(string $html): bool
    {
        $text = preg_replace('/\s+/', ' ', strip_tags(preg_replace('#<(script|style)\b.*?</\1>#is', ' ', $html)));

        return (bool) preg_match('/of\s*1,?000\+\s*results/i', $text);
    }

    protected function get(string $path): ?string
    {
        try {
            $response = Http::withHeaders(['User-Agent' => self::AGENT])
                ->withOptions(['cookies' => $this->jar])
                ->timeout(25)
                ->get(self::BASE . $path);
        } catch (\Throwable $e) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $body = $response->body();
        // A challenge page instead of SpareRoom.
        if (stripos($body, 'captcha') !== false && stripos($body, 'data-listing-id') === false && stripos($body, 'Ad reference') === false) {
            return null;
        }

        return $body;
    }

    /**
     * The result cards on a search page: advertiser role and early-bird flag
     * are data attributes on each card.
     *
     * @return array<string, array{role:string, early_bird:string}>
     */
    public function cards(string $html): array
    {
        $out = [];
        if (preg_match_all('/<[^>]*data-listing-id="(\d+)"[^>]*>/', $html, $tags)) {
            foreach ($tags[0] as $i => $tag) {
                if (! preg_match('/data-listing-advertiser-role="([^"]*)"/', $tag, $role)) {
                    continue;
                }
                preg_match('/data-listing-early-bird="([^"]*)"/', $tag, $early);
                preg_match('/data-listing-neighbourhood="([^"]*)"/', $tag, $area);
                preg_match('/data-listing-postcode="([^"]*)"/', $tag, $postcode);
                $out[$tags[1][$i]] = [
                    'role' => strtolower(trim($role[1])),
                    'early_bird' => trim($early[1] ?? ''),
                    'neighbourhood' => html_entity_decode(trim($area[1] ?? ''), ENT_QUOTES | ENT_HTML5),
                    'postcode' => strtoupper(trim($postcode[1] ?? '')),
                ];
            }
        }

        return $out;
    }

    /** The advert page, parsed by the same code as our direct suppliers. */
    public function parseAdvert(string $html, string $id, array $card = []): ?array
    {
        $agency = $this->agency($html);
        $listing = $this->adverts->parse($html, $id, ['name' => $agency]);
        if (! $listing) {
            return null;
        }

        // The card knows the area and postcode district when the page's own
        // layout does not give them up.
        $listing['location'] = $listing['location'] ?: (($card['neighbourhood'] ?? '') ?: null);
        $listing['postcode'] = $listing['postcode'] ?: (($card['postcode'] ?? '') ?: null);

        // Agent adverts keep their text in a "detaildesc" block the line-based
        // parser does not reach.
        if (empty($listing['description']) && preg_match('#class="[^"]*detaildesc[^"]*"[^>]*>(.*?)</(p|div)>#is', $html, $m)) {
            $text = html_entity_decode(preg_replace('/<br\s*\/?>/i', "\n", $m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $listing['description'] = trim(preg_replace("/[ \t]+/", ' ', strip_tags($text))) ?: null;
        }

        $listing['agency'] = $agency;
        $listing['has_phone'] = $this->hasPhone($html);
        // Rooms and their stations/journey times, like the feed.
        try {
            $listing = app(TransportIndex::class)->annotate($listing);
        } catch (\Throwable $e) {
            // Unannotated is still searchable by area and price.
        }

        return $listing;
    }

    /** "In a hurry? Show interest and we will send Marble Lettings your profile". */
    public function agency(string $html): ?string
    {
        // strip_tags gives up inside SpareRoom's inline scripts, so drop those
        // first and turn tags into spaces ourselves.
        $text = preg_replace('#<(script|style)\b.*?</\1>#is', ' ', $html);
        $text = html_entity_decode(preg_replace('/<[^>]+>/', ' ', (string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\s\x{00A0}]+/u', ' ', $text);
        if (preg_match('/we will send (.{2,80}?) your profile/iu', $text, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /**
     * Whether the advertiser takes calls: SpareRoom lists "Call" among the
     * contact methods (and as a tab) only when there is a number. The support
     * number in every page's footer is not it.
     */
    public function hasPhone(string $html): bool
    {
        return (bool) preg_match('/contact_methods__li[^"]*\bphoneadvertiser\b|fa-phone page-tabs__icon/', $html);
    }

    protected function store(string $id, array $listing): void
    {
        $now = now();
        $existing = DB::table('market_listings')->where('spareroom_id', $id)->first();

        $row = [
            'agency' => $listing['agency'] ?? null,
            'data' => json_encode($listing),
            'last_seen_at' => $now,
            'updated_at' => $now,
        ];

        if ($existing) {
            DB::table('market_listings')->where('id', $existing->id)->update($row);
        } else {
            DB::table('market_listings')->insert($row + [
                'spareroom_id' => $id,
                'token' => self::tokenFor($id),
                'first_seen_at' => $now,
                'created_at' => $now,
            ]);
        }
    }

    /** Opaque, stable, and not the advert number. */
    public static function tokenFor(string $spareroomId): string
    {
        return substr(hash_hmac('sha256', 'market:' . $spareroomId, (string) config('app.key')), 0, 20);
    }
}
