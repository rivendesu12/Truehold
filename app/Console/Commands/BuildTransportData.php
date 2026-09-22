<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Builds a local station index from TfL's open StopPoint API.
 *
 * Without this the assistant has to guess what "zone 3" or "near a tube"
 * means, and a radius from the centre is a poor stand-in: Wembley Park is
 * zone 4 but only ~6 miles out, so a zone-3 circle wrongly includes it.
 *
 * TfL needs no API key for this endpoint. Zones change very rarely, so the
 * file is rebuilt on demand rather than on a schedule.
 */
class BuildTransportData extends Command
{
    protected $signature = 'transport:build-stations';

    protected $description = 'Fetch London station names, coordinates and fare zones from TfL';

    private const MODES = ['tube', 'dlr', 'overground', 'elizabeth-line', 'tram'];

    public function handle(): int
    {
        $stations = [];

        foreach (self::MODES as $mode) {
            $this->line("Fetching {$mode}…");
            $page = 1;

            do {
                try {
                    $response = Http::timeout(60)->acceptJson()
                        ->get("https://api.tfl.gov.uk/StopPoint/Mode/{$mode}", ['page' => $page]);
                } catch (\Throwable $e) {
                    $this->warn("  {$mode} page {$page} failed: {$e->getMessage()}");
                    break;
                }

                if (! $response->successful()) {
                    $this->warn("  {$mode} page {$page} -> HTTP {$response->status()}");
                    break;
                }

                $stops = $response->json('stopPoints') ?? [];

                foreach ($stops as $stop) {
                    $name = trim((string) ($stop['commonName'] ?? ''));
                    $lat = $stop['lat'] ?? null;
                    $lon = $stop['lon'] ?? null;

                    if ($name === '' || ! is_numeric($lat) || ! is_numeric($lon)) {
                        continue;
                    }

                    $zone = null;
                    foreach ($stop['additionalProperties'] ?? [] as $prop) {
                        if (strtolower((string) ($prop['key'] ?? '')) === 'zone') {
                            $zone = $this->lowestZone((string) ($prop['value'] ?? ''));
                            break;
                        }
                    }

                    // Boundary stations appear once per mode; keep the first with
                    // a zone, so a mode lacking the property cannot blank it.
                    $key = strtolower(preg_replace('/\s+station$/i', '', $name));

                    if (isset($stations[$key]) && $stations[$key]['zone'] !== null) {
                        continue;
                    }

                    $stations[$key] = [
                        'name' => preg_replace('/\s+Station$/i', '', $name),
                        'lat' => (float) $lat,
                        'lng' => (float) $lon,
                        'zone' => $zone,
                        'modes' => $stop['modes'] ?? [],
                    ];
                }

                $total = (int) ($response->json('total') ?? 0);
                $perPage = (int) ($response->json('pageSize') ?? count($stops));
                $more = $perPage > 0 && $page * $perPage < $total;
                $page++;
            } while ($more && $page <= 5);
        }

        $stations = array_values($stations);
        usort($stations, fn ($a, $b) => strcmp($a['name'], $b['name']));

        $withZone = count(array_filter($stations, fn ($s) => $s['zone'] !== null));

        $dir = storage_path('app/transport');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        file_put_contents(
            $dir . '/stations.json',
            json_encode(['built_at' => now()->toIso8601String(), 'stations' => $stations], JSON_PRETTY_PRINT)
        );

        $this->newLine();
        $this->info(sprintf('Saved %d stations (%d with a fare zone).', count($stations), $withZone));

        $byZone = [];
        foreach ($stations as $s) {
            $byZone[$s['zone'] ?? 'unknown'] = ($byZone[$s['zone'] ?? 'unknown'] ?? 0) + 1;
        }
        ksort($byZone);
        foreach ($byZone as $zone => $count) {
            $this->line("  zone {$zone}: {$count}");
        }

        return self::SUCCESS;
    }

    /**
     * TfL writes boundary stations as "2+3" or "3|4". The lower number is what
     * a traveller pays and what an agent means by "zone 3".
     */
    protected function lowestZone(string $raw): ?int
    {
        preg_match_all('/\d+/', $raw, $m);
        if (empty($m[0])) {
            return null;
        }
        return (int) min(array_map('intval', $m[0]));
    }
}
