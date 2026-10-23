<?php

use App\Models\CitizenServiceRequest;
use App\Models\User;
use App\Services\Operations\AppointmentBookings;
use App\Services\Operations\RequestCharges;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mysql' || ! str_ends_with(config('database.connections.mysql.database'), '_testing')) {
    exit(2);
}
try {
    $actor = User::findOrFail($argv[2]);
    $request = CitizenServiceRequest::findOrFail($argv[3]);
    $payload = json_decode(base64_decode($argv[4], true), true, 16, JSON_THROW_ON_ERROR);
    while (microtime(true) < (float) $argv[5]) {
        usleep(1000);
    }
    if ($argv[1] === 'booking') {
        $app->make(AppointmentBookings::class)->book($actor, $request, $payload['slot_id']);
    } else {
        $app->make(RequestCharges::class)->record($actor, $request, $payload);
    }
    echo json_encode(['result' => 'saved']);
} catch (ValidationException) {
    echo json_encode(['result' => 'blocked']);
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error));
    exit(1);
}
