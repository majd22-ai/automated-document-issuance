<?php

use App\Enums\ServiceRequestStatus;
use App\Models\CitizenServiceRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mysql' || ! str_ends_with(config('database.connections.mysql.database'), '_testing')) {
    exit(2);
}
try {
    $actor = User::findOrFail($argv[1]);
    $request = CitizenServiceRequest::findOrFail($argv[2]);
    $context = json_decode(base64_decode($argv[4], true), true, 16, JSON_THROW_ON_ERROR);
    while (microtime(true) < (float) $argv[5]) {
        usleep(1000);
    }
    $request->transitionTo(ServiceRequestStatus::from($argv[3]), $actor, null, $context);
    echo json_encode(['result' => 'saved']);
} catch (ValidationException|AuthorizationException) {
    echo json_encode(['result' => 'blocked']);
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error));
    exit(1);
}
