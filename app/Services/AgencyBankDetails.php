<?php

namespace App\Services;

/**
 * Partner agencies' bank details and tenant reference forms, for Sigou to
 * hand an agent ("ap bank details", "how do I pay horizon").
 *
 * Kept in a private file on the server, never in the repository (which is
 * public): storage/app/private/agency-bank-details.json, shaped as
 *   {"agencies": [{"name": "Horizon Dreams", "group": "AP Horizon",
 *     "aliases": ["horizon"],
 *     "accounts": [{"bank", "account_name", "sort_code", "account_number",
 *       "bic", "iban", "reference"}],
 *     "forms": [{"label", "url", "note"}]}]}
 * Each company that takes payment is its own entry (AP Real Estate and
 * Horizon Dreams bank separately; so do JMS and FENIX). Asking for the group
 * ("ap horizon", "javier") gives every company in it.
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

    /**
     * The company an agent means, by name or alias; or, asked for a group,
     * all of its companies as one card.
     */
    public function find(?string $name): ?array
    {
        $want = AgencyDirectory::plainKey((string) $name);
        if ($want === '') {
            return null;
        }

        $all = $this->all();
        $keys = fn (array $a) => array_values(array_filter(array_map([AgencyDirectory::class, 'plainKey'],
            array_merge([(string) ($a['name'] ?? '')], (array) ($a['aliases'] ?? [])))));
        $group = fn (array $a) => AgencyDirectory::plainKey((string) ($a['group'] ?? ''));
        $words = fn (string $a, string $b) => $a !== '' && $b !== ''
            && (preg_match('/(^| )' . preg_quote($a, '/') . '( |$)/', $b) || preg_match('/(^| )' . preg_quote($b, '/') . '( |$)/', $a));

        // Exactly one company by that name.
        foreach ($all as $a) {
            if (in_array($want, $keys($a), true)) {
                return $this->card([$a]);
            }
        }
        // A group: "javier", "ap horizon".
        $members = array_values(array_filter($all, fn ($a) => $group($a) === $want));
        if ($members) {
            return $this->card($members, $members[0]['group']);
        }
        // Looser: a word of it ("jms lifestyle ltd", "the soreva account").
        foreach ($all as $a) {
            foreach ($keys($a) as $key) {
                if ($words($want, $key)) {
                    return $this->card([$a]);
                }
            }
        }
        $members = array_values(array_filter($all, fn ($a) => $words($want, $group($a))));

        return $members ? $this->card($members, $members[0]['group']) : null;
    }

    /** One card: every account labelled with its company, then the forms. */
    protected function card(array $companies, ?string $title = null): array
    {
        $accounts = [];
        $forms = [];
        foreach ($companies as $c) {
            foreach ((array) ($c['accounts'] ?? []) as $acc) {
                $accounts[] = ['company' => $c['name']] + $acc;
            }
            foreach ((array) ($c['forms'] ?? []) as $f) {
                $forms[] = $f + ['company' => $c['name']];
            }
        }

        return ['name' => $title ?? $companies[0]['name'], 'accounts' => $accounts, 'forms' => $forms];
    }
}
