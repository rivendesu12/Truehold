<?php

namespace App\Services;

use App\Support\PropertyClassifier;
use Illuminate\Support\Facades\Cache;

/**
 * The transport facts we hold about a listing: its nearest station, that
 * station's fare zone and lines, and real journey times to London's main
 * destinations.
 *
 * This exists so "zone 3", "on the Jubilee" and "half an hour from Bond
 * Street" are things we look up rather than things a model guesses. A radius
 * from the centre is a poor stand-in for all three: Wembley Park is zone 4 but
 * only six miles out, and those six miles are 19 minutes to Bond Street while
 * six miles into south London can be over an hour.
 *
 * Built by transport:build-stations and transport:build-journeys from TfL open
 * data. Both files are optional — every method degrades to null rather than
 * failing, so the site works before they are built.
 */
class TransportIndex
{
    /** Beyond this a station is not the listing's station in any useful sense. */
    protected float $maxWalkMiles;

    protected int $minutesPerMile;

    protected ?array $stations = null;
    protected ?array $byName = null;
    protected ?array $journeys = null;
    protected ?string $fingerprint = null;

    public function __construct()
    {
        $this->minutesPerMile = (int) config('transport.walking.minutes_per_mile', 20);
        $this->maxWalkMiles = (float) config('transport.walking.max_walk_miles', 1.5);
    }

    public function isAvailable(): bool
    {
        return $this->stations() !== [];
    }

    public function hasJourneyTimes(): bool
    {
        return ($this->journeyData()['minutes'] ?? []) !== [];
    }

    /** @return array<int, array> */
    protected function stations(): array
    {
        if ($this->stations !== null) {
            return $this->stations;
        }

        $path = storage_path('app/transport/stations.json');
        if (! is_file($path)) {
            return $this->stations = [];
        }

        $data = json_decode((string) file_get_contents($path), true);

        // Only those with coordinates are usable for a nearest-station search.
        return $this->stations = array_values(array_filter(
            $data['stations'] ?? [],
            fn ($s) => is_numeric($s['lat'] ?? null) && is_numeric($s['lng'] ?? null)
        ));
    }

    /**
     * Identifies the current station and journey files. Annotations are cached
     * for 30 days, so without this a rebuild would leave every listing showing
     * the station names and zones of the previous build.
     */
    protected function fingerprint(): string
    {
        if ($this->fingerprint !== null) {
            return $this->fingerprint;
        }

        $parts = [];
        foreach (['stations.json', 'journeys.json'] as $file) {
            $path = storage_path('app/transport/' . $file);
            $parts[] = is_file($path) ? (string) filemtime($path) : '0';
        }

        return $this->fingerprint = substr(md5(implode('|', $parts)), 0, 8);
    }

    /** Exact-name lookup, used to resolve configured hubs to a NaPTAN. */
    public function stationByName(string $name): ?array
    {
        if ($this->byName === null) {
            $this->byName = [];
            foreach ($this->stations() as $station) {
                $this->byName[strtolower($station['name'])] = $station;
            }
        }

        return $this->byName[strtolower(trim($name))] ?? null;
    }

    /** @return array<string, string> naptan => name */
    public function allNaptans(): array
    {
        $out = [];
        foreach ($this->stations() as $station) {
            if (! empty($station['naptan'])) {
                $out[$station['naptan']] = $station['name'];
            }
        }

        return $out;
    }

    /**
     * Nearest station to a point, with its zone, lines and a walking estimate.
     */
    public function nearest(float $lat, float $lng, bool $requireZone = false): ?array
    {
        $best = null;
        $bestMiles = INF;

        foreach ($this->stations() as $station) {
            if ($requireZone && ! is_numeric($station['zone'] ?? null)) {
                continue;
            }

            $miles = PropertyClassifier::milesBetween($lat, $lng, (float) $station['lat'], (float) $station['lng']);
            if ($miles < $bestMiles) {
                $bestMiles = $miles;
                $best = $station;
            }
        }

        if (! $best || (! $requireZone && $bestMiles > $this->maxWalkMiles * 4)) {
            return null;
        }

        // Past a sensible walking distance, saying "121 min walk" is worse than
        // saying nothing: it is not a walk anyone would make, and it made
        // door-to-door times nonsense. Keep the station and the distance, drop
        // the claim.
        $walkable = $bestMiles <= $this->maxWalkMiles;

        return [
            'name' => $best['name'],
            'naptan' => $best['naptan'] ?? null,
            'zone' => is_numeric($best['zone'] ?? null) ? (int) $best['zone'] : null,
            'lines' => array_values($best['lines'] ?? []),
            'miles' => round($bestMiles, 2),
            'walkable' => $walkable,
            'walk_minutes' => $walkable ? (int) max(1, round($bestMiles * $this->minutesPerMile)) : null,
        ];
    }

    /**
     * Nearest station that has a fare zone recorded — a listing's zone should
     * not come back empty because the closest stop happens to lack the field
     * (tram stops outside the zone system, mostly).
     */
    public function nearestWithZone(float $lat, float $lng): ?array
    {
        return $this->nearest($lat, $lng, requireZone: true);
    }

    /**
     * Annotate a property with transport facts. Cached per coordinate pair,
     * since many listings share a building, and keyed by the data files' own
     * fingerprint so a rebuild takes effect immediately.
     */
    public function annotate(array $property): array
    {
        $lat = $property['latitude'] ?? null;
        $lng = $property['longitude'] ?? null;

        if (! is_numeric($lat) || ! is_numeric($lng) || ! $this->isAvailable()) {
            return $property;
        }

        $key = 'transport_' . $this->fingerprint() . '_'
            . md5(round((float) $lat, 4) . ',' . round((float) $lng, 4));

        $facts = Cache::remember($key, now()->addDays(30), function () use ($lat, $lng) {
            $nearest = $this->nearest((float) $lat, (float) $lng);
            $zoned = $this->nearestWithZone((float) $lat, (float) $lng);
            $station = $nearest ?? $zoned;

            return [
                'nearest_station' => $station['name'] ?? null,
                'station_naptan' => $station['naptan'] ?? null,
                'station_lines' => $station['lines'] ?? [],
                'station_walkable' => (bool) ($station['walkable'] ?? false),
                'walk_minutes' => $station['walk_minutes'] ?? null,
                'station_miles' => $station['miles'] ?? null,
                'zone' => $zoned['zone'] ?? null,
                'zone_station' => $zoned['name'] ?? null,
            ];
        });

        $property = array_merge($property, $facts);

        // Door-to-hub minutes: the walk to the station plus the ride. Only
        // where the station is actually walkable — otherwise the total would
        // quietly omit however the tenant is meant to cover those six miles.
        if (! empty($facts['station_naptan']) && ! empty($facts['station_walkable'])) {
            $property['journey_minutes'] = $this->journeysFrom(
                $facts['station_naptan'],
                (int) ($facts['walk_minutes'] ?? 0)
            );
        }

        return $property;
    }

    protected function journeyData(): array
    {
        if ($this->journeys !== null) {
            return $this->journeys;
        }

        $path = storage_path('app/transport/journeys.json');
        if (! is_file($path)) {
            return $this->journeys = ['hubs' => [], 'minutes' => []];
        }

        $data = json_decode((string) file_get_contents($path), true);

        return $this->journeys = [
            'hubs' => $data['hubs'] ?? [],
            'minutes' => $data['minutes'] ?? [],
        ];
    }

    /**
     * Door-to-hub journey times for one station.
     *
     * @return array<string, array{minutes: int, changes: int, ride: int}>
     */
    public function journeysFrom(string $naptan, int $walkMinutes = 0): array
    {
        $rides = $this->journeyData()['minutes'][$naptan] ?? [];
        $out = [];

        foreach ($rides as $hub => $leg) {
            if (! is_numeric($leg['m'] ?? null)) {
                continue;
            }

            $out[$hub] = [
                'minutes' => (int) $leg['m'] + $walkMinutes,
                'ride' => (int) $leg['m'],
                'changes' => (int) ($leg['c'] ?? 0),
            ];
        }

        return $out;
    }

    /** @return array<string, array> the hubs journey times were built for */
    public function hubs(): array
    {
        return $this->journeyData()['hubs'] ?: (array) config('transport.hubs', []);
    }

    /**
     * Map a phrase an agent typed onto a hub key, using the hub labels,
     * station names and the aliases in config/transport.php. Substring match
     * both ways, so "near Canary Wharf" and "the Wharf" both land.
     */
    public function resolveHub(string $phrase): ?string
    {
        $needle = strtolower(trim($phrase));
        if ($needle === '') {
            return null;
        }

        $best = null;
        $bestLength = 0;

        foreach ($this->hubs() as $key => $hub) {
            $candidates = array_merge(
                [$hub['label'] ?? $key, $hub['station'] ?? ''],
                (array) ($hub['aliases'] ?? [])
            );

            foreach ($candidates as $candidate) {
                $candidate = strtolower(trim((string) $candidate));
                if ($candidate === '') {
                    continue;
                }

                // An exact match wins outright. Preferring the longest match
                // alone sent "UCL" to Stratford, because "ucl east" contains
                // "ucl" and is the longer string.
                if ($needle === $candidate) {
                    return $key;
                }

                if (str_contains($needle, $candidate) || str_contains($candidate, $needle)) {
                    // Otherwise the longest, so "canary wharf" beats "the city"
                    // when both happen to appear in one phrase.
                    if (strlen($candidate) > $bestLength) {
                        $best = $key;
                        $bestLength = strlen($candidate);
                    }
                }
            }
        }

        return $best;
    }

    /**
     * Find a station by an agent's wording — "Ealing Broadway", "ealing
     * broadway station", "Kings Cross". Prefers the longest name that matches,
     * so "Shepherd's Bush" does not lose to a shorter partial.
     *
     * This is what lets a brief name any of London's 509 stations rather than
     * only the handful anyone thought to hardcode.
     */
    public function findStation(string $name): ?array
    {
        $needle = strtolower(trim(preg_replace('/\s+(underground|tube|rail|dlr)?\s*station$/i', '', trim($name))));

        if (strlen($needle) < 3) {
            return null;
        }

        $best = null;
        $bestLength = 0;

        foreach ($this->stations() as $station) {
            $candidate = strtolower($station['name']);

            if ($candidate === $needle) {
                return $station;
            }

            if (str_contains($needle, $candidate) || str_contains($candidate, $needle)) {
                if (strlen($candidate) > $bestLength) {
                    $best = $station;
                    $bestLength = strlen($candidate);
                }
            }
        }

        return $best;
    }

    /** Human label for a hub key. */
    public function hubLabel(string $key): string
    {
        return $this->hubs()[$key]['label'] ?? ucwords(str_replace('-', ' ', $key));
    }
}
