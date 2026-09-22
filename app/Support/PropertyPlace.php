<?php

namespace App\Support;

/**
 * How a listing's whereabouts is written on a card.
 *
 * A postcode district tells an agent very little at a glance — "London SE27"
 * is a shrug. The nearest station and the walk to it is what actually places
 * a room in someone's head, and we already hold both from TfL, so that is
 * what the cards show, with the district kept as the fallback for the handful
 * of listings with no station within walking distance.
 */
final class PropertyPlace
{
    public static function describe($property): string
    {
        $get = function (string $key) use ($property) {
            if (is_array($property)) {
                return $property[$key] ?? null;
            }

            return $property->{$key} ?? null;
        };

        $station = trim((string) $get('nearest_station'));
        $walk = $get('walk_minutes');
        $area = trim((string) $get('location'));

        if ($station !== '') {
            $line = $station;

            if (is_numeric($walk) && (int) $walk > 0) {
                $line .= ', ' . (int) $walk . ' min walk';
            }

            // The district still earns its place: two Whitechapels are a mile
            // apart, and an agent reads the postcode to tell them apart.
            $district = self::district($area) ?: self::district((string) $get('postcode'));

            return $district !== '' ? $line . ' · ' . $district : $line;
        }

        return $area !== '' ? $area : 'Location not specified';
    }

    /** "London SE27" -> "SE27"; a full postcode -> its outward code. */
    protected static function district(string $value): string
    {
        if (preg_match('/\b([A-Z]{1,2}\d{1,2}[A-Z]?)\b/i', $value, $m)) {
            return strtoupper($m[1]);
        }

        return '';
    }
}
