<?php

use App\Models\IdentityCardForm;
use App\Models\User;
use App\Services\FingerprintService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
// Validate the real connection before enabling the protected local demonstration.
if (! $app->environment('testing') || DB::getDriverName() !== 'mysql'
    || ! str_ends_with(DB::connection()->getDatabaseName(), '_testing')) {
    exit(2);
}
$app->instance('env', 'local');
config(['identity_card.simulation_enabled' => true, 'identity_card.fingerprint.simulation_enabled' => true]);
try {
    $data = json_decode(base64_decode($argv[4], true), true, flags: JSON_THROW_ON_ERROR);
    if (! in_array($argv[3], ['capture', 'administer'], true)) {
        exit(2);
    }
    while (microtime(true) < (float) $argv[5]) {
        usleep(1000);
    }
    try {
        $record = app(FingerprintService::class)->{$argv[3]}(User::findOrFail($argv[1]), IdentityCardForm::findOrFail($argv[2]), $data);
        echo json_encode(['result' => 'saved', 'id' => $record->id]);
    } catch (HttpExceptionInterface $error) {
        if ($error->getStatusCode() !== 409) {
            throw $error;
        }
        echo json_encode(['result' => 'blocked', 'status' => 409]);
    }
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error));
    exit(1);
}
