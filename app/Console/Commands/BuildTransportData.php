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
 * Built line by line rather than mode by mode. The per-mode endpoint returns
 * platform- and entrance-level stop points whose `lines` array is empty, and
 * no NaPTAN we can hand to the journey planner; /Line/{id}/StopPoints returns
 * one row per station, with its hub NaPTAN, its fare zone, and — by virtue of
 * which call it came back on — the lines that serve it.
 *
 * TfL needs no API key for this. Zones and lines change very rarely, so the
 * file is rebuilt on demand rather than on a schedule.
 */
class BuildTransportData extends Command
{
    protected $signature = 'transport:build-stations';

    protected $description = 'Fetch London station names, coordinates, fare zones and lines from TfL';

    private const MODES = 'tube,dlr,overground,elizabeth-line,tram';

    public function handle(): int
    {
        $lines = $this->fetchLines();
        if (! $lines) {
            $this->error('Could not list lines from TfL.');
            return self::FAILURE;
        }

        $this->info(count($lines) . ' lines to walk.');

        $stations = [];

        foreach ($lines as $line) {
            $stops = $this->fetchLineStops($line['id']);
            if ($stops === null) {
                $this->warn("  {$line['name']}: failed");
                continue;
            }

            $added = 0;
            foreach ($stops as $stop) {
                $naptan = trim((string) ($stop['stationNaptan'] ?? $stop['id'] ?? ''));
                $name = $this->cleanName((string) ($stop['commonName'] ?? ''));
                $lat = $stop['lat'] ?? null;
                $lng = $stop['lon'] ?? null;

                if ($naptan === '' || $name === '' || ! is_numeric($lat) || ! is_numeric($lng)) {
                    continue;
                }

                if (! isset($stations[$naptan])) {
                    $stations[$naptan] = [
                        'naptan' => $naptan,
                        'name' => $name,
                        'lat' => (float) $lat,
                        'lng' => (float) $lng,
                        'zone' => $this->zoneOf($stop),
                        'lines' => [],
                        'modes' => [],
                    ];
                    $added++;
                }

                // A station reached on a second line can be the one carrying the
                // zone property, so never let a later blank overwrite a value.
                $stations[$naptan]['zone'] ??= $this->zoneOf($stop);

                $stations[$naptan]['lines'][$line['name']] = true;
                foreach ($stop['modes'] ?? [] as $mode) {
                    $stations[$naptan]['modes'][$mode] = true;
                }
            }

            $this->line(sprintf('  %-18s %3d stops (%d new)', $line['name'], count($stops), $added));
        }

        $stations = array_map(function (array $s) {
            $s['lines'] = array_values(array_keys($s['lines']));
            $s['modes'] = array_values(array_keys($s['modes']));
            sort($s['lines']);
            sort($s['modes']);
            return $s;
        }, $stations);

        $stations = array_values($stations);
        usort($stations, fn ($a, $b) => strcmp($a['name'], $b['name']));

        $this->save($stations);

        return self::SUCCESS;
    }

    protected function fetchLines(): array
    {
        try {
            $response = Http::timeout(60)->acceptJson()
                ->get('https://api.tfl.gov.uk/Line/Mode/' . self::MODES);
        } catch (\Throwable $e) {
            $this->warn('Line list failed: ' . $e->getMessage());
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        return collect($response->json() ?? [])
            ->filter(fn ($l) => ! empty($l['id']) && ! empty($l['name']))
            ->map(fn ($l) => ['id' => $l['id'], 'name' => $l['name']])
            ->values()
            ->all();
    }

    protected function fetchLineStops(string $lineId): ?array
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $response = Http::timeout(60)->acceptJson()
                    ->get("https://api.tfl.gov.uk/Line/{$lineId}/StopPoints");
            } catch (\Throwable $e) {
                usleep(500_000);
                continue;
            }

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            usleep(500_000 * $attempt);
        }

        return null;
    }

    protected function cleanName(string $name): string
    {
        return trim(preg_replace(
            '/\s+(Underground|Rail|DLR|Tram)?\s*Station$/i',
            '',
            trim($name)
        ));
    }

    protected function zoneOf(array $stop): ?int
    {
        foreach ($stop['additionalProperties'] ?? [] as $prop) {
            if (strtolower((string) ($prop['key'] ?? '')) === 'zone') {
                return $this->lowestZone((string) ($prop['value'] ?? ''));
            }
        }

        return null;
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

    protected function save(array $stations): void
    {
        $dir = storage_path('app/transport');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        file_put_contents(
            $dir . '/stations.json',
            json_encode(
                ['built_at' => now()->toIso8601String(), 'stations' => $stations],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );

        $withZone = count(array_filter($stations, fn ($s) => $s['zone'] !== null));
        $withLines = count(array_filter($stations, fn ($s) => $s['lines'] !== []));

        $this->newLine();
        $this->info(sprintf(
            'Saved %d stations — %d with a fare zone, %d with lines.',
            count($stations),
            $withZone,
            $withLines
        ));

        $byZone = [];
        foreach ($stations as $s) {
            $key = $s['zone'] ?? 'unknown';
            $byZone[$key] = ($byZone[$key] ?? 0) + 1;
        }
        ksort($byZone);
        foreach ($byZone as $zone => $count) {
            $this->line("  zone {$zone}: {$count}");
        }
    }
}
