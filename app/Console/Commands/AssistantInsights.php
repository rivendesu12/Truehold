<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * What agents actually do with Sigou, from the interaction log, to decide
 * what to improve next: which briefs find nothing, which areas are asked
 * for, which band of results gets opened.
 *
 *   php artisan assistant:insights            the last 7 days
 *   php artisan assistant:insights --days=30
 */
class AssistantInsights extends Command
{
    protected $signature = 'assistant:insights {--days=7}';

    protected $description = 'Summarise how agents use Sigou: kinds, empty searches, areas, clicks, speed, cost';

    public function handle(): int
    {
        $since = now()->subDays((int) $this->option('days'));
        $rows = DB::table('assistant_interactions')->where('created_at', '>=', $since)->get();

        if ($rows->isEmpty()) {
            $this->info('No Sigou interactions recorded in that period.');
            return self::SUCCESS;
        }

        $this->info($rows->count() . ' interactions by ' . $rows->pluck('user_id')->unique()->count() . ' logins, '
            . $rows->pluck('session_key')->unique()->count() . ' devices');

        $this->table(['kind', 'count'], $rows->groupBy('kind')->map->count()->sortDesc()
            ->map(fn ($n, $k) => [$k, $n])->values()->all());

        $searches = $rows->where('kind', 'search');
        if ($searches->isNotEmpty()) {
            $empty = $searches->where('best', 0);
            $this->info(sprintf(
                'Searches: %d, of which %d%% had no exact match (%d found nothing at all); %d%% were follow-ups.',
                $searches->count(),
                round(100 * $empty->count() / $searches->count()),
                $searches->filter(fn ($r) => $r->best + $r->other + $r->wild === 0)->count(),
                round(100 * $searches->where('refined', true)->count() / $searches->count()),
            ));

            $this->line('Briefs with no exact match (most recent first):');
            foreach ($empty->sortByDesc('created_at')->take(15) as $r) {
                $this->line('  - ' . mb_substr($r->query, 0, 90) . "  [other {$r->other}, wild {$r->wild}]");
            }

            $areas = $searches->map(function ($r) {
                $f = json_decode((string) $r->filters, true) ?: [];
                return $f['location'] ?? $f['near_landmark'] ?? $f['region'] ?? null;
            })->filter()->map(fn ($a) => ucwords(strtolower((string) $a)))->countBy()->sortDesc()->take(10);
            if ($areas->isNotEmpty()) {
                $this->table(['area asked for', 'searches'], $areas->map(fn ($n, $a) => [$a, $n])->values()->all());
            }
        }

        $clicks = $rows->flatMap(fn ($r) => json_decode((string) $r->clicks, true) ?: []);
        $this->table(['band opened', 'clicks'], collect(['best', 'other', 'wild'])
            ->map(fn ($b) => [$b, $clicks->where('band', $b)->count()])->all());

        $latency = $rows->pluck('latency_ms')->filter()->sort()->values();
        if ($latency->isNotEmpty()) {
            $this->info(sprintf('Speed: median %.1fs, slowest 10%% over %.1fs.',
                $latency[intdiv($latency->count(), 2)] / 1000,
                $latency[(int) floor($latency->count() * 0.9)] / 1000));
        }

        $price = config('services.openai.price');
        $cost = $rows->sum(function ($r) use ($price) {
            $u = json_decode((string) $r->usage, true) ?: [];
            return ((($u['input'] ?? 0) - ($u['cached'] ?? 0)) * $price['input'] + ($u['cached'] ?? 0) * $price['cached'] + ($u['output'] ?? 0) * $price['output']) / 1_000_000;
        });
        $this->info(sprintf('Model cost for these: $%.4f ($%.5f each).', $cost, $cost / max(1, $rows->count())));

        return self::SUCCESS;
    }
}
