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

    /**
     * Does this listing actually offer an en-suite?
     *
     * A substring search over the description does not answer this. One advert
     * read "one main bathroom shared between four residents, plus one ensuite
     * room in the house" — the en-suite is a different room, and the room
     * being advertised is a double with a shared bathroom. It was being
     * returned for "give me ensuite" anyway.
     *
     * So the stated room type wins whenever there is one: a room the feed
     * calls a double is not an en-suite, whatever the prose mentions. Only
     * where no type is stated does the wording get a say, and then only when
     * it is not plainly describing someone else's bathroom.
     */
    public static function isEnsuite(array $property): bool
    {
        $stated = preg_replace('/[^a-z]/', '', strtolower(trim(
            (string) ($property['room_type'] ?? $property['room1_type'] ?? '')
        )));

        if ($stated === 'ensuite') {
            return true;
        }

        if (in_array($stated, ['double', 'single', 'twin', 'studio'], true)) {
            return false;
        }

        // Some sources put it in the type column instead.
        if (self::mentionsEnsuite(strtolower((string) ($property['property_type'] ?? '')))) {
            return true;
        }

        // The advertiser's own headline is about the room they are letting.
        if (self::mentionsEnsuite(strtolower((string) ($property['title'] ?? '')))) {
            return true;
        }

        $description = strtolower((string) ($property['description'] ?? ''));

        return self::mentionsEnsuite($description) && ! self::ensuiteBelongsElsewhere($description);
    }

    protected static function mentionsEnsuite(string $text): bool
    {
        return (bool) preg_match('/\ben[\s-]?suite\b/', $text);
    }

    /**
     * Wording that shows the en-suite is another room's, or that this room
     * shares a bathroom.
     */
    protected static function ensuiteBelongsElsewhere(string $text): bool
    {
        $patterns = [
            // "one ensuite room in the house", "another en-suite", "1 ensuite"
            '/\b(?:one|two|three|another|other|\d+)\s+en[\s-]?suite\b/',
            // "the other rooms are ensuite"
            '/\bother\s+rooms?\b[^.]{0,40}\ben[\s-]?suite\b/',
            // this room's own bathroom is shared
            '/\bshared?\s+bathroom\b/',
            '/\bbathroom\b[^.]{0,30}\bshared\b/',
            '/\bshare\s+(?:a|the|one)?\s*bathroom\b/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
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
