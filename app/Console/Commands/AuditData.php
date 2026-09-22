<?php

namespace App\Console\Commands;

use App\Services\AgentSearchAssistant;
use App\Services\ScrapedListingsApiService;
use App\Support\PropertyClassifier;
use App\Support\PropertyPhoto;
use App\Support\RoomFacts;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Checks the feed against itself, so problems surface here rather than when an
 * agent runs a search.
 *
 * Three kinds of check, because three kinds of bug kept getting through:
 *
 *  1. Fields the filters rely on, per source. A filter over a column that one
 *     source never fills silently excludes that source — which is how Javier's
 *     E14 en-suites went missing from commission searches for want of an
 *     agency name.
 *  2. Data the feed scrapes but we never map. Those 82 rows carried their
 *     company and a photo folder in their raw data the whole time.
 *  3. Whether each filter's output actually satisfies the filter. Counting
 *     results proves nothing: a search can return plenty and all of it wrong,
 *     as the en-suite filter did.
 */
class AuditData extends Command
{
    protected $signature = 'audit:data {--verbose-rows : list the offending listings}';

    protected $description = 'Audit field coverage, unmapped source data and filter correctness';

    /** Fields a filter or a card depends on. */
    private const REQUIRED = [
        'title', 'price', 'latitude', 'longitude', 'zone', 'nearest_station',
        'walk_minutes', 'station_lines', 'agent_name', 'link',
    ];

    /** Fields that drive a filter but are legitimately sparse. */
    private const OPTIONAL = [
        'bills_included', 'couples_ok', 'smoking_ok', 'garden', 'parking',
        'furnishings', 'available_date', 'min_term', 'deposit', 'total_rooms',
        'room1_type', 'pref_occupation', 'journey_minutes',
    ];

    protected int $problems = 0;

    public function handle(ScrapedListingsApiService $feed, AgentSearchAssistant $assistant): int
    {
        $all = $feed->getAllProperties();

        if ($all->isEmpty()) {
            $this->error('The feed returned nothing.');
            return self::FAILURE;
        }

        $this->info("Auditing {$all->count()} listings.");
        $this->newLine();

        $this->auditRequiredFields($all);
        $this->auditCoverageBySource($all);
        $this->auditUnmappedRawData($all);
        $this->auditFilterCorrectness($all, $assistant);
        $this->auditIntegrity($all);

        $this->newLine();

        if ($this->problems === 0) {
            $this->info('No problems found.');
            return self::SUCCESS;
        }

        $this->warn("{$this->problems} problem(s) found.");

        return self::FAILURE;
    }

    protected function flag(string $message): void
    {
        $this->problems++;
        $this->line('  <fg=red>x</> ' . $message);
    }

    protected function ok(string $message): void
    {
        $this->line('  <fg=green>v</> ' . $message);
    }

    /** A field every listing needs, missing anywhere, is a bug. */
    protected function auditRequiredFields(Collection $all): void
    {
        $this->line('<options=bold>Fields every listing needs</>');

        foreach (self::REQUIRED as $field) {
            $missing = $all->filter(fn ($p) => $this->isBlank($p[$field] ?? null));

            if ($missing->isEmpty()) {
                $this->ok("{$field}: all " . $all->count());
                continue;
            }

            $bySource = $missing->pluck('source')->countBy()
                ->map(fn ($c, $s) => "{$s}={$c}")->implode(', ');

            $this->flag(sprintf('%s: missing on %d (%s)', $field, $missing->count(), $bySource));

            if ($this->option('verbose-rows')) {
                foreach ($missing->take(3) as $p) {
                    $this->line('      ' . mb_substr((string) ($p['title'] ?? $p['id']), 0, 60));
                }
            }
        }

        $this->newLine();
    }

    /**
     * A source that never fills a column is invisible to any filter over it.
     */
    protected function auditCoverageBySource(Collection $all): void
    {
        $this->line('<options=bold>Filterable fields by source</>');

        $sources = $all->groupBy(fn ($p) => $p['source'] ?? 'unknown');

        foreach (self::OPTIONAL as $field) {
            $blanks = [];

            foreach ($sources as $source => $rows) {
                $filled = $rows->reject(fn ($p) => $this->isBlank($p[$field] ?? null))->count();

                if ($filled === 0) {
                    $blanks[] = "{$source} (all {$rows->count()})";
                }
            }

            if ($blanks) {
                $this->flag(sprintf(
                    '%s: never set for %s — any filter on it excludes those listings entirely',
                    $field,
                    implode(', ', $blanks)
                ));
            }
        }

        if ($this->problems === 0) {
            $this->ok('every source fills every filterable field at least sometimes');
        }

        $this->newLine();
    }

    /** Data the feed scraped that we are throwing away. */
    protected function auditUnmappedRawData(Collection $all): void
    {
        $this->line('<options=bold>Scraped but unmapped</>');

        // Keys we know are either mapped elsewhere or deliberately ignored,
        // the tenant contact details among them.
        $ignore = [
            'tenant', 'tenants\' phone number', 'whatsapp', 'company', 'property',
            'post code', 'postcode', 'folder link', 'room #', 'room type', 'status',
            '1p', '2p', 'bills', 'date available', 'collection date',
            'contract length', 'tube station', 'days empty',
        ];

        $unmapped = [];

        foreach ($all as $property) {
            $raw = $property['raw_row'] ?? null;
            if (is_string($raw)) {
                $raw = json_decode($raw, true);
            }
            if (! is_array($raw)) {
                continue;
            }

            foreach ($raw as $key => $value) {
                $normalised = strtolower(trim((string) $key));

                if ($normalised === '' || in_array($normalised, $ignore, true)) {
                    continue;
                }
                if (str_starts_with($normalised, 'column ')) {
                    continue;
                }
                if ($this->isBlank($value)) {
                    continue;
                }

                $unmapped[$normalised] = ($unmapped[$normalised] ?? 0) + 1;
            }
        }

        if (! $unmapped) {
            $this->ok('nothing useful left unmapped');
        } else {
            arsort($unmapped);
            foreach (array_slice($unmapped, 0, 8, true) as $key => $count) {
                $this->flag("raw data has \"{$key}\" on {$count} rows and we do not read it");
            }
        }

        $this->newLine();
    }

    /**
     * Does each filter's output actually satisfy the filter? Counting results
     * proves nothing; the en-suite filter returned plenty and was wrong.
     */
    protected function auditFilterCorrectness(Collection $all, AgentSearchAssistant $assistant): void
    {
        $this->line('<options=bold>Filters return what they claim</>');

        $method = new \ReflectionMethod($assistant, 'applyPreferences');
        $method->setAccessible(true);
        $apply = fn (array $spec) => $method->invoke($assistant, $all, $spec);

        $checks = [
            'max_price 900' => [
                ['max_price' => 900],
                fn ($p) => is_numeric($p['price'] ?? null) && (float) $p['price'] <= 900,
            ],
            'min_price 1200' => [
                ['min_price' => 1200],
                fn ($p) => is_numeric($p['price'] ?? null) && (float) $p['price'] >= 1200,
            ],
            'max_zone 3' => [
                ['max_zone' => 3],
                fn ($p) => is_numeric($p['zone'] ?? null) && (int) $p['zone'] <= 3,
            ],
            'walk <= 10 min' => [
                ['max_walk_to_station' => 10],
                fn ($p) => is_numeric($p['walk_minutes'] ?? null) && (int) $p['walk_minutes'] <= 10,
            ],
            'ensuite only' => [
                ['ensuite_only' => true],
                fn ($p) => RoomFacts::isEnsuite($p),
            ],
            'studios only' => [
                ['property_types' => ['studio']],
                fn ($p) => PropertyClassifier::bucket($p) === 'studio',
            ],
            'whole properties' => [
                ['property_types' => ['full_property']],
                fn ($p) => PropertyClassifier::bucket($p) === 'full_property',
            ],
            'commission only' => [
                ['commission_only' => true],
                fn ($p) => $assistant->paysCommission($p),
            ],
            'bills included' => [
                ['bills_included' => true],
                fn ($p) => in_array(strtolower(trim((string) ($p['bills_included'] ?? ''))), ['yes', 'all included', 'some', 'included', 'partial'], true),
            ],
            'east London' => [
                ['region' => 'east'],
                fn ($p) => \App\Support\LondonRegion::matches($p, 'east'),
            ],
            'max house size 3' => [
                ['max_house_size' => 3],
                fn ($p) => is_numeric($p['house_size'] ?? $p['total_rooms'] ?? null)
                    && (int) ($p['house_size'] ?? $p['total_rooms']) <= 3,
            ],
            'Jubilee line' => [
                ['lines' => ['Jubilee']],
                fn ($p) => collect($p['station_lines'] ?? [])->contains(fn ($l) => str_contains(strtolower($l), 'jubilee')),
            ],
        ];

        foreach ($checks as $label => [$spec, $predicate]) {
            $results = $apply($spec);
            $wrong = $results->reject($predicate);

            if ($results->isEmpty()) {
                $this->flag("{$label}: returns nothing at all — check the column is populated");
                continue;
            }

            if ($wrong->isNotEmpty()) {
                $this->flag(sprintf(
                    '%s: %d of %d results do NOT satisfy it (e.g. "%s")',
                    $label,
                    $wrong->count(),
                    $results->count(),
                    mb_substr((string) ($wrong->first()['title'] ?? '?'), 0, 44)
                ));
                continue;
            }

            $this->ok("{$label}: all {$results->count()} results satisfy it");
        }

        $this->newLine();
    }

    /** Things that should never be true of the merged feed. */
    protected function auditIntegrity(Collection $all): void
    {
        $this->line('<options=bold>Integrity</>');

        $ids = $all->pluck('id')->filter();
        if ($ids->count() !== $ids->unique()->count()) {
            $this->flag('duplicate listing ids: ' . ($ids->count() - $ids->unique()->count()));
        } else {
            $this->ok('listing ids are unique');
        }

        $sameAdvert = $all
            ->map(fn ($p) => preg_match('/(?:^|:)(\d{6,9})$/', (string) ($p['external_ref'] ?? ''), $m) ? $m[1] : null)
            ->filter()
            ->countBy()
            ->filter(fn ($c) => $c > 1);

        if ($sameAdvert->isNotEmpty()) {
            $this->flag('same source advert listed twice: ' . $sameAdvert->keys()->take(4)->implode(', '));
        } else {
            $this->ok('no advert appears twice');
        }

        $noPhoto = $all->filter(fn ($p) => PropertyPhoto::best($p) === null);
        $this->line(sprintf(
            '  - %d of %d listings have no photograph (%s)',
            $noPhoto->count(),
            $all->count(),
            $noPhoto->pluck('agent_name')->map(fn ($n) => $n ?: 'unattributed')
                ->countBy()->sortDesc()->take(3)->map(fn ($c, $n) => "{$n}={$c}")->implode(', ') ?: 'none'
        ));

        $offMap = $all->filter(fn ($p) => $this->isBlank($p['latitude'] ?? null));
        if ($offMap->isNotEmpty()) {
            $this->flag($offMap->count() . ' listings cannot appear on the map');
        } else {
            $this->ok('every listing has coordinates');
        }

        $silly = $all->filter(fn ($p) => is_numeric($p['price'] ?? null)
            && ((float) $p['price'] < 200 || (float) $p['price'] > 10000));
        if ($silly->isNotEmpty()) {
            $this->flag($silly->count() . ' listings have an implausible price (e.g. GBP '
                . $silly->first()['price'] . ' — "' . mb_substr((string) $silly->first()['title'], 0, 34) . '")');
        } else {
            $this->ok('all prices are plausible');
        }
    }

    protected function isBlank($value): bool
    {
        if (is_array($value)) {
            return $value === [];
        }

        return $value === null || trim((string) $value) === '' || strcasecmp((string) $value, 'N/A') === 0;
    }
}
