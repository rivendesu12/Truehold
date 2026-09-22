<?php

namespace App\Support;

/**
 * Shared property classification and geo helpers.
 *
 * These live in a class rather than the FiltersPropertyCollection trait because
 * several callers need them statically, and PHP 8.4 deprecates calling a trait's
 * static methods directly.
 */
final class PropertyClassifier
{
    /**
     * Collapse a source's free-text room type into one of three buckets.
     *
     * Sources emit "Double", "En suite", "En-Suite", "Ensuite", "Flat",
     * "Master", "Room", "Single", "Studio", "double room", "single room" …
     */
    public static function bucket(array $property): string
    {
        $raw = strtolower(trim((string) ($property['property_type'] ?? '')));
        $title = strtolower((string) ($property['title'] ?? ''));

        if (str_contains($raw, 'studio') || str_contains($title, 'studio')) {
            return 'studio';
        }

        // "3-Bedroom Flat Share in E3" carries property_type "Flat" but lets a
        // single room. The share wording in the title is the reliable signal;
        // without it these were offered as whole three-bed flats.
        foreach (['flat share', 'flatshare', 'house share', 'houseshare', 'room in', 'rooms in', 'room available'] as $needle) {
            if (str_contains($title, $needle)) {
                return 'rooms';
            }
        }

        foreach (['flat', 'apartment', 'house', 'whole', 'full property', 'maisonette'] as $needle) {
            if (str_contains($raw, $needle)) {
                return 'full_property';
            }
        }

        // Everything else the sources emit is a room of some kind.
        return 'rooms';
    }

    /** Great-circle distance in miles. */
    public static function milesBetween(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 3958.8;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
