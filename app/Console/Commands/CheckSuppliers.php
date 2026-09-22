<?php

namespace App\Console\Commands;

use App\Services\SorevaSheetService;
use App\Services\SpareRoomAdvertService;
use Illuminate\Console\Command;

/**
 * Shows what the directly-sourced suppliers are actually returning, so a
 * silent zero can be told apart from a supplier with nothing available.
 */
class CheckSuppliers extends Command
{
    protected $signature = 'suppliers:check {--crawl : Re-crawl SpareRoom rather than using the cache}';

    protected $description = 'Report what Soreva and the SpareRoom advertisers are returning';

    public function handle(SorevaSheetService $soreva, SpareRoomAdvertService $spareroom): int
    {
        $this->info('Soreva Living sheet');
        if (! SorevaSheetService::isConfigured()) {
            $this->warn('  not configured');
        } else {
            $rows = $soreva->getAllProperties();
            $this->line("  {$rows->count()} available rooms");
            foreach ($rows->take(6) as $r) {
                $this->line(sprintf(
                    '    %-34s GBP %-6s %-10s %s',
                    mb_substr($r['title'], 0, 34),
                    $r['price'],
                    $r['postcode'],
                    $r['latitude'] ? 'mapped' : 'NOT MAPPED'
                ));
            }
        }

        $this->newLine();
        $this->info('SpareRoom advertisers');

        if (! SpareRoomAdvertService::isConfigured()) {
            $this->warn('  none configured');
            return self::SUCCESS;
        }

        if ($this->option('crawl')) {
            $spareroom->clearCache();
            $rows = $spareroom->crawlAll(function ($name, $advert, $outcome) {
                $this->line(sprintf('    %-12s %-9s %s', $name, $advert, $outcome));
            });
        } else {
            $rows = $spareroom->getAllProperties();
        }

        $this->line("  {$rows->count()} available rooms");

        foreach ($rows->groupBy('agent_name') as $name => $group) {
            $this->line(sprintf('    %-14s %d rooms', $name, $group->count()));
            foreach ($group->take(3) as $r) {
                $this->line(sprintf(
                    '      %-38s GBP %-6s %-6s %s',
                    mb_substr($r['title'], 0, 38),
                    $r['price'],
                    $r['postcode'] ?? '-',
                    $r['latitude'] ? 'mapped' : 'NOT MAPPED'
                ));
            }
        }

        return self::SUCCESS;
    }
}
