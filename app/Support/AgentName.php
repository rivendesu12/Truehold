<?php

namespace App\Support;

/**
 * "giacomo", "ema", "sign as alex" all mean one of the team: settle them on
 * the spelling in config('truehold.agents') so the signature is consistent.
 * A name not on the list is kept as typed (tidied), since anyone can sign.
 */
final class AgentName
{
    public static function canonical(?string $name): ?string
    {
        $name = trim(preg_replace('/\s+/', ' ', (string) $name));
        if ($name === '') {
            return null;
        }

        $lower = mb_strtolower($name);
        $team = config('truehold.agents', []);

        foreach ($team as $agent) {
            if (mb_strtolower($agent) === $lower) {
                return $agent;
            }
        }

        // A clear prefix of exactly one teammate: "ema" is Emanuela.
        $matches = array_values(array_filter($team, fn ($a) => mb_strlen($lower) >= 3 && str_starts_with(mb_strtolower($a), $lower)));
        if (count($matches) === 1) {
            return $matches[0];
        }

        return mb_convert_case($name, MB_CASE_TITLE);
    }
}
