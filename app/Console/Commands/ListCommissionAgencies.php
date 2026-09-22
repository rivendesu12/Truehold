<?php

namespace App\Console\Commands;

use App\Services\CommissionRates;
use App\Services\ScrapedListingsApiService;
use Illuminate\Console\Command;

/**
 * Prints the config key for every agency in the live feed, so real commission
 * rates can be entered without guessing how a name normalises.
 */
class ListCommissionAgencies extends Command
{
    protected $signature = 'commission:agencies {--paying : Only agencies that pay}';

    protected $description = 'List agencies in the feed with their config/commission.php keys';

    public function handle(CommissionRates $rates): int
    {
        $listings = app(ScrapedListingsApiService::class)->getAllProperties();

        $groups = [];

        foreach ($listings as $listing) {
            if ($this->option('paying') && ! $rates->pays($listing)) {
                continue;
            }

            $name = trim((string) ($listing['agent_name'] ?? $listing['landlord_name'] ?? 'unknown'));
            $key = $rates->normalise($name);

            $groups[$key]['key'] = $key;
            $groups[$key]['names'][$name] = true;
            $groups[$key]['count'] = ($groups[$key]['count'] ?? 0) + 1;
            $groups[$key]['pays'] = ($groups[$key]['pays'] ?? false) || $rates->pays($listing);
            $groups[$key]['configured'] = isset(config('commission.rates')[$key]);

            $value = $rates->value($listing);
            if ($value['amount'] !== null) {
                $groups[$key]['total'] = ($groups[$key]['total'] ?? 0) + $value['amount'];
            }
        }

        uasort($groups, fn ($a, $b) => $b['count'] <=> $a['count']);

        $this->table(
            ['config key', 'spelt in feed as', 'listings', 'pays', 'rate', 'est. value'],
            array_map(fn ($g) => [
                $g['key'],
                implode(' / ', array_keys($g['names'])),
                $g['count'],
                $g['pays'] ? 'yes' : 'no',
                $g['configured'] ? 'set' : ($g['pays'] ? 'not set' : '—'),
                isset($g['total']) ? 'GBP ' . number_format($g['total']) : '—',
            ], $groups)
        );

        $unset = array_filter($groups, fn ($g) => $g['pays'] && ! $g['configured']);

        if ($unset) {
            $this->newLine();
            $this->warn(count($unset) . ' paying agencies pay, but we do not know how much.');
            $this->line('They are flagged as commission-paying with no figure attached, which');
            $this->line('is all the feed tells us. To rank by what you actually earn, paste the');
            $this->line('real terms into config/commission.php — percent of the first month,');
            $this->line('or a flat fee per let:');
            $this->newLine();
            foreach ($unset as $g) {
                $this->line("    '{$g['key']}' => ['type' => 'percent', 'value' => ??],");
            }
        }

        return self::SUCCESS;
    }
}
