<?php

namespace App\Services;

use App\Support\PropertyClassifier;
use Illuminate\Support\Facades\Cache;

/**
 * Nearest-station lookup, so "zone 3" and "near a tube" are facts about a
 * listing rather than guesses from its distance to the centre.
 *
 * Built by transport:build-stations from TfL's open data.
 */
class TransportIndex
{
    /** Walking pace: 3 mph, so 20 minutes a mile. */
    private const MINUTES_PER_MILE = 20;

    /** Beyond this a station is not meaningfully "nearby" on foot. */
    private const MAX_WALK_MILES = 1.5;

    protected ?array $stations = null;

    public function isAvailable(): bool
    {
        return $this->stations() !== [];
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
        $stations = $data['stations'] ?? [];

        // Only those with coordinates are usable for a nearest-station search.
        return $this->stations = array_values(array_filter(
            $stations,
            fn ($s) => is_numeric($s['lat'] ?? null) && is_numeric($s['lng'] ?? null)
        ));
    }

    /**
     * Nearest station to a point, with its zone and a walking estimate.
     *
     * @return array{name: string, zone: ?int, miles: float, walk_minutes: int}|null
     */
    public function nearest(float $lat, float $lng): ?array
    {
        $best = null;
        $bestMiles = INF;

        foreach ($this->stations() as $station) {
            $miles = PropertyClassifier::milesBetween($lat, $lng, (float) $station['lat'], (float) $station['lng']);
            if ($miles < $bestMiles) {
                $bestMiles = $miles;
                $best = $station;
            }
        }

        if (! $best || $bestMiles > self::MAX_WALK_MILES * 4) {
            return null;
        }

        return [
            'name' => $best['name'],
            'zone' => $best['zone'] ?? null,
            'miles' => round($bestMiles, 2),
            'walk_minutes' => (int) max(1, round($bestMiles * self::MINUTES_PER_MILE)),
        ];
    }

    /**
     * Nearest station that has a fare zone recorded — a listing's zone should
     * not come back empty because the closest stop happens to lack the field.
     *
     * @return array{name: string, zone: int, miles: float, walk_minutes: int}|null
     */
    public function nearestWithZone(float $lat, float $lng): ?array
    {
        $best = null;
        $bestMiles = INF;

        foreach ($this->stations() as $station) {
            if (! is_numeric($station['zone'] ?? null)) {
                continue;
            }
            $miles = PropertyClassifier::milesBetween($lat, $lng, (float) $station['lat'], (float) $station['lng']);
            if ($miles < $bestMiles) {
                $bestMiles = $miles;
                $best = $station;
            }
        }

        if (! $best) {
            return null;
        }

        return [
            'name' => $best['name'],
            'zone' => (int) $best['zone'],
            'miles' => round($bestMiles, 2),
            'walk_minutes' => (int) max(1, round($bestMiles * self::MINUTES_PER_MILE)),
        ];
    }

    /**
     * Annotate a property with transport facts. Cached per coordinate pair,
     * since many listings share a building.
     */
    public function annotate(array $property): array
    {
        $lat = $property['latitude'] ?? null;
        $lng = $property['longitude'] ?? null;

        if (! is_numeric($lat) || ! is_numeric($lng) || ! $this->isAvailable()) {
            return $property;
        }

        $key = 'transport_' . md5(round((float) $lat, 4) . ',' . round((float) $lng, 4));

        $facts = Cache::remember($key, now()->addDays(30), function () use ($lat, $lng) {
            $nearest = $this->nearest((float) $lat, (float) $lng);
            $zoned = $this->nearestWithZone((float) $lat, (float) $lng);

            return [
                'nearest_station' => $nearest['name'] ?? ($zoned['name'] ?? null),
                'walk_minutes' => $nearest['walk_minutes'] ?? ($zoned['walk_minutes'] ?? null),
                'station_miles' => $nearest['miles'] ?? ($zoned['miles'] ?? null),
                'zone' => $zoned['zone'] ?? null,
                'zone_station' => $zoned['name'] ?? null,
            ];
        });

        return array_merge($property, $facts);
    }
}
