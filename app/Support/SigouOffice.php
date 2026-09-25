<?php

namespace App\Support;

/**
 * What Sigou knows about the office and the people in it: learned from the
 * team's WhatsApp groups, so it lives in a private file on the server and
 * never in this repository, which is public. Shaped as
 *   {"persona": "...", "jokes": {"key": ["joke", "/hook regex/"]},
 *    "small_talk": ["key"], "pokes": ["line"], "morning": ["line"]}
 * Missing or broken, Sigou just knows less.
 */
class SigouOffice
{
    private static ?array $data = null;

    private static function data(): array
    {
        if (self::$data === null) {
            $path = (string) config('truehold.sigou_office');
            $json = $path !== '' && is_readable($path) ? json_decode((string) file_get_contents($path), true) : null;
            self::$data = is_array($json) ? $json : [];
        }

        return self::$data;
    }

    /** Appended to the persona; the same on every call, so it caches. */
    public static function persona(): string
    {
        $text = trim((string) (self::data()['persona'] ?? ''));

        return $text === '' ? '' : "\nTHE OFFICE\n" . $text . "\n";
    }

    /** Running jokes about the crew: key => [joke, hook regex]. */
    public static function jokes(): array
    {
        return array_filter((array) (self::data()['jokes'] ?? []),
            fn ($j) => is_array($j) && count($j) === 2 && is_string($j[0]) && is_string($j[1]));
    }

    public static function smallTalk(): array
    {
        return array_values(array_filter((array) (self::data()['small_talk'] ?? []), 'is_string'));
    }

    /** Canned panel lines ("pokes", "morning"). */
    public static function lines(string $kind): array
    {
        return array_values(array_filter((array) (self::data()[$kind] ?? []), 'is_string'));
    }

    /** For tests. */
    public static function forget(): void
    {
        self::$data = null;
    }
}
