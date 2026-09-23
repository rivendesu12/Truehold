<?php

namespace App\Console\Commands;

use App\Services\MarketListingsService;
use Illuminate\Console\Command;

/**
 * Refresh the wildcards: free-to-contact rooms from SpareRoom letting agents.
 * Scheduled every other night; polite by design (a few seconds between
 * requests), and it stops rather than hammers if SpareRoom refuses.
 *
 *   php artisan market:crawl                 the full run (~40 pages)
 *   php artisan market:crawl --pages=3 --adverts=5 --delay=2000   a trial
 */
class CrawlMarket extends Command
{
    protected $signature = 'market:crawl {--pages=100} {--adverts=300} {--delay=2500}';

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

        $this->table(['search pages', 'cards', 'agent adverts', 'saved', 'failed', 'stopped'], [[
            $stats['pages'], $stats['cards'], $stats['agents'], $stats['saved'], $stats['failed'], $stats['stopped'] ?? '-',
        ]]);
        $this->info('Wildcards now held: ' . $market->all()->count());

        return $stats['stopped'] ? self::FAILURE : self::SUCCESS;
    }
}
