<?php

namespace App\Services\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Shared in-memory filtering for property feeds that return a Collection of
 * property arrays (Google Sheets, Harbor Ops scraped-listings API).
 */
trait FiltersPropertyCollection
{
    /**
     * Get all properties from the feed.
     */
    abstract public function getAllProperties(): Collection;

    /**
     * Filter properties by criteria
     *
     * @param array $filters
     * @return Collection
     */
    public function filterProperties(array $filters): Collection
    {
        $properties = $this->getAllProperties();

        // Apply filters
        if (isset($filters['location'])) {
            $properties = $properties->filter(function ($property) use ($filters) {
                return stripos($property['location'] ?? '', $filters['location']) !== false;
            });
        }

        // Property type is presented as three buckets rather than the ~14 raw
        // values the sources use ("Double", "En-Suite", "Ensuite", "double room",
        // "Master", "Flat", …). Accepts one bucket or several.
        if (! empty($filters['property_types'])) {
            $wanted = array_filter((array) $filters['property_types']);
            if ($wanted) {
                $properties = $properties->filter(function ($property) use ($wanted) {
                    return in_array(\App\Support\PropertyClassifier::bucket($property), $wanted, true);
                });
            }
        } elseif (isset($filters['property_type'])) {
            // Legacy single raw-value filter, kept so existing shared links work.
            $properties = $properties->filter(function ($property) use ($filters) {
                return stripos($property['property_type'] ?? '', $filters['property_type']) !== false;
            });
        }

        // Radius search: keep listings within N miles of the centre of whatever
        // the location term matched, so "Wapping + 1 mile" also finds Shadwell.
        if (! empty($filters['radius_miles']) && ! empty($filters['radius_center'])) {
            $miles = (float) $filters['radius_miles'];
            [$centerLat, $centerLng] = $filters['radius_center'];
            $properties = $properties->filter(function ($property) use ($miles, $centerLat, $centerLng) {
                $lat = $property['latitude'] ?? null;
                $lng = $property['longitude'] ?? null;
                if (! is_numeric($lat) || ! is_numeric($lng)) {
                    return false;
                }
                return \App\Support\PropertyClassifier::milesBetween((float) $lat, (float) $lng, $centerLat, $centerLng) <= $miles;
            });
        }

        if (isset($filters['available_date'])) {
            $properties = $properties->filter(function ($property) use ($filters) {
                return stripos($property['available_date'] ?? '', $filters['available_date']) !== false;
            });
        }

        if (isset($filters['management_company'])) {
            $properties = $properties->filter(function ($property) use ($filters) {
                return stripos($property['management_company'] ?? '', $filters['management_company']) !== false;
            });
        }

        $allowRestricted = (bool) ($filters['allow_restricted'] ?? false);

        if (isset($filters['agent_name']) && (auth()->check() || $allowRestricted)) {
            $properties = $properties->filter(function ($property) use ($filters) {
                return stripos($property['agent_name'] ?? '', $filters['agent_name']) !== false;
            });
        }

        if (isset($filters['paying_only']) && (auth()->check() || $allowRestricted)) {
            // The same rule as the cards and Sigou: config's always/never
            // lists, then the feed.
            $rates = app(\App\Services\CommissionRates::class);
            $properties = $properties->filter(fn ($property) => $rates->pays((array) $property));
        }

        if (isset($filters['room_count']) && $filters['room_count'] !== '') {
            $roomCountFilter = trim((string) $filters['room_count']);
            $properties = $properties->filter(function ($property) use ($roomCountFilter) {
                $totalRooms = isset($property['total_rooms']) ? trim((string) $property['total_rooms']) : '';
                $roomCount = isset($property['room_count']) ? trim((string) $property['room_count']) : '';
                return $totalRooms === $roomCountFilter || $roomCount === $roomCountFilter;
            });
        }

        if (isset($filters['couples_allowed'])) {
            $properties = $properties->filter(function ($property) use ($filters) {
                $couplesOk = strtolower($property['couples_ok'] ?? '');
                $couplesAllowed = strtolower($property['couples_allowed'] ?? '');

                if ($filters['couples_allowed'] === 'yes') {
                    return stripos($couplesOk, 'yes') !== false
                        || stripos($couplesOk, 'couples') !== false
                        || stripos($couplesAllowed, 'yes') !== false
                        || stripos($couplesAllowed, 'couples') !== false;
                } else {
                    return stripos($couplesOk, 'no') !== false
                        || stripos($couplesAllowed, 'no') !== false;
                }
            });
        }

        // Ensuite filter - must have "en-suite" or "ensuite" in description and NOT have "studio"
        // Also exclude cases where en-suites are mentioned but not available
        if (isset($filters['ensuite']) && $filters['ensuite'] === 'yes') {
            $properties = $properties->filter(function ($property) {
                $description = strtolower($property['description'] ?? '');

                // Must have "en-suite" or "ensuite" in description
                $hasEnsuite = stripos($description, 'en-suite') !== false
                            || stripos($description, 'ensuite') !== false;

                if (!$hasEnsuite) {
                    return false;
                }

                // Must NOT have "studio" in description
                $hasStudio = stripos($description, 'studio') !== false;
                if ($hasStudio) {
                    return false;
                }

                // Must NOT indicate that en-suites are not available
                // Check for direct patterns first
                $directNegativePatterns = [
                    'en-suite not available',
                    'ensuite not available',
                    'en-suite unavailable',
                    'ensuite unavailable',
                    'en-suite are not available',
                    'ensuite are not available',
                    'en-suite not included',
                    'ensuite not included',
                    'en-suite not for',
                    'ensuite not for',
                ];

                foreach ($directNegativePatterns as $pattern) {
                    if (stripos($description, $pattern) !== false) {
                        return false;
                    }
                }

                // Check for patterns with words in between (e.g., "en-suite rooms (ENSUITES ARE NOT AVAILABLE")
                // If both "en-suite"/"ensuite" and "not available"/"unavailable" appear, exclude
                $hasEnsuiteMention = stripos($description, 'en-suite') !== false || stripos($description, 'ensuite') !== false;
                $hasNotAvailable = stripos($description, 'not available') !== false || stripos($description, 'unavailable') !== false;

                if ($hasEnsuiteMention && $hasNotAvailable) {
                    // Additional check: make sure they're in the same context
                    // Find positions to ensure they're reasonably close (within 200 characters)
                    $ensuitePos = max(
                        stripos($description, 'en-suite') !== false ? stripos($description, 'en-suite') : -1,
                        stripos($description, 'ensuite') !== false ? stripos($description, 'ensuite') : -1
                    );
                    $notAvailablePos = max(
                        stripos($description, 'not available') !== false ? stripos($description, 'not available') : -1,
                        stripos($description, 'unavailable') !== false ? stripos($description, 'unavailable') : -1
                    );

                    if ($ensuitePos !== -1 && $notAvailablePos !== -1 && abs($ensuitePos - $notAvailablePos) < 200) {
                        return false;
                    }
                }

                // Check for pattern indicating available rooms have shared bathrooms
                // If description mentions "en-suite"/"ensuite", "available", and "shared bathroom" together, exclude
                if ($hasEnsuiteMention && stripos($description, 'available') !== false) {
                    if (stripos($description, 'shared bathroom') !== false ||
                        (stripos($description, 'available room') !== false && stripos($description, 'shared') !== false)) {
                        return false;
                    }
                }

                return true;
            });
        }

        // Price filtering
        if (isset($filters['min_price'])) {
            $properties = $properties->filter(function ($property) use ($filters) {
                $price = $this->extractNumericPrice((string) ($property['price'] ?? ''));
                return $price >= (float) $filters['min_price'];
            });
        }

        if (isset($filters['max_price'])) {
            $properties = $properties->filter(function ($property) use ($filters) {
                $price = $this->extractNumericPrice((string) ($property['price'] ?? ''));
                return $price <= (float) $filters['max_price'];
            });
        }

        return $properties;
    }

    /**
     * Extract numeric price from price string
     *
     * @param string $priceString
     * @return float
     */
    protected function extractNumericPrice(string $priceString): float
    {
        // Remove currency symbols and text
        $cleaned = preg_replace('/[£,\s]/', '', $priceString);
        $cleaned = preg_replace('/[^0-9.]/', '', $cleaned);

        return (float) $cleaned;
    }

    /**
     * Get unique values for filter dropdowns
     *
     * @return array
     */
    public function getFilterValues(): array
    {
        try {
            $properties = $this->getAllProperties();

            return [
                'locations' => $properties->pluck('location')
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values(),
                'propertyTypes' => $properties->pluck('property_type')
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values(),
                'available_dates' => $properties->pluck('available_date')
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values(),
                'agent_names' => auth()->check()
                    ? $properties->pluck('agent_name')
                        ->filter()
                        ->unique()
                        ->sort()
                        ->values()
                    : collect(),
                'agents_with_paying' => auth()->check()
                    ? $properties->filter(function ($property) {
                        $paying = $property['paying'] ?? null;
                        if ($paying === null || $paying === '') {
                            return false;
                        }
                        $paying = is_string($paying) ? strtolower(trim($paying)) : $paying;
                        return $paying === 'yes' || $paying === true || $paying === 1;
                    })
                        ->pluck('agent_name')
                        ->filter()
                        ->unique()
                        ->values()
                    : collect(),
                'room_counts' => $properties->map(function ($property) {
                    $v = $property['total_rooms'] ?? $property['room_count'] ?? null;
                    return $v !== null && $v !== '' ? trim((string) $v) : null;
                })
                    ->filter()
                    ->unique()
                    ->sortBy(function ($v) {
                        return is_numeric($v) ? (int) $v : $v;
                    })
                    ->values(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get filter values from property feed', [
                'feed' => static::class,
                'error' => $e->getMessage()
            ]);

            // Return empty collections on error
            return [
                'locations' => collect(),
                'propertyTypes' => collect(),
                'available_dates' => collect(),
                'agent_names' => collect(),
                'agents_with_paying' => collect(),
                'room_counts' => collect(),
            ];
        }
    }

}
