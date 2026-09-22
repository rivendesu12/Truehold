<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * "2026-10-23" is a database value, not something a person reads at a glance
 * on a phone. A date already passed means the room is free now, which the
 * card says separately, so that returns null.
 */
final class AvailableLabel
{
    public static function for(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || strtolower($value) === 'n/a') {
            return null;
        }

        if (preg_match('/\bnow\b|immediate/i', $value)) {
            return null;
        }

        try {
            $date = Carbon::parse($value)->startOfDay();
        } catch (\Throwable $e) {
            return $value; // free text the sheet wrote; show it as written
        }

        if ($date->lessThanOrEqualTo(Carbon::today())) {
            return null;
        }

        return 'From ' . $date->format($date->year === Carbon::today()->year ? 'j M' : 'j M Y');
    }
}
