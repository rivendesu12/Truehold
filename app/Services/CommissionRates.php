<?php

namespace App\Services;

/**
 * Turns the feed's yes/no commission flag into a number we can rank by.
 *
 * An agent asking to "prioritise commission" wants the listings worth most,
 * not an arbitrary order among everything that happens to pay. Rates live in
 * config/commission.php because they are commercial terms, not data any feed
 * gives us; where one is missing the default is used and the result is marked
 * `estimated` so the panel never states a guess as fact.
 */
class CommissionRates
{
    protected array $rates;
    protected array $aliases;
    protected float $default;

    public function __construct()
    {
        $this->rates = (array) config('commission.rates', []);
        $this->aliases = (array) config('commission.aliases', []);
        $this->default = (float) config('commission.default_percent', 0);
    }

    /**
     * "Cloudrooms Ltd", "Cloud Rooms" and "CLOUDROOMS  LTD." are one agency.
     */
    public function normalise(?string $name): string
    {
        $key = strtolower(trim((string) $name));
        $key = preg_replace('/\b(ltd|limited|llp|plc|inc)\b/', '', $key);
        $key = preg_replace('/[^a-z0-9 ]+/', ' ', $key);
        $key = trim(preg_replace('/\s+/', ' ', $key));

        return $this->aliases[$key] ?? $key;
    }

    public function pays(array $property): bool
    {
        // Named in config either way, that wins over whatever the feed says.
        if ($this->listed($property, 'commission.never_pay')) {
            return false;
        }
        if ($this->hasArrangement($property)) {
            return true;
        }
        // The office's agencies tab: a partner with a commission entered pays.
        $agency = app(AgencyDirectory::class)->find($property['agent_name'] ?? $property['landlord_name'] ?? null);
        if ($agency && ! empty($agency['commission'])) {
            return true;
        }

        $paying = $property['paying'] ?? null;

        if (is_string($paying) && trim($paying) !== '') {
            return strtolower(trim($paying)) === 'yes';
        }

        if (is_bool($paying) || is_numeric($paying)) {
            return (bool) $paying;
        }

        // The feed leaves this blank on every spreadsheet-sourced row, so our
        // own suppliers were invisible to a commission search. An agency we
        // have an arrangement with is named in config, not guessed at.
        return $this->hasArrangement($property);
    }

    /** Is this agency on the list of ones we know pay? */
    public function hasArrangement(array $property): bool
    {
        return $this->listed($property, 'commission.always_pay');
    }

    /** Is this listing's agency named in the given config list? */
    protected function listed(array $property, string $configKey): bool
    {
        $key = $this->normalise($property['agent_name'] ?? $property['landlord_name'] ?? null);
        if ($key === '') {
            return false;
        }

        foreach ((array) config($configKey, []) as $listed) {
            $listed = $this->normalise((string) $listed);
            if ($listed !== '' && preg_match('/(^| )' . preg_quote($listed, '/') . '( |$)/', $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * What this listing is worth to us, in pounds.
     *
     * @return array{pays: bool, amount: ?float, estimated: bool, basis: ?string}
     */
    public function value(array $property): array
    {
        if (! $this->pays($property)) {
            return ['pays' => false, 'amount' => null, 'estimated' => false, 'basis' => null];
        }

        $agency = $this->normalise($property['agent_name'] ?? $property['landlord_name'] ?? null);
        $rate = $this->rates[$agency] ?? null;
        $estimated = $rate === null;

        if ($rate === null) {
            if ($this->default <= 0) {
                return ['pays' => true, 'amount' => null, 'estimated' => true, 'basis' => null];
            }
            $rate = ['type' => 'percent', 'value' => $this->default];
        }

        $type = $rate['type'] ?? 'percent';
        $value = (float) ($rate['value'] ?? 0);

        if ($type === 'fixed') {
            return [
                'pays' => true,
                'amount' => round($value, 2),
                'estimated' => $estimated,
                'basis' => 'GBP ' . number_format($value) . ' per let',
            ];
        }

        $rent = $this->monthlyRent($property);
        if ($rent === null) {
            return ['pays' => true, 'amount' => null, 'estimated' => true, 'basis' => round($value) . '% of rent'];
        }

        return [
            'pays' => true,
            'amount' => round($rent * $value / 100, 2),
            'estimated' => $estimated,
            'basis' => round($value) . '% of the first month',
        ];
    }

    /** Attach the value to a listing so filters and sorting can use it. */
    public function annotate(array $property): array
    {
        $value = $this->value($property);

        $property['pays_commission'] = $value['pays'];
        $property['commission_value'] = $value['amount'];
        $property['commission_estimated'] = $value['estimated'];
        $property['commission_basis'] = $value['basis'];

        return $property;
    }

    protected function monthlyRent(array $property): ?float
    {
        $price = $property['price'] ?? null;

        if (is_string($price)) {
            $price = preg_replace('/[^0-9.]/', '', $price);
        }

        return is_numeric($price) && $price > 0 ? (float) $price : null;
    }
}
