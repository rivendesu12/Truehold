<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Which borough each station is in, from postcodes.io (open ONS data, no key).
 *
 * Agents ask for "Tower Hamlets" or "near Hackney" and the search only knew
 * stations, areas and postcodes, so a borough came back "could not place".
 * A listing counts as in a borough when its nearest station is, which needs
 * only this one lookup per station rather than one per listing.
 *
 * Kept in its own file so a rebuild does not touch stations.json, whose
 * timestamp keys the cached transport annotations. Saves nothing unless every
 * station was looked up, like transport:build-stations.
 */
class BuildBoroughs extends Command
{
    protected $signature = 'transport:build-boroughs
        {--force : Save even if some stations could not be looked up}';

    protected $description = 'Look up the borough (local authority) of every station via postcodes.io';

    private const BATCH = 100; // postcodes.io's limit per bulk reverse lookup

    public function handle(): int
    {
        $path = storage_path('app/transport/stations.json');
        $stations = array_values(array_filter(
            json_decode((string) @file_get_contents($path), true)['stations'] ?? [],
            fn ($s) => ! empty($s['naptan']) && is_numeric($s['lat'] ?? null) && is_numeric($s['lng'] ?? null)
        ));
        if (! $stations) {
            $this->error('No stations on file: run transport:build-stations first.');
            return self::FAILURE;
        }

        $boroughs = [];
        $missing = [];

        foreach (array_chunk($stations, self::BATCH) as $i => $chunk) {
            $response = Http::timeout(30)->retry(3, 2000, throw: false)
                ->post('https://api.postcodes.io/postcodes', [
                    'geolocations' => array_map(fn ($s) => [
                        'latitude' => (float) $s['lat'],
                        'longitude' => (float) $s['lng'],
                        'radius' => 2000,
                        'limit' => 1,
                    ], $chunk),
                ]);

            if (! $response->successful()) {
                $this->error('postcodes.io refused batch ' . ($i + 1) . ' (HTTP ' . $response->status() . '). Nothing saved.');
                return self::FAILURE;
            }

            foreach ($response->json('result') ?? [] as $j => $row) {
                $station = $chunk[$j] ?? null;
                $district = $row['result'][0]['admin_district'] ?? null;
                if (! $station) {
                    continue;
                }
                if ($district) {
                    $boroughs[$station['naptan']] = $district;
                } else {
                    $missing[] = $station['name'];
                }
            }

            usleep(300_000);
        }

        if ($missing && ! $this->option('force')) {
            $this->error(count($missing) . ' stations got no borough (' . implode(', ', array_slice($missing, 0, 8)) . '). Nothing saved; --force to save anyway.');
            return self::FAILURE;
        }

        file_put_contents(
            storage_path('app/transport/boroughs.json'),
            json_encode(['built_at' => now()->toIso8601String(), 'boroughs' => $boroughs], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $counts = array_count_values($boroughs);
        arsort($counts);
        $this->info(count($boroughs) . ' stations in ' . count($counts) . ' boroughs/districts.');
        foreach (array_slice($counts, 0, 12, true) as $name => $n) {
            $this->line("  {$name}: {$n}");
        }

        return self::SUCCESS;
    }
}
