<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * What Sigou has cost, day by day, from the token counts every call records.
 *
 *   php artisan assistant:usage          the last 7 days
 *   php artisan assistant:usage --days=30
 */
class AssistantUsage extends Command
{
    protected $signature = 'assistant:usage {--days=7}';

    protected $description = 'Calls, tokens and estimated cost of the search assistant per day';

    public function handle(): int
    {
        $price = config('services.openai.price');
        $rows = [];
        $total = 0.0;

        for ($i = (int) $this->option('days') - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $d = Cache::get('assistant_usage:' . $date);
            if (! $d) {
                continue;
            }
            // Cached input is billed at the cached rate, the rest at full rate;
            // reasoning tokens are already inside output.
            $cost = (($d['input'] - $d['cached']) * $price['input'] + $d['cached'] * $price['cached'] + $d['output'] * $price['output']) / 1_000_000;
            $total += $cost;
            $rows[] = [$date, $d['calls'], $d['input'], $d['cached'], $d['output'], $d['reasoning'],
                '$' . number_format($cost, 4), $d['calls'] ? '$' . number_format($cost / $d['calls'], 5) : '-'];
        }

        if (! $rows) {
            $this->info('No assistant calls recorded yet.');
            return self::SUCCESS;
        }

        $this->table(['day', 'calls', 'input', 'of which cached', 'output', 'of which thinking', 'cost', 'per call'], $rows);
        $this->info('Total: $' . number_format($total, 4) . ' (prices per 1M tokens: in $' . $price['input'] . ', cached $' . $price['cached'] . ', out $' . $price['output'] . ')');

        return self::SUCCESS;
    }
}
