<?php

namespace App\Console\Commands;

use App\Services\ScrapedListingsApiService;
use App\Services\TransportIndex;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;

/**
 * Precomputes real public-transport journey times from each listing's nearest
 * station to every destination hub in config/transport.php.
 *
 * Why: "30 minutes from Bond Street" was being answered with a straight-line
 * radius, which is not the same thing and is wrong in both directions — six
 * miles up the Jubilee is half an hour, six miles across south London is an
 * hour and a half. An agent says "30 minutes" to a client either way, so the
 * number behind it has to be a journey time.
 *
 * Station-to-hub rather than listing-to-hub on purpose: a station pair's time
 * is stable for years, while listings churn weekly, so the cache survives feed
 * changes. A listing's door-to-hub time is this plus its walk to the station.
 *
 * TfL's journey planner needs no API key. The run is resumable: known pairs
 * are skipped, and results are flushed to disk as they arrive.
 */
class BuildJourneyTimes extends Command
{
    protected $signature = 'transport:build-journeys
        {--all : Every station in the index, not just those listings sit near}
        {--fresh : Recompute pairs already on file}
        {--limit=0 : Stop after this many stations (for a smoke test)}
        {--concurrency=5 : Requests in flight at once}
        {--rate=45 : Maximum requests per minute}';

    protected $description = 'Precompute journey times from listing stations to London destination hubs';

    protected const PATH = 'app/transport/journeys.json';

    public function handle(TransportIndex $index): int
    {
        if (! $index->isAvailable()) {
            $this->error('No station index. Run transport:build-stations first.');
            return self::FAILURE;
        }

        $hubs = $this->resolveHubs($index);
        if (! $hubs) {
            return self::FAILURE;
        }

        $origins = $this->originStations($index);
        if (! $origins) {
            $this->error('No origin stations to compute from.');
            return self::FAILURE;
        }

        if ($limit = (int) $this->option('limit')) {
            $origins = array_slice($origins, 0, $limit, true);
        }

        $existing = $this->option('fresh') ? [] : $this->load();

        // One flat work list, so concurrency is over pairs and not over hubs
        // within a station — a station whose hubs are all cached costs nothing.
        $pairs = [];
        foreach ($origins as $naptan => $name) {
            foreach ($hubs as $key => $hub) {
                if ($naptan === $hub['naptan']) {
                    $existing[$naptan][$key] = ['m' => 0, 'c' => 0];
                    continue;
                }
                if (isset($existing[$naptan][$key])) {
                    continue;
                }
                $pairs[] = ['from' => $naptan, 'hub' => $key, 'to' => $hub['naptan'], 'name' => $name];
            }
        }

        $this->info(sprintf(
            '%d origin stations x %d hubs — %d pairs to fetch (%d already known).',
            count($origins),
            count($hubs),
            count($pairs),
            count($origins) * count($hubs) - count($pairs)
        ));

        if (! $pairs) {
            $this->save($existing, $hubs);
            $this->info('Nothing to do.');
            return self::SUCCESS;
        }

        $date = now()->next(\Carbon\Carbon::TUESDAY)->format('Ymd');
        $time = (string) config('transport.journeys.arrive_by', '0830');
        $modes = (string) config('transport.journeys.modes');
        $concurrency = max(1, (int) $this->option('concurrency'));
        $rate = max(1, (int) $this->option('rate'));
        $secondsPerRequest = 60 / $rate;

        $bar = $this->output->createProgressBar(count($pairs));
        $bar->start();

        $done = 0;
        $dropped = 0;
        $queue = $pairs;
        $attempts = [];

        while ($queue) {
            $chunk = array_splice($queue, 0, $concurrency);
            $startedAt = microtime(true);

            $responses = Http::pool(function (Pool $pool) use ($chunk, $date, $time, $modes) {
                $requests = [];
                foreach ($chunk as $i => $pair) {
                    $query = ['mode' => $modes, 'date' => $date, 'time' => $time, 'timeIs' => 'Arriving'];
                    if ($key = config('services.tfl.app_key')) {
                        $query['app_key'] = $key;
                    }

                    $requests[] = $pool->as((string) $i)
                        ->timeout(40)
                        ->acceptJson()
                        ->get(
                            "https://api.tfl.gov.uk/Journey/JourneyResults/{$pair['from']}/to/{$pair['to']}",
                            $query
                        );
                }
                return $requests;
            });

            $throttled = 0;

            foreach ($chunk as $i => $pair) {
                $response = $responses[(string) $i] ?? null;
                $id = $pair['from'] . '|' . $pair['hub'];

                // 429 is not "no route" — it is us going too fast. Requeue the
                // pair rather than recording a gap that looks like real data.
                if ($this->isThrottled($response)) {
                    $throttled = max($throttled, $this->retryAfter($response));
                    if (($attempts[$id] = ($attempts[$id] ?? 0) + 1) <= 4) {
                        $queue[] = $pair;
                    } else {
                        $dropped++;
                        $bar->advance();
                    }
                    continue;
                }

                $result = $this->readJourney($response);

                if ($result === null) {
                    // A genuine no-route answer (or a hard error after retries).
                    if (($attempts[$id] = ($attempts[$id] ?? 0) + 1) <= 2 && $this->isRetryable($response)) {
                        $queue[] = $pair;
                        continue;
                    }
                    $dropped++;
                } else {
                    $existing[$pair['from']][$pair['hub']] = $result;
                    $done++;
                }

                $bar->advance();
            }

            // Flush as we go: a run interrupted at pair 2,000 keeps its work.
            if ($done > 0 && $done % 100 < $concurrency) {
                $this->save($existing, $hubs);
            }

            if ($throttled > 0) {
                $bar->setMessage("rate limited, waiting {$throttled}s");
                sleep(min(60, $throttled + 1));
                continue;
            }

            // Hold the average under the per-minute cap. Keyless TfL allows
            // about 50 a minute and answers 429 with "try again in 25 seconds",
            // which costs far more than pacing does.
            $budget = $secondsPerRequest * count($chunk);
            $spent = microtime(true) - $startedAt;
            if ($spent < $budget) {
                usleep((int) round(($budget - $spent) * 1_000_000));
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->save($existing, $hubs);

        $this->info("Saved {$done} journey times." . ($dropped ? " {$dropped} pairs had no route." : ''));

        return self::SUCCESS;
    }

    protected function isThrottled($response): bool
    {
        return $response
            && method_exists($response, 'status')
            && $response->status() === 429;
    }

    /** A 5xx or a connection failure is worth one more go; a 404 is not. */
    protected function isRetryable($response): bool
    {
        if (! $response || ! method_exists($response, 'status')) {
            return true;
        }

        return $response->status() >= 500;
    }

    /**
     * TfL puts the wait in the body ("Try again in 25 seconds") rather than in
     * a Retry-After header, so read both.
     */
    protected function retryAfter($response): int
    {
        if ($response && method_exists($response, 'header')) {
            $header = (int) $response->header('Retry-After');
            if ($header > 0) {
                return min(60, $header);
            }

            if (preg_match('/try again in (\\d+) second/i', (string) $response->body(), $m)) {
                return min(60, (int) $m[1]);
            }
        }

        return 20;
    }

    /**
     * Shortest journey, and how many interchanges it needs. Changes make
     * "direct to Canary Wharf" answerable; walking legs are not changes.
     *
     * @return array{m: int, c: int}|null
     */
    protected function readJourney($response): ?array
    {
        if (! $response || ! method_exists($response, 'successful') || ! $response->successful()) {
            return null;
        }

        $journeys = $response->json('journeys') ?? [];
        $best = null;

        foreach ($journeys as $journey) {
            $minutes = $journey['duration'] ?? null;
            if (! is_numeric($minutes)) {
                continue;
            }

            $rides = 0;
            foreach ($journey['legs'] ?? [] as $leg) {
                if (strtolower((string) ($leg['mode']['name'] ?? '')) !== 'walking') {
                    $rides++;
                }
            }

            $candidate = ['m' => (int) $minutes, 'c' => max(0, $rides - 1)];

            if ($best === null || $candidate['m'] < $best['m']) {
                $best = $candidate;
            }
        }

        return $best;
    }

    /**
     * Hubs from config, with the NaPTAN of their station. A hub whose station
     * name does not exist in the index is a config typo, not a data gap, so
     * say so loudly rather than silently dropping the destination.
     */
    protected function resolveHubs(TransportIndex $index): array
    {
        $resolved = [];
        $missing = [];

        foreach ((array) config('transport.hubs', []) as $key => $hub) {
            $station = $index->stationByName((string) ($hub['station'] ?? ''));

            if (! $station) {
                $missing[] = "{$key} ({$hub['station']})";
                continue;
            }

            $resolved[$key] = [
                'label' => $hub['label'] ?? $station['name'],
                'station' => $station['name'],
                'naptan' => $station['naptan'],
                'zone' => $station['zone'] ?? null,
                'aliases' => array_values($hub['aliases'] ?? []),
            ];
        }

        foreach ($missing as $m) {
            $this->error("Hub station not found in index: {$m}");
        }

        return $missing ? [] : $resolved;
    }

    /**
     * @return array<string, string> naptan => station name
     */
    protected function originStations(TransportIndex $index): array
    {
        if ($this->option('all')) {
            $this->line('Origins: every station in the index.');
            return $index->allNaptans();
        }

        try {
            $listings = app(ScrapedListingsApiService::class)->getAllProperties();
        } catch (\Throwable $e) {
            $this->warn('Feed unavailable (' . $e->getMessage() . '); falling back to every station.');
            return $index->allNaptans();
        }

        $origins = [];
        foreach ($listings as $listing) {
            $naptan = $listing['station_naptan'] ?? null;
            if ($naptan) {
                $origins[$naptan] = $listing['nearest_station'] ?? $naptan;
            }
        }

        $this->line(sprintf('Origins: %d stations the current %d listings sit near.', count($origins), $listings->count()));

        return $origins;
    }

    protected function load(): array
    {
        $path = storage_path(self::PATH);
        if (! is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data['minutes'] ?? null) ? $data['minutes'] : [];
    }

    protected function save(array $minutes, array $hubs): void
    {
        $dir = dirname(storage_path(self::PATH));
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        file_put_contents(
            storage_path(self::PATH),
            json_encode([
                'built_at' => now()->toIso8601String(),
                'arrive_by' => config('transport.journeys.arrive_by'),
                'hubs' => $hubs,
                'minutes' => $minutes,
            ], JSON_UNESCAPED_SLASHES)
        );
    }
}
