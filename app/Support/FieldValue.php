<?php

namespace App\Support;

/**
 * Reads the feed's inconsistent yes/no and numeric columns.
 *
 * These columns are scraped from adverts written by hundreds of different
 * people, so "yes" arrives as Yes, YES, "All included", "Some", true, 1 — and
 * couples_ok contains stray rents like "1350" where a scrape mis-aligned.
 * Anything that is not recognisably an answer is treated as no answer at all,
 * which is the difference between hiding a listing and admitting we don't know.
 */
final class FieldValue
{
    private const YES = ['yes', 'y', 'true', '1', 'all included', 'included', 'some', 'partial', 'allowed', 'ok'];
    private const NO = ['no', 'n', 'false', '0', 'not included', 'none', 'not allowed'];

    /** true / false / null when the field does not say. */
    public static function tribool($value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        $text = strtolower(trim((string) $value));

        if (in_array($text, self::YES, true)) {
            return true;
        }

        if (in_array($text, self::NO, true)) {
            return false;
        }

        return null;
    }

    /** First integer in a field like "6 months", "0", "£400". */
    public static function number($value): ?int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_string($value) && preg_match('/-?\d+/', $value, $m)) {
            return (int) $m[0];
        }

        return null;
    }

    /** "None" means no minimum term, not a zero-month one. */
    public static function months($value): ?int
    {
        $text = strtolower(trim((string) $value));

        if ($text === '' || $text === 'none' || $text === 'n/a') {
            return 0;
        }

        return self::number($value);
    }

    public static function date($value): ?\Carbon\Carbon
    {
        if (empty($value)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse((string) $value);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
