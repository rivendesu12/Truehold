<?php

namespace App\Support;

/**
 * Normalises what a listing actually offers into four facts the filters and
 * the assistant can rely on.
 *
 * The feed answers this inconsistently. Room adverts carry room_count (rooms
 * offered), total_rooms (size of the shared house) and room1_type, but every
 * whole-flat advert leaves all three blank and states the size only in its
 * title — "1-Bed Flat in N1", "2Beds Apartment", "Luxury 3-Bedroom House",
 * "Studio available in Leyton". Without this, a search for a two-bed flat
 * matched nothing at all, which is why the bedroom filter had to be dropped
 * so often.
 *
 * The distinction that matters: in a room advert "5-bedroom flat" is the size
 * of the houseshare, not something the tenant is renting. So bedrooms is only
 * set for a whole property, and a houseshare's size is kept separately.
 */
final class RoomFacts
{
    private const WORD_NUMBERS = [
        'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5,
        'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10,
    ];

    /**
     * @return array{bedrooms: ?int, house_size: ?int, rooms_available: ?int, room_type: ?string, bedrooms_from: ?string}
     */
    public static function extract(array $property): array
    {
        $bucket = PropertyClassifier::bucket($property);
        $text = self::text($property);

        $facts = [
            'bedrooms' => null,
            'house_size' => null,
            'rooms_available' => self::intOrNull($property['room_count'] ?? null),
            'room_type' => self::roomType($property, $text),
            'bedrooms_from' => null,
        ];

        // A studio has no separate bedroom. Saying "0" rather than leaving it
        // blank is what makes "studio, not a one-bed" answerable.
        if ($bucket === 'studio') {
            $facts['bedrooms'] = 0;
            $facts['bedrooms_from'] = 'studio';

            return $facts;
        }

        $stated = self::statedBedrooms($text);

        if ($bucket === 'full_property') {
            $facts['bedrooms'] = self::intOrNull($property['total_rooms'] ?? null) ?? $stated;
            $facts['bedrooms_from'] = $facts['bedrooms'] === null
                ? null
                : (empty($property['total_rooms']) ? 'title' : 'feed');

            return $facts;
        }

        // A room: the dwelling's bedroom count is the houseshare's size, which
        // the feed usually gives and the advert text otherwise mentions.
        $facts['house_size'] = self::intOrNull($property['total_rooms'] ?? null)
            ?? self::intOrNull($property['housemates'] ?? null)
            ?? $stated;

        return $facts;
    }

    protected static function text(array $property): string
    {
        return trim(
            (string) ($property['title'] ?? '') . ' '
            . (string) ($property['description'] ?? '')
        );
    }

    /**
     * Bedrooms stated in the advert text: "1-Bed", "2Beds", "3-Bedroom",
     * "three bedroom". The digit has to sit against the word — "2 Spacious
     * Double Bedroom" is two rooms on offer, not a two-bed flat, and "Zone
     * 2-Next to shops" is neither.
     */
    protected static function statedBedrooms(string $text): ?int
    {
        if (preg_match('/\b(\d{1,2})\s?-?\s?bed(?:room|rooms|s|roomed)?\b/i', $text, $m)) {
            $n = (int) $m[1];
            if ($n >= 1 && $n <= 12) {
                return $n;
            }
        }

        $words = implode('|', array_keys(self::WORD_NUMBERS));
        if (preg_match('/\b(' . $words . ')\s?-?\s?bed(?:room|rooms|s|roomed)?\b/i', $text, $m)) {
            return self::WORD_NUMBERS[strtolower($m[1])] ?? null;
        }

        return null;
    }

    /**
     * double / single / ensuite / twin / studio. The feed's room1_type is
     * trusted first, except for its "(now let)" placeholder.
     */
    protected static function roomType(array $property, string $text): ?string
    {
        $stated = strtolower(trim((string) ($property['room1_type'] ?? '')));
        $stated = preg_replace('/[^a-z]/', '', $stated);

        if (in_array($stated, ['double', 'single', 'ensuite', 'twin', 'studio'], true)) {
            return $stated;
        }

        if (preg_match('/\bstudio\b/i', $text)) {
            return 'studio';
        }
        if (preg_match('/\ben[\s-]?suite\b/i', $text)) {
            return 'ensuite';
        }
        if (preg_match('/\btwin\s+(?:bed)?room\b/i', $text)) {
            return 'twin';
        }
        if (preg_match('/\bdouble\b/i', $text)) {
            return 'double';
        }
        // "single occupancy" is a rule about who may live there, not the size
        // of the room, so require the noun.
        if (preg_match('/\bsingle\s+(?:bed)?room\b/i', $text)) {
            return 'single';
        }

        return null;
    }

    protected static function intOrNull($value): ?int
    {
        if (is_numeric($value)) {
            $n = (int) $value;
            return $n > 0 ? $n : null;
        }

        return null;
    }
}
