<?php

use App\Models\User;
use App\Services\Registration\CitizenAccountActivation;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mysql' || ! str_ends_with(config('database.connections.mysql.database'), '_testing')) {
    exit(2);
}
config(['cache.default' => 'database']);
$app['env'] = 'local';
config(['registration.local_otp' => true, 'registration.activation_fingerprints' => true]);
try {
    $data = json_decode(base64_decode($argv[3]), true, flags: JSON_THROW_ON_ERROR);
    while (microtime(true) < (float) $argv[4]) {
        usleep(1000);
    }
    try {
        $record = app(CitizenAccountActivation::class)->confirm(User::findOrFail($argv[1]), User::findOrFail($argv[2]), $data);
        echo json_encode(['result' => 'confirmed', 'id' => $record->id]);
    } catch (ValidationException) {
        echo json_encode(['result' => 'blocked', 'id' => null]);
    }
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error));
    exit(1);
}
