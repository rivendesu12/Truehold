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
        $paying = $property['paying'] ?? null;

        if (is_string($paying)) {
            return strtolower(trim($paying)) === 'yes';
        }

        return (bool) $paying;
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
