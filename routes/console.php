<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Schedule
|--------------------------------------------------------------------------
|
| Keeping listings current is the whole job: an agent sending a client a room
| that went last week is worse than showing them nothing. The cadence below is
| set by how fast each source actually changes.
|
| Needs one cron entry on the server:
|   * * * * * cd /var/www/truehold && php artisan schedule:run >> /dev/null 2>&1
|
*/

// The feed and the supplier sheets move through the day, so the cache is
// dropped hourly and rebuilt by the next visitor.
Schedule::command('properties:clear-cache')
    ->hourly()
    ->withoutOverlapping();

// Photographs for the spreadsheet-sourced rooms live in private Drive folders,
// and resolving them is slow. Warming the cache here, on the half hour, keeps
// that work off the page request that would otherwise have waited for it.
Schedule::command('photos:warm')
    ->hourlyAt(50)
    ->withoutOverlapping()
    ->runInBackground();

// Re-checks source adverts for "not currently accepting applications", which
// is the only reliable signal that a room has gone. Slower cadence because it
// fetches every advert.
Schedule::command('properties:check-availability')
    ->everySixHours()
    ->withoutOverlapping()
    ->runInBackground();

// Stations, zones and lines change when the network does — a few times a year.
Schedule::command('transport:build-stations')
    ->monthlyOn(1, '03:00')
    ->withoutOverlapping();

// Incremental by design: pairs already on file are skipped, so this only does
// work when listings appear near a station we have never seen. Runs after the
// station rebuild so a new station is picked up the same night.
Schedule::command('transport:build-journeys')
    ->weeklyOn(1, '03:30')
    ->withoutOverlapping()
    ->runInBackground();

// Sigou's wildcards: free-to-contact SpareRoom agent listings, zones 1-3.
// Nightly, off-peak, a few seconds between requests, only new adverts opened;
// listings not seen for three days drop out, so a missed run changes nothing.
Schedule::command('market:crawl')
    ->dailyAt('02:10')
    ->withoutOverlapping()
    ->runInBackground();

// Zoopla enquiries from the Zoho mailbox into the leads sheet. A lead is
// worth most in its first minutes, hence the short interval; each run looks
// back three days, so a failed one costs nothing.
Schedule::command('zoopla:leads')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Sigou's interaction log keeps six months.
Schedule::call(fn () => \Illuminate\Support\Facades\DB::table('assistant_interactions')
    ->where('created_at', '<', now()->subDays(180))->delete())
    ->monthlyOn(2, '04:00');
