<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PropertyGoogleSheetsService;
use App\Services\ScrapedListingsApiService;

class ClearPropertiesCache extends Command
{
    protected $signature = 'properties:clear-cache';
    protected $description = 'Clear the property feed caches (Harbor Ops API and Google Sheets)';

    public function handle(PropertyGoogleSheetsService $sheetsService, ScrapedListingsApiService $apiService)
    {
        $apiService->clearCache();
        $sheetsService->clearCache();

        // The directly-sourced suppliers cache separately, so clearing only
        // the feed would leave their stock an hour stale after a refresh.
        app(\App\Services\SupplierTargetsSheetService::class)->clearCache();
        app(\App\Services\SorevaSheetService::class)->clearCache();
        app(\App\Services\SpareRoomAdvertService::class)->clearCache();

        // Clearing and walking away leaves the next visitor to rebuild the
        // feed — which is exactly the wait this is supposed to prevent. Rebuild
        // it here, where taking a few seconds costs nobody anything.
        $count = $apiService->getAllProperties()->count();

        // The agencies tab (links, max age, commission) rides along hourly.
        $agencies = app(\App\Services\AgencyDirectory::class)->refresh();
        $this->line("Agency directory: {$agencies} agencies.");

        $this->info("Properties cache refreshed: {$count} listings.");

        return 0;
    }
}
