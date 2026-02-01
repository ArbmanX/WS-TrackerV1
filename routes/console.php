<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Clean up old activity logs (per config retention days)
Schedule::command('activitylog:clean')->daily();

// Prune old Pulse data (keeps 7 days by default)
Schedule::command('pulse:clear --force')->weekly();

// Prune old health check results
Schedule::command('health:delete-old-records')->daily();
