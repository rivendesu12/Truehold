<?php

namespace App\Console\Commands;

use App\Services\AgentSearchAssistant;
use App\Services\ScrapedListingsApiService;
use Illuminate\Console\Command;

/**
 * Checks whether the configured model actually understands the briefs agents
 * use, so "is the cheap model good enough" is measured rather than assumed.
 *
 *   php artisan assistant:test
 *   ASSISTANT_PROVIDER=openai OPENAI_MODEL=gpt-5.6-luna php artisan assistant:test
 */
class TestAgentAssistant extends Command
{
    protected $signature = 'assistant:test
        {--show-spec : print the full parsed spec for each case}
        {--sigou : print what Sigou says for each case}
        {--compare : also read every brief without the Sigou persona and report any filter that changed}
        {--only= : run only these sections, comma-separated: searches,followups,agreements,invoices,wifi,chat}
        {--quick : searches: only the first 10 briefs (the wording traps and a few classics)}';

    /**
     * Each case is a paid model call, so test what changed, not everything:
     *   --only=invoices          after touching invoices (5 calls)
     *   --only=searches --quick  a smoke test of the search (10 calls)
     *   (no options)             everything once (~64 calls, about $0.06)
     *   --compare                everything with and without Sigou (~$0.10);
     *                            only after changing the search rules.
     */
    private function section(string $name, array $cases): array
    {
        $only = array_filter(array_map('trim', explode(',', (string) $this->option('only'))));
        if ($only && ! in_array($name, $only, true)) {
            return [];
        }

        return $name === 'searches' && $this->option('quick') ? array_slice($cases, 0, 10) : $cases;
    }

    /** Not searches: Sigou should answer these and run nothing. */
    private const CHAT_CASES = [
        'hey sigou how are you',
        'are you vaping again?',
        'who is the best agent in the office',
        'thanks bro',
    ];

    /**
     * Follow-ups: the first brief, then what the agent types next. A
     * refinement keeps everything else; a new client starts over.
     */
    private const FOLLOWUP_CASES = [
        ['first' => 'something in east london up to zone 3', 'then' => 'max 650',
            'refines' => true, 'expect' => ['region' => 'east', 'max_zone' => 3, 'max_price' => 650], 'expect_none' => true],
        ['first' => 'ensuite near bond street under 1000', 'then' => 'what about 1200',
            'refines' => true, 'expect' => ['ensuite_only' => true, 'place' => 'bond', 'max_price' => 1200], 'expect_none' => true],
        ['first' => 'east london zone 3 max 800', 'then' => 'drop the zone',
            'refines' => true, 'expect' => ['max_zone' => 'none', 'region' => 'east', 'max_price' => 800]],
        ['first' => 'double room in stratford under 900', 'then' => 'and couples ok',
            'refines' => true, 'expect' => ['couples' => true, 'place' => 'stratford', 'max_price' => 900], 'expect_none' => true],
        ['first' => 'studio in canary wharf under 1500', 'then' => 'new client: couple looking for a double in brixton under 1300',
            'refines' => false, 'expect' => ['couples' => true, 'place' => 'brixton', 'max_price' => 1300], 'expect_none' => true],
        ['first' => 'rooms in E14 under 1000', 'then' => 'another one, whole flat in wapping two bedrooms minimum',
            'refines' => false, 'expect' => ['max_price' => 'none', 'min_bedrooms' => 2, 'place' => 'wapping'], 'expect_none' => true],
    ];

    /**
     * Sourcing agreements: asked for in plain words, details pulled out, the
     * gaps left empty for Sigou to ask about. `pending` is what an earlier
     * message already collected.
     */
    private const AGREEMENT_CASES = [
        ['q' => 'malaka make me a sourcing agreement for Maria Lopez, 250',
            'expect' => ['client_name' => 'Maria Lopez', 'fee' => 250, 'template_only' => false]],
        ['q' => 'contract for john smith, he pays cash',
            'expect' => ['client_name' => 'john smith', 'fee' => 220]],
        ['q' => 'sourcing agreement for Anna Kowalska, bank transfer, sign as Alex',
            'expect' => ['client_name' => 'Anna Kowalska', 'fee' => 250, 'sign_as' => 'Alex']],
        ['q' => 'make me a sourcing agreement for this client',
            'expect' => ['client_name' => null, 'fee' => null]],
        ['q' => 'send me the blank sourcing agreement template',
            'expect' => ['template_only' => true]],
        ['q' => 'Anna Nowak, cash', 'pending' => ['client_name' => null, 'fee' => null, 'sign_as' => 'Giacomo'],
            'expect' => ['client_name' => 'Anna Nowak', 'fee' => 220]],
    ];

    /** Sourcing-fee invoices. */
    private const INVOICE_CASES = [
        ['q' => 'malaka invoice for Alexander Marcano 250', 'expect' => ['client_name' => 'Alexander Marcano', 'amount' => 250, 'paid' => true]],
        ['q' => 'make an invoice for maria lopez, she paid cash', 'expect' => ['client_name' => 'maria lopez', 'amount' => 220]],
        ['q' => 'invoice for John Smith 250, he hasnt paid yet', 'expect' => ['client_name' => 'John Smith', 'amount' => 250, 'paid' => false]],
        ['q' => 'make me an invoice', 'expect' => ['client_name' => null, 'amount' => null]],
        ['q' => 'Anna Nowak, transfer', 'pending' => ['client_name' => null, 'amount' => null, 'paid' => true],
            'expect' => ['client_name' => 'Anna Nowak', 'amount' => 250]],
    ];

    /** Asking for the office WiFi, in the ways agents actually do. */
    private const WIFI_CASES = [
        'whats the office wifi',
        'client needs the wifi password',
        'malaka give me the internet password for the client',
    ];

    /** Fields that are Sigou talking, not search filters. */
    private const PERSONA_FIELDS = AgentSearchAssistant::NOT_FILTERS;

    protected $description = 'Run real agent briefs through the assistant and check the parsed filters';

    /**
     * Each case lists the expectations that matter. A missing expectation is a
     * miss; extra fields are fine. Cases are the phrasings agents actually use,
     * including the ones needing inference rather than keyword matching.
     */
    private const CASES = [
        // Wording traps. Describing the tenant, or wishing for something, must
        // not remove rooms: "she is a student" once filtered out 124 listings.
        [
            'q' => 'room for my client, she is a student, under 800 in east london',
            'expect' => ['students' => 'none', 'max_price' => 800, 'region' => 'east'],
        ],
        [
            'q' => 'professional guy, works in canary wharf, 28 years old, budget 900',
            'expect' => ['students' => 'none', 'minutes_from_landmark' => 'none', 'radius_miles' => 'none', 'max_price' => 900],
        ],
        [
            'q' => 'double room zone 2 max 950, ideally ensuite and bills included would be nice',
            'expect' => ['ensuite_only' => 'none', 'bills_included' => 'none', 'max_zone' => 2, 'nice_to_have' => ['ensuite', 'bills_included']],
        ],
        [
            'q' => 'she is a nurse, quiet, french, needs something near stratford under 850',
            'expect' => ['students' => 'none', 'place' => 'stratford', 'max_price' => 850],
        ],
        [
            // Needs, not descriptions: these must still filter.
            'q' => 'couple with a cat, he smokes, under 1200',
            'expect' => ['couples' => true, 'pets' => true, 'smokers' => true, 'max_price' => 1200],
        ],
        [
            'q' => 'asap, anything under 750, parking would be a bonus',
            'expect' => ['available_by' => '~', 'parking' => 'none', 'nice_to_have' => ['parking'], 'max_price' => 750],
        ],
        [
            'q' => 'ensuite within 20 minutes of Bond Street',
            'expect' => ['ensuite_only' => true, 'place' => 'bond', 'minutes_from_landmark' => 20],
        ],
        [
            'q' => 'something up to zone 3 up to 700 a month',
            'expect' => ['max_price' => 700, 'max_zone' => 3],
        ],
        [
            'q' => 'studio in Canary Wharf under 1500',
            'expect' => ['property_types' => ['studio'], 'max_price' => 1500, 'place' => 'canary'],
        ],
        [
            // The hard one: the constraint is implied, never stated as a number.
            'q' => 'ensuite room or a room in a max 3 bed flat so they dont share bathroom with many people',
            'expect' => ['max_bedrooms' => 3, 'property_types' => ['rooms']],
        ],
        [
            'q' => 'only give me ones with commission',
            'expect' => ['commission_only' => true],
        ],
        [
            'q' => 'whole flat in Wapping, two bedrooms minimum',
            'expect' => ['property_types' => ['full_property'], 'place' => 'wapping', 'min_bedrooms' => 2],
        ],
        [
            'q' => 'anything cheap for a couple near Stratford',
            'expect' => ['couples' => true, 'place' => 'stratford'],
        ],
        [
            // A real gap in stock, not a logic error: the three en-suites
            // under GBP 900 are in zones 5, 6 and 9, and the cheapest in zone
            // 1-2 is GBP 1,040. What matters is that the agent is told that.
            'q' => 'double room zone 1 or 2 max 900, must have own bathroom',
            'expect' => ['ensuite_only' => true, 'max_price' => 900, 'property_types' => ['rooms']],
            'expect_none' => true,
        ],
        [
            // A journey, not a radius. The destination is an area, not a station.
            'q' => 'client works in the City, needs to be under 40 minutes door to door',
            'expect' => ['place' => 'city', 'minutes_from_landmark' => 40],
        ],
        [
            'q' => 'somewhere on the Jubilee line, budget 800',
            'expect' => ['lines' => 'jubilee', 'max_price' => 800],
        ],
        [
            'q' => 'needs a direct train to Canary Wharf, no changes, up to 1100',
            'expect' => ['direct_only' => true, 'place' => 'canary', 'max_price' => 1100],
        ],
        [
            'q' => 'student at UCL, half an hour max, cheapest you have',
            'expect' => ['place' => 'euston|ucl|kings', 'minutes_from_landmark' => 30, 'sort' => 'cheapest'],
        ],
        [
            // The house size, not the size of a flat being rented.
            'q' => 'max 3 rooms total within 30 min of bond street',
            'expect' => ['max_house_size' => 3, 'place' => 'bond', 'minutes_from_landmark' => 30],
        ],
        [
            'q' => 'budget 900 up to zone 4',
            'expect' => ['max_price' => 900, 'max_zone' => 4],
        ],
        [
            'q' => 'something with good transport to central london around 750',
            'expect' => ['good_transport' => true],
        ],
        [
            'q' => 'all bills included, no deposit, under 800',
            'expect' => ['bills_included' => true, 'no_deposit' => true, 'max_price' => 800],
        ],
        [
            'q' => 'needs to move in this week, anything under 700',
            'expect' => ['available_by' => '~', 'max_price' => 700],
        ],
        [
            'q' => 'short let, 3 months max, room in east london',
            'expect' => ['max_commitment_months' => 3, 'property_types' => ['rooms'], 'region' => 'east'],
        ],
        [
            // "to central London" is a destination, not where they want to
            // live: this used to set region=central and return nothing.
            'q' => 'cheapest ensuite you have with good transport to central london',
            'expect' => ['good_transport' => true, 'ensuite_only' => true, 'sort' => 'cheapest'],
        ],
        [
            // Was returning a double with a shared bathroom, because the advert
            // mentioned "one ensuite room in the house" — a different room.
            'q' => 'give me ensuite up to zone 3, max budget 1000',
            'expect' => ['ensuite_only' => true, 'max_zone' => 3, 'max_price' => 1000],
        ],
        [
            'q' => 'rooms in E14 under 1000',
            'expect' => ['place' => 'e14', 'max_price' => 1000, 'property_types' => ['rooms']],
        ],
        [
            'q' => 'five minutes from Whitechapel station, ensuite',
            'expect' => ['place' => 'whitechapel', 'ensuite_only' => true],
        ],
        [
            'q' => 'anything in south london under 800',
            'expect' => ['region' => 'south', 'max_price' => 800],
        ],
        [
            'q' => 'north west london, ensuite, bills included',
            'expect' => ['region' => 'north_west', 'ensuite_only' => true, 'bills_included' => true],
        ],
        [
            'q' => 'only Banksia properties please',
            'expect' => ['agencies' => 'banksia'],
        ],
        [
            'q' => 'couple looking for a double room, bills in, max 1100',
            'expect' => ['couples' => true, 'room_type' => 'double', 'bills_included' => true, 'max_price' => 1100],
        ],
        [
            'q' => 'professional house, no students, garden would be nice',
            'expect' => ['students' => false, 'garden' => 'none', 'nice_to_have' => ['garden']],
        ],
        [
            'q' => 'anything at all, just show me the cheapest rooms you have',
            'expect' => ['sort' => 'cheapest', 'property_types' => ['rooms']],
        ],
        [
            'q' => 'they smoke, so somewhere that allows it, under 850',
            'expect' => ['smokers' => true, 'max_price' => 850],
        ],
        [
            // The feed holds nine whole properties, one of them a two-bed in
            // zone 5, and none with parking. There is no answer to give, so
            // what matters is that the agent is told why.
            'q' => 'two bed flat with parking in zone 3 or 4, up to 2000',
            'expect' => ['property_types' => ['full_property'], 'parking' => true, 'max_zone' => 4, 'max_price' => 2000, 'min_bedrooms' => 2],
            'expect_none' => true,
        ],
        [
            // We hold nothing on pets: this must be reported, not answered.
            'q' => 'has a dog, needs somewhere pet friendly under 900',
            'expect' => ['pets' => true, 'max_price' => 900],
        ],
        [
            'q' => 'ensuite, 10 minutes walk of a tube max, zone 2',
            'expect' => ['ensuite_only' => true, 'max_walk_to_station' => 10, 'max_zone' => 2],
        ],
        [
            'q' => 'room near Ealing Broadway, 700ish',
            'expect' => ['place' => 'ealing', 'max_price' => '~'],
        ],
        [
            'q' => 'between 600 and 800, furnished, somewhere in south london',
            'expect' => ['min_price' => 600, 'max_price' => 800, 'furnished' => true, 'region' => 'south'],
        ],
    ];

    public function handle(AgentSearchAssistant $assistant, ScrapedListingsApiService $feed): int
    {
        if (! $assistant->isConfigured()) {
            $this->error("Not configured — set the API key for provider '{$assistant->provider()}' first.");
            return self::FAILURE;
        }

        $this->info("Provider: {$assistant->provider()}   Model: {$assistant->model()}");
        $this->newLine();

        $properties = $feed->getAllProperties();
        $locations = $properties->pluck('location')->filter()->unique()->values()->all();

        $passed = 0;
        $rows = [];

        $plainAssistant = $assistant->withoutPersona();
        $plainPassed = 0;
        $changed = [];
        $sigouRows = [];

        foreach ($this->section('searches', self::CASES) as $case) {
            $spec = $assistant->parse($case['q'], $locations);

            if (! $spec) {
                $rows[] = [substr($case['q'], 0, 46), 'PARSE FAILED', '-', '-'];
                continue;
            }

            $misses = $this->misses($case, $spec, $assistant, $properties);

            // A brief read as small talk, or as an agreement, runs no search.
            if (! empty($spec['chit_chat'])) {
                $misses[] = 'taken for chit-chat, no search';
            }
            if (! empty($spec['agreement']['wanted'])) {
                $misses[] = 'taken for a sourcing agreement, no search';
            }
            if (! empty($spec['wifi'])) {
                $misses[] = 'taken for a wifi request, no search';
            }
            if (! empty($spec['invoice']['wanted'])) {
                $misses[] = 'taken for an invoice, no search';
            }

            if ($this->option('compare')) {
                $plain = $plainAssistant->parse($case['q'], $locations);
                if ($plain) {
                    $plainPassed += empty($this->misses($case, $plain, $plainAssistant, $properties)) ? 1 : 0;
                    $diff = $this->filterDiff($plain, $spec);
                    if ($diff) {
                        $changed[] = [substr($case['q'], 0, 46), implode('; ', array_slice($diff, 0, 3))];
                    }
                }
            }

            if ($this->option('sigou')) {
                $sigouRows[] = [substr($case['q'], 0, 40), $spec['sigou'] ?? '', $spec['sigou_found'] ?? '', $spec['sigou_none'] ?? ''];
            }

            $found = $assistant->search($spec, $properties);
            $ok = empty($misses);
            $passed += $ok ? 1 : 0;

            $rows[] = [
                substr($case['q'], 0, 46),
                $ok ? 'pass' : 'MISS',
                $ok ? '' : implode('; ', array_slice($misses, 0, 2)),
                $found['matched'] . ' results',
            ];

            if ($this->option('show-spec')) {
                $this->line('  ' . json_encode($spec, JSON_UNESCAPED_SLASHES));
            }
        }

        $this->table(['brief', 'result', 'what it got wrong', 'matches'], $rows);

        // Follow-ups: does "max 650" keep the rest of the last search?
        $followOk = 0;
        $followRows = [];
        foreach ($this->section('followups', self::FOLLOWUP_CASES) as $case) {
            $first = $assistant->parse($case['first'], $locations);
            $previous = $first ? AgentSearchAssistant::carriedFilters($first) : null;
            $spec = $previous ? $assistant->parse($case['then'], $locations, $previous) : null;

            if (! $spec) {
                $followRows[] = [$case['first'] . ' → ' . $case['then'], 'PARSE FAILED', ''];
                continue;
            }

            $misses = $this->misses(['q' => $case['then']] + $case, $spec, $assistant, $properties);
            if ((bool) ($spec['refines_previous'] ?? false) !== $case['refines']) {
                $misses[] = $case['refines'] ? 'started over instead of refining' : 'kept the old search for a new client';
            }

            $ok = empty($misses);
            $followOk += $ok ? 1 : 0;
            $followRows[] = [substr($case['first'], 0, 34) . ' → ' . substr($case['then'], 0, 30), $ok ? 'pass' : 'MISS', implode('; ', array_slice($misses, 0, 2))];
        }
        $this->newLine();
        $this->table(['follow-up', 'result', 'what it got wrong'], $followRows);

        // Sourcing agreements.
        $dealOk = 0;
        $dealRows = [];
        foreach ($this->section('agreements', self::AGREEMENT_CASES) as $case) {
            $spec = $assistant->parse($case['q'], $locations, null, $case['pending'] ?? null);
            $a = (array) ($spec['agreement'] ?? []);
            $misses = [];
            if (empty($a['wanted'])) {
                $misses[] = 'not recognised as an agreement';
            }
            foreach ($case['expect'] as $key => $want) {
                $got = $a[$key] ?? null;
                $same = is_string($want) ? strcasecmp(trim((string) $got), $want) === 0
                    : (is_numeric($want) ? is_numeric($got) && (float) $got == $want : $got === $want);
                if (! $same) {
                    $misses[] = "{$key}=" . json_encode($got);
                }
            }
            $ok = $spec && empty($misses);
            $dealOk += $ok ? 1 : 0;
            $dealRows[] = [substr($case['q'], 0, 60), $ok ? 'pass' : 'MISS', implode('; ', $misses), $spec['sigou'] ?? ''];
        }
        $this->newLine();
        $this->table(['sourcing agreement', 'result', 'what it got wrong', 'Sigou says'], $dealRows);

        // Invoices.
        $billOk = 0;
        foreach ($this->section('invoices', self::INVOICE_CASES) as $case) {
            $spec = $assistant->parse($case['q'], $locations, null, null, $case['pending'] ?? null);
            $b = (array) ($spec['invoice'] ?? []);
            $misses = empty($b['wanted']) ? ['not recognised as an invoice'] : [];
            foreach ($case['expect'] as $key => $want) {
                $got = $b[$key] ?? null;
                $same = is_string($want) ? strcasecmp(trim((string) $got), $want) === 0
                    : (is_numeric($want) ? is_numeric($got) && (float) $got == $want : $got === $want);
                if (! $same) {
                    $misses[] = "{$key}=" . json_encode($got);
                }
            }
            $billOk += $spec && ! $misses ? 1 : 0;
            if ($misses) {
                $this->warn("invoice MISS: {$case['q']}: " . implode('; ', $misses));
            }
        }

        // The office WiFi.
        $wifiOk = 0;
        foreach ($this->section('wifi', self::WIFI_CASES) as $q) {
            $spec = $assistant->parse($q, $locations);
            $ok = $spec && ! empty($spec['wifi']) && empty($spec['agreement']['wanted']);
            $wifiOk += $ok ? 1 : 0;
            if (! $ok) {
                $this->warn("wifi MISS: {$q}");
            }
        }

        // Small talk: answered in character, nothing searched.
        $chatOk = 0;
        $chatRows = [];
        foreach ($this->section('chat', self::CHAT_CASES) as $q) {
            $spec = $assistant->parse($q, $locations);
            $ok = $spec && ! empty($spec['chit_chat']) && trim((string) ($spec['sigou'] ?? '')) !== '';
            $chatOk += $ok ? 1 : 0;
            $chatRows[] = [$q, $ok ? 'pass' : 'MISS', $spec['sigou'] ?? '(no reply)'];
        }
        $this->newLine();
        $this->table(['small talk', 'result', 'Sigou says'], $chatRows);

        if ($sigouRows) {
            $this->newLine();
            $this->table(['brief', 'first reaction', 'if found', 'if nothing'], $sigouRows);
        }

        $total = count($this->section('searches', self::CASES));
        $this->newLine();
        $this->info("Passed {$passed}/{$total} on {$assistant->provider()}/{$assistant->model()}, follow-ups {$followOk}/" . count($this->section('followups', self::FOLLOWUP_CASES))
            . ", agreements {$dealOk}/" . count($this->section('agreements', self::AGREEMENT_CASES)) . ", invoices {$billOk}/" . count($this->section('invoices', self::INVOICE_CASES))
            . ", wifi {$wifiOk}/" . count($this->section('wifi', self::WIFI_CASES))
            . ", small talk {$chatOk}/" . count($this->section('chat', self::CHAT_CASES)));

        if ($this->option('compare')) {
            $this->info("Without the Sigou persona: {$plainPassed}/{$total}");
            if ($changed) {
                $this->warn(count($changed) . ' briefs read differently with Sigou on:');
                $this->table(['brief', 'filters that changed (without -> with)'], $changed);
            } else {
                $this->info('Every brief produced the same filters with and without Sigou.');
            }
        }

        if ($passed < $total) {
            $this->warn('Not all briefs parsed correctly. Try a stronger model:');
            $this->line('  ASSISTANT_PROVIDER=openai OPENAI_MODEL=gpt-5.6-luna php artisan assistant:test');
            $this->line('  ASSISTANT_PROVIDER=anthropic php artisan assistant:test');
        }

        return $passed === $total && $chatOk === count($this->section('chat', self::CHAT_CASES)) && $followOk === count($this->section('followups', self::FOLLOWUP_CASES))
            && $dealOk === count($this->section('agreements', self::AGREEMENT_CASES)) && $wifiOk === count($this->section('wifi', self::WIFI_CASES))
            && $billOk === count($this->section('invoices', self::INVOICE_CASES))
            ? self::SUCCESS : self::FAILURE;
    }

    /** Filters that differ between two readings, ignoring Sigou's lines. */
    private function filterDiff(array $a, array $b): array
    {
        $diff = [];
        foreach (array_unique(array_merge(array_keys($a), array_keys($b))) as $key) {
            if (in_array($key, self::PERSONA_FIELDS, true)) {
                continue;
            }
            $x = $a[$key] ?? null;
            $y = $b[$key] ?? null;
            if (is_array($x)) { sort($x); }
            if (is_array($y)) { sort($y); }
            if (json_encode($x) !== json_encode($y)) {
                $diff[] = $key . ': ' . json_encode($x) . ' -> ' . json_encode($y);
            }
        }

        return $diff;
    }

    /** What a parsed spec gets wrong against a case's expectations. */
    private function misses(array $case, array $spec, AgentSearchAssistant $assistant, $properties): array
    {
            $misses = [];
            foreach ($case['expect'] as $key => $want) {
                $got = $spec[$key] ?? null;

                // The area may land in either field — both are valid readings
                // and the search handles them equivalently, so accept either.
                if ($key === 'place') {
                    $hay = strtolower(trim(($spec['near_landmark'] ?? '') . ' ' . ($spec['location'] ?? '')));
                    // Several readings can be right: a client "at UCL" is near
                    // Euston or King's Cross, and either lands them correctly.
                    $ok = false;
                    foreach (explode('|', strtolower((string) $want)) as $option) {
                        if ($option !== '' && str_contains($hay, $option)) {
                            $ok = true;
                            break;
                        }
                    }
                    if (! $ok) {
                        $misses[] = "place not recognised (got '" . $hay . "')";
                    }
                    continue;
                }

                if ($want === 'none') {
                    // Must not be set: a description or a wish is not a filter.
                    if (! empty($got)) {
                        $misses[] = "{$key} set to " . json_encode($got) . ' (should be left out)';
                    }
                } elseif ($want === '~') {
                    // Only needs to be present and non-empty.
                    if (empty($got)) {
                        $misses[] = $key;
                    }
                } elseif (is_bool($want)) {
                    if ((bool) $got !== $want) {
                        $misses[] = "{$key}=" . var_export($got, true);
                    }
                } elseif (is_array($want)) {
                    $gotArr = array_map('strtolower', (array) $got);
                    foreach ($want as $needle) {
                        if (! in_array(strtolower($needle), $gotArr, true)) {
                            $misses[] = "{$key} missing {$needle}";
                        }
                    }
                } elseif (is_numeric($want)) {
                    if (! is_numeric($got) || abs((float) $got - (float) $want) > 0.01) {
                        $misses[] = "{$key}=" . var_export($got, true);
                    }
                } elseif (is_array($got)) {
                    $hit = false;
                    foreach ($got as $item) {
                        if (str_contains(strtolower((string) $item), strtolower((string) $want))) {
                            $hit = true;
                            break;
                        }
                    }
                    if (! $hit) {
                        $misses[] = "{$key}=" . json_encode($got);
                    }
                } else {
                    if (! is_string($got) || ! str_contains(strtolower($got), strtolower((string) $want))) {
                        $misses[] = "{$key}=" . var_export($got, true);
                    }
                }
            }

            $found = $assistant->search($spec, $properties);

            // A brief can be parsed perfectly and still have no answer in the
            // stock we hold. That is only acceptable when the search explains
            // itself, which is what expect_none asserts; otherwise returning
            // nothing is a failure from the agent's point of view.
            // Exact matches are "best"; nearby or stretched ones are "other
            // options". Either is an answer; nothing at all, unexplained, is not.
            $offered = $found['matched'] + $found['alternatives']->count();
            if (! empty($case['expect_none'])) {
                if ($offered === 0 && empty($found['why_none'])) {
                    $misses[] = 'zero results with no explanation';
                }
            } elseif (empty($misses) && $offered === 0) {
                $misses[] = 'parsed fine but nothing offered';
            }

            return $misses;
    }
}
