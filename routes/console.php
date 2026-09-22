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
