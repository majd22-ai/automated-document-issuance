<?php

use App\Models\CitizenServiceRequest;
use App\Models\User;
use App\Services\Issuance\IssueDocuments;
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
    config(['filesystems.disks.documents.root' => storage_path('framework/testing/issuance-race-'.$request->user_id)]);
    $data = json_decode(base64_decode($argv[3], true), true, 16, JSON_THROW_ON_ERROR);
    while (microtime(true) < (float) $argv[4]) {
        usleep(1000);
    }
    $document = app(IssueDocuments::class)->issue($actor, $request, $data);
    echo json_encode(['result' => 'saved', 'id' => $document->id]);
} catch (AuthorizationException|ValidationException) {
    echo json_encode(['result' => 'blocked']);
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error));
    exit(1);
}
