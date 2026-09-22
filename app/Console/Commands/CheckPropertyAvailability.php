<?php

namespace App\Console\Commands;

use App\Services\ScrapedListingsApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Checks each listing's source advert for whether it is still taking enquiries.
 *
 * The feed's status field does not track lettings, so a let room stays visible
 * as status=available indefinitely. The advert itself does say, via the string
 * "not currently accepting applications", so that is what we check.
 */
class CheckPropertyAvailability extends Command
{
    protected $signature = 'properties:check-availability
                            {--limit=0 : Only check this many listings (0 = all)}
                            {--delay=1000 : Milliseconds to wait between requests}
                            {--stale-hours=20 : Skip listings checked more recently than this}';

    protected $description = 'Check source adverts for whether they are still accepting applications';

    private const MARKER = 'not currently accepting applications';

    private const UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 '
                     . '(KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36';

    public function handle(ScrapedListingsApiService $feed): int
    {
        $limit = (int) $this->option('limit');
        $delayMs = max(0, (int) $this->option('delay'));
        $staleHours = max(0, (int) $this->option('stale-hours'));

        // Read straight from the API so this does not depend on, or get filtered
        // by, the availability rules it is itself feeding.
        $listings = $feed->getAllPropertiesUnfiltered()
            ->filter(fn ($p) => ! empty($p['url']) && ! empty($p['id']))
            ->values();

        if ($staleHours > 0) {
            $fresh = DB::table('property_availability')
                ->where('checked_at', '>=', now()->subHours($staleHours))
                ->pluck('listing_id')
                ->all();
            $freshSet = array_flip($fresh);
            $listings = $listings->reject(fn ($p) => isset($freshSet[(string) $p['id']]))->values();
        }

        if ($limit > 0) {
            $listings = $listings->take($limit);
        }

        $total = $listings->count();
        if ($total === 0) {
            $this->info('Nothing to check.');
            return self::SUCCESS;
        }

        $this->info("Checking {$total} adverts (delay {$delayMs}ms)...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $unavailable = 0;
        $errors = 0;

        foreach ($listings as $property) {
            $accepting = true;
            $status = null;

            try {
                $response = Http::withHeaders(['User-Agent' => self::UA])
                    ->timeout(25)
                    ->get($property['url']);

                $status = $response->status();

                if ($status === 404 || $status === 410) {
                    // Advert withdrawn entirely.
                    $accepting = false;
                } elseif ($response->successful()) {
                    $accepting = ! str_contains(strtolower($response->body()), self::MARKER);
                } else {
                    // Any other response (rate limit, 5xx) is inconclusive:
                    // leave the listing visible rather than hide it wrongly.
                    $errors++;
                    $bar->advance();
                    if ($delayMs > 0) {
                        usleep($delayMs * 1000);
                    }
                    continue;
                }
            } catch (\Throwable $e) {
                $errors++;
                Log::warning('Availability check failed', [
                    'listing' => $property['id'],
                    'error' => $e->getMessage(),
                ]);
                $bar->advance();
                if ($delayMs > 0) {
                    usleep($delayMs * 1000);
                }
                continue;
            }

            if (! $accepting) {
                $unavailable++;
            }

            DB::table('property_availability')->updateOrInsert(
                ['listing_id' => (string) $property['id']],
                [
                    'url' => $property['url'],
                    'accepting_applications' => $accepting,
                    'http_status' => $status,
                    'checked_at' => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $bar->advance();
            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Checked {$total}. Not accepting applications: {$unavailable}. Inconclusive: {$errors}.");

        $feed->clearCache();

        return self::SUCCESS;
    }
}
