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
            'expect' => ['ensuite_only' => true, 'near_landmark' => 'bond', 'minutes_from_landmark' => 20],
        ],
        [
            'q' => 'something up to zone 3 up to 700 a month',
            'expect' => ['max_price' => 700, 'radius_miles' => '~'],
        ],
        [
            'q' => 'studio in Canary Wharf under 1500',
            'expect' => ['property_types' => ['studio'], 'max_price' => 1500, 'near_landmark' => 'canary'],
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
            'expect' => ['property_types' => ['full_property'], 'location' => 'wapping', 'min_bedrooms' => 2],
        ],
        [
            'q' => 'anything cheap for a couple near Stratford',
            'expect' => ['couples' => true, 'near_landmark' => 'stratford'],
        ],
        [
            'q' => 'double room zone 1 or 2 max 900, must have own bathroom',
            'expect' => ['ensuite_only' => true, 'max_price' => 900, 'property_types' => ['rooms']],
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
                } else {
                    if (! is_string($got) || ! str_contains(strtolower($got), strtolower((string) $want))) {
                        $misses[] = "{$key}=" . var_export($got, true);
                    }
                }
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
