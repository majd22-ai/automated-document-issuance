<?php

use App\Models\CitizenServiceRequest;
use App\Models\User;
use App\Services\Integrations\IntegrationManager;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\TestVerificationProvider;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mysql' || ! str_ends_with(config('database.connections.mysql.database'), '_testing')) {
    exit(2);
}
try {
    config(['integrations.providers.test' => ['label' => 'TEST ONLY', 'enabled' => true, 'approved' => true, 'adapter' => TestVerificationProvider::class, 'base_url' => 'https://verification.example.test', 'allowed_host' => 'verification.example.test', 'endpoints' => ['identity' => '/verify'], 'retries' => 0]]);
    Http::preventStrayRequests();
    Http::fake(function ($http) {
        usleep(900000);

        return Http::response(['status' => 'verified', 'correlation' => $http['correlation'], 'subject' => $http['subject']]);
    });
    while (microtime(true) < (float) $argv[4]) {
        usleep(1000);
    }
    try {
        $attempt = app(IntegrationManager::class)->start(User::findOrFail($argv[1]), CitizenServiceRequest::findOrFail($argv[2]), 'identity', $argv[3]);
        $status = 200;
        $id = $attempt->id;
    } catch (HttpException $e) {
        if ($e->getStatusCode() !== 409) {
            throw $e;
        }$status = 409;
        $id = null;
    }
    echo json_encode(['id' => $id, 'status' => $status, 'calls' => Http::recorded()->count()]);
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error));
    exit(1);
}
