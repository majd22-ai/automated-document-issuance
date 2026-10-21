<?php

use App\Models\GovernmentService;
use App\Models\User;
use App\Services\ServiceRequestSubmission;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mysql' || ! str_ends_with(config('database.connections.mysql.database'), '_testing')) {
    exit(2);
}
try {
    $citizen = User::findOrFail($argv[1]);
    $service = GovernmentService::findOrFail($argv[2]);
    $preview = json_decode(base64_decode($argv[4], true), true, 32, JSON_THROW_ON_ERROR);
    while (microtime(true) < (float) $argv[5]) {
        usleep(1000);
    }
    $request = $app->make(ServiceRequestSubmission::class)->submit($citizen, $service, $argv[3], $preview);
    echo json_encode(['result' => 'submitted', 'id' => $request->id]);
} catch (ValidationException) {
    echo json_encode(['result' => 'blocked']);
} catch (Throwable $exception) {
    fwrite(STDERR, get_class($exception));
    exit(1);
}
