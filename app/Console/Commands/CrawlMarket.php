<?php

namespace App\Console\Commands;

use App\Services\MarketListingsService;
use Illuminate\Console\Command;

/**
 * Refresh the wildcards: free-to-contact rooms from SpareRoom letting agents,
 * zones 1-3, with a number. Nightly; polite by design (a few seconds between
 * requests, one at a time), and it stops rather than hammers if refused.
 * The first run opens every agent advert (hours); later ones only new ones.
 *
 *   php artisan market:crawl
 *   php artisan market:crawl --pages=3 --adverts=5 --delay=2000   a trial
 */
class CrawlMarket extends Command
{
    protected $signature = 'market:crawl {--pages=1500} {--adverts=5000} {--delay=2500}';

    protected $description = 'Crawl free-to-contact SpareRoom agent listings for Sigou\'s wildcards';

    public function handle(MarketListingsService $market): int
    {
        $stats = $market->crawl(
            (int) $this->option('pages'),
            (int) $this->option('adverts'),
            (int) $this->option('delay'),
            function (string $stage, int $n, int $kept) {
                $this->output->write("\r  {$stage} {$n}, kept {$kept}   ");
            },
        );
        $this->newLine();

        $this->table(['rent bands', 'still full', 'pages', 'cards', 'agent adverts', 'already read', 'opened', 'kept', 'no number', 'outside z1-3', 'failed', 'stopped'], [[
            $stats['bands'], $stats['capped'], $stats['pages'], $stats['cards'], $stats['agents'], $stats['known'], $stats['fetched'],
            $stats['saved'], $stats['no_number'], $stats['outside'], $stats['failed'], $stats['stopped'] ?? '-',
        ]]);
        $this->info('Wildcards now held: ' . $market->all()->count());

        return $stats['stopped'] ? self::FAILURE : self::SUCCESS;
    }
}
