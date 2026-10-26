<?php

use App\Mail\CitizenUpdateMail;
use App\Services\Notifications\ExpiryAlerts;
use App\Services\Notifications\NotificationMail;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Mail;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mysql' || ! str_ends_with(config('database.connections.mysql.database'), '_testing')) {
    exit(2);
}
try {
    Mail::fake();
    config(['citizen_notifications.expiry_offsets' => [30, 7], 'citizen_notifications.expired_policy' => 'skip', 'citizen_notifications.mail_enabled' => $argv[1] === 'mail', 'mail.default' => 'smtp']);
    while (microtime(true) < (float) $argv[3]) {
        usleep(1000);
    }
    if ($argv[1] === 'mail') {
        app(NotificationMail::class)->send($argv[2]);
        $count = Mail::sent(CitizenUpdateMail::class)->count();
    } else {
        $count = app(ExpiryAlerts::class)->run();
    }
    echo json_encode(['count' => $count]);
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error));
    exit(1);
}
