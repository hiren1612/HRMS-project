<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// QA H1: sweep up sessions nobody clocked out of, just after midnight.
// Requires a cron entry on the server:
//   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
Schedule::command('attendance:close-stale')->dailyAt('00:15');
