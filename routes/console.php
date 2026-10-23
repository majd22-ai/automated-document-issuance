<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

\Illuminate\Support\Facades\Schedule::command('documents:notify-expiry')->dailyAt('08:00')->withoutOverlapping(60);
\Illuminate\Support\Facades\Schedule::command('notifications:dispatch-mail')->everyMinute()->withoutOverlapping(10);
