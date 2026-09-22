<?php

namespace App\Support;

/**
 * Which part of London a listing is in.
 *
 * "A room in east London" and "somewhere in south London" are among the most
 * common things an agent types, and the assistant was dropping them entirely:
 * neither is a listing's stated area, so there was no field to put them in and
 * the constraint silently disappeared.
 *
 * London's postcode districts already encode this and everybody uses them that
 * way — E is east, SE is south-east, NW is north-west. Where a listing has no
 * postcode we fall back to the bearing from Charing Cross, which is the same
 * judgement made from coordinates instead of letters.
 */
final class LondonRegion
{
    /** Inner London postcode areas, which mean exactly what they say. */
    private const INNER = [
        'EC' => 'central', 'WC' => 'central',
        'E' => 'east', 'N' => 'north', 'NW' => 'north_west',
        'SE' => 'south_east', 'SW' => 'south_west', 'W' => 'west',
    ];

    /** Outer areas, by where they sit relative to the centre. */
    private const OUTER = [
        'IG' => 'east', 'RM' => 'east', 'DA' => 'south_east', 'BR' => 'south_east',
        'CR' => 'south', 'SM' => 'south', 'KT' => 'south_west', 'TW' => 'west',
        'UB' => 'west', 'HA' => 'north_west', 'WD' => 'north_west',
        'EN' => 'north', 'AL' => 'north', 'SL' => 'west', 'SS' => 'east',
        'ME' => 'south_east', 'TN' => 'south_east', 'RH' => 'south',
        'GU' => 'south_west', 'HP' => 'north_west', 'CM' => 'east',
    ];

    /** What an agent's word covers. "South" includes both south-easts. */
    private const COVERS = [
        'central' => ['central'],
        'north' => ['north', 'north_east', 'north_west'],
        'south' => ['south', 'south_east', 'south_west'],
        'east' => ['east', 'north_east', 'south_east'],
        'west' => ['west', 'north_west', 'south_west'],
        'north_east' => ['north_east'],
        'north_west' => ['north_west'],
        'south_east' => ['south_east'],
        'south_west' => ['south_west'],
    ];

    public static function of(array $property): ?string
    {
        $area = self::postcodeArea($property);

        if ($area !== null) {
            return self::INNER[$area] ?? self::OUTER[$area] ?? null;
        }

        return self::fromCoordinates($property);
    }

    /** Does this listing satisfy an agent asking for `$wanted`? */
    public static function matches(array $property, string $wanted): bool
    {
        $region = self::of($property);
        if ($region === null) {
            return false;
        }

        $key = strtolower(str_replace([' ', '-'], '_', trim($wanted)));
        $key = preg_replace('/_?london$/', '', $key);
        $key = trim($key, '_');

        $covers = self::COVERS[$key] ?? null;

        return $covers !== null && in_array($region, $covers, true);
    }

    /** The letters of the postcode district: "E14 9RP" -> "E". */
    protected static function postcodeArea(array $property): ?string
    {
        $haystack = ($property['postcode'] ?? '') . ' '
            . ($property['title'] ?? '') . ' '
            . ($property['location'] ?? '') . ' '
            . ($property['description'] ?? '');

        if (preg_match('/\b([A-Z]{1,2})([0-9][A-Z0-9]?)\s*[0-9][A-Z]{2}\b/i', $haystack, $m)) {
            return strtoupper($m[1]);
        }

        // Districts are written on their own far more often than full postcodes
        // ("Mile End E3", "Grays RM17"), so accept those too.
        if (preg_match('/\b([A-Z]{1,2})([0-9]{1,2})\b(?!\s*[0-9])/', strtoupper($haystack), $m)) {
            $area = $m[1];
            if (isset(self::INNER[$area]) || isset(self::OUTER[$area])) {
                return $area;
            }
        }

        return null;
    }

    protected static function fromCoordinates(array $property): ?string
    {
        $lat = $property['latitude'] ?? null;
        $lng = $property['longitude'] ?? null;

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        [$cLat, $cLng] = (array) config('transport.index.centre', [51.5074, -0.1278]);

        $miles = PropertyClassifier::milesBetween((float) $cLat, (float) $cLng, (float) $lat, (float) $lng);
        if ($miles <= 2) {
            return 'central';
        }

        // Latitude and longitude degrees are not the same distance apart, so
        // compare them in miles before deciding which way is dominant.
        $north = ((float) $lat - (float) $cLat) * 69.0;
        $east = ((float) $lng - (float) $cLng) * 43.0;

        $vertical = abs($north) > abs($east) * 2;
        $horizontal = abs($east) > abs($north) * 2;

        if ($vertical) {
            return $north > 0 ? 'north' : 'south';
        }

        if ($horizontal) {
            return $east > 0 ? 'east' : 'west';
        }

        return ($north > 0 ? 'north' : 'south') . '_' . ($east > 0 ? 'east' : 'west');
    }
}
