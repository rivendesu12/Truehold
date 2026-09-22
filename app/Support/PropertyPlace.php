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

            // The district earns its place only when it is this listing's own:
            // two rooms can share a station and sit a mile apart. A district
            // scraped from elsewhere on a page is worse than none, so it is
            // only shown when the listing carries a real postcode for it.
            $district = self::district((string) $get('postcode'));

            if ($district === '' && self::looksTrustworthy($property, $area)) {
                $district = self::district($area);
            }

            return $district !== '' ? $line . ' · ' . $district : $line;
        }

        return $area !== '' ? $area : 'Location not specified';
    }

    /**
     * A district is trusted when the listing's own title or description says
     * it too. One advert's district scraped off a shared page was being
     * stamped onto rooms four miles away.
     */
    protected static function looksTrustworthy($property, string $area): bool
    {
        $district = self::district($area);

        if ($district === '') {
            return false;
        }

        $get = fn (string $k) => is_array($property) ? ($property[$k] ?? null) : ($property->{$k} ?? null);

        $text = strtoupper((string) $get('title') . ' ' . (string) $get('description'));

        return str_contains($text, $district);
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
