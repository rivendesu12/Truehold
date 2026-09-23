<?php

namespace App\Services;

/**
 * Partner agencies' bank details and tenant reference forms, for Sigou to
 * hand an agent ("ap bank details", "how do I pay horizon").
 *
 * Kept in a private file on the server, never in the repository (which is
 * public): storage/app/private/agency-bank-details.json, shaped as
 *   {"agencies": [{"name": "AP Horizon", "aliases": ["horizon"],
 *     "accounts": [{"company", "bank", "account_name", "sort_code",
 *       "account_number", "bic", "iban", "reference"}],
 *     "forms": [{"label", "url"}]}]}
 * Agents only: the endpoint that serves it requires a login.
 */
class AgencyBankDetails
{
    public function all(): array
    {
        $path = (string) config('truehold.agency_bank_details');
        if ($path === '' || ! is_readable($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data['agencies'] ?? null) ? $data['agencies'] : [];
    }

    /** The agency an agent means, by its name or any alias. */
    public function find(?string $name): ?array
    {
        $want = AgencyDirectory::key((string) $name);
        if ($want === '') {
            return null;
        }

        $keys = fn (array $a) => array_map([AgencyDirectory::class, 'key'], array_merge([(string) ($a['name'] ?? '')], (array) ($a['aliases'] ?? [])));

        foreach ($this->all() as $a) {
            if (in_array($want, $keys($a), true)) {
                return $a;
            }
        }
        foreach ($this->all() as $a) {
            foreach ($keys($a) as $key) {
                if ($key !== '' && (preg_match('/(^| )' . preg_quote($want, '/') . '( |$)/', $key)
                    || preg_match('/(^| )' . preg_quote($key, '/') . '( |$)/', $want))) {
                    return $a;
                }
            }
        }

        return null;
    }
}
