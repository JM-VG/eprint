<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('stations:close-idle')->everyMinute()->withoutOverlapping();

Schedule::command('files:cleanup')->dailyAt('02:00')->withoutOverlapping();

// Repair derived values (counters, dangling references, commission amounts) and report the rest.
Schedule::command('firebase:reconcile --fix')->dailyAt('03:00')->withoutOverlapping();
