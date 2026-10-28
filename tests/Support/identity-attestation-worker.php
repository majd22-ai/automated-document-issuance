<?php

use App\Models\IdentityCardAttestation;
use App\Models\User;
use App\Services\IdentityAttestations;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mysql' || ! str_ends_with(config('database.connections.mysql.database'), '_testing')) {
    exit(2);
}
// Local-only simulation is exercised exclusively after the isolated test DB guard.
$app['env'] = 'local';
config(['identity_card.simulation_enabled' => true]);
while (microtime(true) < (float) $argv[4]) {
    usleep(1000);
}
try {
    app(IdentityAttestations::class)->decide(User::findOrFail($argv[1]), IdentityCardAttestation::findOrFail($argv[2]), ['decision' => $argv[3], 'reason' => 'TEST-only concurrent decision']);
    echo json_encode(['result' => 'saved']);
} catch (HttpExceptionInterface $error) {
    if ($error->getStatusCode() !== 409) {
        exit(3);
    }
    echo json_encode(['result' => 'blocked']);
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error));
    exit(1);
}
