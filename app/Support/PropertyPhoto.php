<?php

namespace App\Support;

/**
 * Picks the best available image for a listing.
 *
 * The property cards and the assistant panel were disagreeing: the cards read
 * the high-quality photo set and fell back to the single photo field, while
 * the panel only ever read the single field. A listing whose pictures live in
 * `photos` therefore rendered as an empty grey box in the panel and correctly
 * on the card. One rule, in one place, so they cannot drift again.
 */
final class PropertyPhoto
{
    public static function best(array $property): ?string
    {
        foreach (self::all($property) as $url) {
            return $url;
        }

        return null;
    }

    /** @return array<int, string> */
    public static function all(array $property): array
    {
        $candidates = [];

        foreach (['all_photos', 'photos'] as $field) {
            $value = $property[$field] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $candidates = array_merge($candidates, preg_split('/\s*,\s*/', trim($value)));
            } elseif (is_array($value)) {
                $candidates = array_merge($candidates, $value);
            }
        }

        $single = $property['first_photo_url'] ?? null;
        if (is_string($single) && trim($single) !== '') {
            $candidates[] = $single;
        }

        $out = [];

        foreach ($candidates as $url) {
            $url = trim((string) $url);

            if ($url === '' || strcasecmp($url, 'N/A') === 0) {
                continue;
            }

            // SpareRoom serves the same photo at several sizes; the square one
            // is a thumbnail, so ask for the large version instead.
            if (str_contains($url, 'spareroom.co.uk') && str_contains($url, '/square/')) {
                $url = str_replace('/square/', '/large/', $url);
            }

            if (! in_array($url, $out, true)) {
                $out[] = $url;
            }
        }

        return $out;
    }
}
