<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('documents:notify-expiry')->dailyAt('08:00')->withoutOverlapping(60);
Schedule::command('notifications:dispatch-mail')->everyMinute()->withoutOverlapping(10);

Artisan::command('registration:prune', function () {
    $count = DB::table('registration_attempts')->where('expires_at', '<=', now())->delete();
    $this->info("Expired registration attempts removed: {$count}");
})->purpose('Remove expired encrypted registration data');
Schedule::command('registration:prune')->hourly()->withoutOverlapping();
