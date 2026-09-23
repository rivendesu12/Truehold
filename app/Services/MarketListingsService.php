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
 * Crawled from the console every couple of days (market:crawl), never during
 * a page load. Stored in their own table and offered only in Sigou's search,
 * in their own section; they never appear in the listings agents browse.
 *
 * SpareRoom has a "free to contact" filter but no "agents only" one, so the
 * search is free-to-contact London and each card's advertiser role decides.
 * Only adverts that offer a phone number are kept: the page shows a "Call"
 * contact method when the advertiser has one. We only record that there is
 * a number, never the number itself (logged out, SpareRoom hides it anyway).
 */
class MarketListingsService
{
    protected const BASE = 'https://www.spareroom.co.uk';
    protected const AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) '
        . 'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36';

    /** Listings not seen again within this many days are dropped. */
    public const KEEP_DAYS = 4;

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
                ->filter(fn ($p) => $p && ! empty($p['has_phone']))
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
     * Crawl free-to-contact London, keep the agents, store them.
     *
     * @return array{pages:int, cards:int, agents:int, saved:int, no_number:int, failed:int, stopped:?string}
     */
    public function crawl(int $maxPages = 40, int $maxAdverts = 250, int $delayMs = 2500, ?callable $progress = null): array
    {
        $stats = ['pages' => 0, 'cards' => 0, 'agents' => 0, 'saved' => 0, 'no_number' => 0, 'failed' => 0, 'stopped' => null];

        $searchId = $this->startSearch();
        if (! $searchId) {
            $stats['stopped'] = 'could not start the search';
            return $stats;
        }

        $agentIds = [];
        for ($page = 0; $page < $maxPages; $page++) {
            usleep($delayMs * 1000);
            $html = $this->get('/flatshare/?offset=' . ($page * 10) . '&search_id=' . $searchId . '&sort_by=by_day&mode=list');
            if ($html === null) {
                $stats['stopped'] = 'search page refused';
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
            $progress && $progress('page', $page + 1, count($agentIds));
        }

        $stats['agents'] = count($agentIds);

        foreach (array_slice(array_keys($agentIds), 0, $maxAdverts) as $n => $id) {
            $card = $agentIds[$id];
            usleep($delayMs * 1000);
            $html = $this->get('/' . $id);

            if ($html === null) {
                $stats['failed']++;
                // A block rather than a bad advert: stop and keep what we have.
                if ($stats['failed'] >= 5 && $stats['failed'] > $stats['saved']) {
                    $stats['stopped'] = 'too many advert pages refused';
                    break;
                }
                continue;
            }

            $listing = $this->parseAdvert($html, (string) $id, $card);
            if ($listing && ! $listing['has_phone']) {
                $stats['no_number']++;
            } elseif ($listing) {
                $this->store((string) $id, $listing);
                $stats['saved']++;
            }
            $progress && $progress('advert', $n + 1, $stats['saved']);
        }

        // Only prune after a crawl that worked, so a blocked run cannot empty
        // the section.
        if ($stats['saved'] > 0 && ! $stats['stopped']) {
            DB::table('market_listings')->where('last_seen_at', '<', now()->subDays(self::KEEP_DAYS))->delete();
        }

        return $stats;
    }

    /** SpareRoom's search is a form post that redirects to ?search_id=. */
    protected function startSearch(): ?string
    {
        try {
            $response = Http::withHeaders(['User-Agent' => self::AGENT])
                ->withOptions(['cookies' => $this->jar, 'allow_redirects' => ['track_redirects' => true]])
                ->timeout(25)
                ->asForm()
                ->post(self::BASE . '/flatshare/search.pl', [
                    'flatshare_type' => 'offered',
                    'location_type' => 'area',
                    'search' => 'London',
                    'miles_from_max' => 0,
                    'showme_rooms' => 'Y',
                    'showme_1beds' => 'Y',
                    'showme_buddyup_properties' => 'Y',
                    'free_to_contact' => 'Y',
                    'posted_by' => '',
                    'searchtype' => 'advanced',
                    'action' => 'search',
                ]);
        } catch (\Throwable $e) {
            Log::warning('Market search failed to start', ['error' => $e->getMessage()]);
            return null;
        }

        $history = (array) $response->header('X-Guzzle-Redirect-History');
        foreach (array_merge($history, [$response->effectiveUri()?->__toString() ?? '']) as $url) {
            if (preg_match('/search_id=(\d+)/', (string) $url, $m)) {
                return $m[1];
            }
        }
        if (preg_match('/search_id=(\d+)/', $response->body(), $m)) {
            return $m[1];
        }

        return null;
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
