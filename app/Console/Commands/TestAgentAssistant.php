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
    protected $signature = 'assistant:test {--show-spec : print the full parsed spec for each case}';

    protected $description = 'Run real agent briefs through the assistant and check the parsed filters';

    /**
     * Each case lists the expectations that matter. A missing expectation is a
     * miss; extra fields are fine. Cases are the phrasings agents actually use,
     * including the ones needing inference rather than keyword matching.
     */
    private const CASES = [
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
            'q' => 'double room zone 1 or 2 max 900, must have own bathroom',
            'expect' => ['ensuite_only' => true, 'max_price' => 900, 'property_types' => ['rooms']],
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
            'expect' => ['students' => false, 'garden' => true],
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
            'expect' => ['property_types' => ['full_property'], 'parking' => true, 'max_zone' => 4, 'max_price' => 2000],
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

        foreach (self::CASES as $case) {
            $spec = $assistant->parse($case['q'], $locations);

            if (! $spec) {
                $rows[] = [substr($case['q'], 0, 46), 'PARSE FAILED', '-', '-'];
                continue;
            }

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

                if ($want === '~') {
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
            if (! empty($case['expect_none'])) {
                // Either we found something (the relaxation ladder did its
                // job) or we explained why we could not. Both are acceptable;
                // a bare zero is not.
                if ($found['matched'] === 0 && empty($found['why_none'])) {
                    $misses[] = 'zero results with no explanation';
                }
            } elseif (empty($misses) && $found['matched'] === 0) {
                $misses[] = 'parsed fine but zero results';
            }

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

        $total = count(self::CASES);
        $this->newLine();
        $this->info("Passed {$passed}/{$total} on {$assistant->provider()}/{$assistant->model()}");

        if ($passed < $total) {
            $this->warn('Not all briefs parsed correctly. Try a stronger model:');
            $this->line('  ASSISTANT_PROVIDER=openai OPENAI_MODEL=gpt-5.6-luna php artisan assistant:test');
            $this->line('  ASSISTANT_PROVIDER=anthropic php artisan assistant:test');
        }

        return $passed === $total ? self::SUCCESS : self::FAILURE;
    }
}
