<?php

namespace App\Console\Commands;

use App\Services\ScrapedListingsApiService;
use App\Services\SupplierPhotoService;
use Illuminate\Console\Command;

/**
 * Resolves every supplier photo folder ahead of time.
 *
 * Photographs for the spreadsheet-sourced rooms live in private Drive folders,
 * and resolving one is a couple of API calls. Doing that while someone is
 * waiting for the listings page meant a hundred calls on a cold cache and a
 * page that looked broken. The site now only reads the cache; this fills it.
 *
 * Runs before the hourly feed refresh, so the cache is warm by the time the
 * feed is rebuilt.
 */
class WarmSupplierPhotos extends Command
{
    protected $signature = 'photos:warm {--force : refetch folders already cached}';

    protected $description = 'Resolve supplier Drive photo folders into the cache';

    public function handle(ScrapedListingsApiService $feed, SupplierPhotoService $photos): int
    {
        $listings = $feed->getAllProperties();

        $folders = [];

        foreach ($listings as $listing) {
            // Soreva has both: a folder per room and one per property.
            foreach (array_filter([$listing['drive_room_folder'] ?? null, $listing['drive_folder_url'] ?? null]) as $folder) {
                // Keyed by folder and room, which is what the cache is keyed by.
                $folders[$folder . '|' . ($listing['source_room'] ?? '')] = [
                    'folder' => $folder,
                    'room' => $listing['source_room'] ?? null,
                ];
            }
        }

        if (! $folders) {
            $this->info('No supplier photo folders to warm.');
            return self::SUCCESS;
        }

        $this->info(count($folders) . ' folder/room combinations to check.');

        $fetched = 0;
        $cached = 0;
        $empty = 0;

        $bar = $this->output->createProgressBar(count($folders));
        $bar->start();

        foreach ($folders as $entry) {
            $already = $photos->cachedPhotosForRoom($entry['folder'], $entry['room']);

            if ($already !== null && ! $this->option('force')) {
                $cached++;
                $bar->advance();
                continue;
            }

            $ids = $photos->photosForRoom($entry['folder'], $entry['room'], (bool) $this->option('force'));

            if ($ids) {
                $fetched++;
            } else {
                $empty++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info(sprintf(
            '%d already cached, %d fetched, %d folders held no usable images.',
            $cached,
            $fetched,
            $empty
        ));

        // The feed caches the photo urls it built, so it has to be rebuilt for
        // anything newly fetched to appear.
        if ($fetched > 0) {
            $feed->clearCache();
            $this->line('Feed cache cleared so the new photographs appear.');
        }

        return self::SUCCESS;
    }
}
