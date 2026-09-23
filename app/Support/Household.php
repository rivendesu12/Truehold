<?php

namespace App\Support;

/**
 * Who a tenant would be living with, as one line for an agent: "3 flatmates
 * · 3 females, 3 males · aged 22 to 31 · professionals". From the advert's
 * "Current household" block, or an agency's sheet (count and ages only).
 * Never anyone's name.
 */
class Household
{
    public static function summary(array $p): ?string
    {
        $h = (array) ($p['household'] ?? []);
        $count = $h['count'] ?? (is_numeric($p['housemates'] ?? null) ? (int) $p['housemates'] : null);

        $parts = [];
        if ($count !== null) {
            $parts[] = $count === 0
                ? (($h['source'] ?? null) === 'agency sheet' ? 'no other tenants on the agency sheet' : 'no flatmates yet')
                : $count . ' ' . ($count === 1 ? 'flatmate' : 'flatmates');
        }
        if (! empty($h['gender'])) {
            $parts[] = strtolower((string) $h['gender']);
        }
        if (! empty($h['ages'])) {
            $parts[] = 'aged ' . $h['ages'];
        }
        $job = strtolower(trim((string) ($h['occupation'] ?? '')));
        if ($job !== '' && $job !== 'other') {
            $parts[] = $job;
        }

        return $parts ? implode(' · ', $parts) : null;
    }

    /** Whether a listing is the property an agent named ("netherby house"). */
    public static function isProperty(array $p, string $name): bool
    {
        $norm = fn (string $s) => trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9 ]+/', ' ', strtolower($s))));
        $words = array_filter(explode(' ', $norm($name)), fn ($w) => ! in_array($w, ['the', 'at', 'in', 'on', 'of', 'room', 'rooms', 'flat'], true));
        if (! $words) {
            return false;
        }
        $hay = ' ' . $norm(implode(' ', array_filter([
            $p['source_property'] ?? null, $p['title'] ?? null, $p['address'] ?? null,
        ], 'is_string'))) . ' ';

        foreach ($words as $w) {
            if (! str_contains($hay, ' ' . $w . ' ')) {
                return false;
            }
        }

        return true;
    }
}
