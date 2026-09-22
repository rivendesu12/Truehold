<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * UK postcode -> lat/long via postcodes.io.
 *
 * Used for supplier rooms, which come from spreadsheets carrying a postcode but
 * no coordinates, so without this they can never appear on the map.
 *
 * postcodes.io is free, needs no key and has no billing, which is why it is
 * preferred here over Google Geocoding (a separate paid API from Maps).
 * Postcode centroids do not move, so results are cached for a year.
 */
class PostcodeGeocoder
{
    private const ENDPOINT = 'https://api.postcodes.io/postcodes';
    private const BULK_LIMIT = 100;

    /**
     * @param  array<int, string>  $postcodes
     * @return array<string, array{lat: float, lng: float}>  keyed by normalised postcode
     */
    public function lookupMany(array $postcodes): array
    {
        $normalised = [];
        foreach ($postcodes as $raw) {
            $key = $this->normalise($raw);
            if ($key !== '') {
                $normalised[$key] = true;
            }
        }

        $found = [];
        $missing = [];

        foreach (array_keys($normalised) as $key) {
            $cached = Cache::get($this->cacheKey($key));
            if (is_array($cached)) {
                $found[$key] = $cached;
            } elseif ($cached === 'none') {
                continue; // known-bad, do not ask again
            } else {
                $missing[] = $key;
            }
        }

        foreach (array_chunk($missing, self::BULK_LIMIT) as $chunk) {
            foreach ($this->fetchChunk($chunk) as $key => $coords) {
                $found[$key] = $coords;
            }
        }

        return $found;
    }

    /** @param array<int, string> $chunk */
    protected function fetchChunk(array $chunk): array
    {
        $out = [];

        try {
            $response = Http::timeout(25)
                ->acceptJson()
                ->post(self::ENDPOINT, ['postcodes' => $chunk]);

            if (! $response->successful()) {
                Log::warning('postcodes.io lookup failed', ['status' => $response->status()]);
                return [];
            }

            foreach ($response->json('result') ?? [] as $row) {
                $key = $this->normalise((string) ($row['query'] ?? ''));
                $result = $row['result'] ?? null;

                if ($key === '') {
                    continue;
                }

                if (! $result || ! isset($result['latitude'], $result['longitude'])) {
                    // Cache the miss so a bad postcode is not retried every sync.
                    Cache::put($this->cacheKey($key), 'none', now()->addDays(30));
                    continue;
                }

                $coords = [
                    'lat' => (float) $result['latitude'],
                    'lng' => (float) $result['longitude'],
                ];
                Cache::put($this->cacheKey($key), $coords, now()->addYear());
                $out[$key] = $coords;
            }
        } catch (\Throwable $e) {
            Log::warning('postcodes.io request threw', ['error' => $e->getMessage()]);
        }

        return $out;
    }

    public function normalise(string $postcode): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($postcode)) ?? '');
    }

    protected function cacheKey(string $normalised): string
    {
        return 'postcode_geo_' . $normalised;
    }
}
