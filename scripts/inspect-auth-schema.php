<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
echo 'Connection: '.config('database.default').PHP_EOL;
try {
    $schema = Schema::getFacadeRoot();
    echo json_encode($schema->getColumns('users'), JSON_PRETTY_PRINT).PHP_EOL;
    echo json_encode($schema->getIndexes('users'), JSON_PRETTY_PRINT).PHP_EOL;
    echo 'Users: '.User::count().PHP_EOL;
} catch (Throwable $e) {
    echo 'Database inspection failed ('.get_class($e).').'.PHP_EOL;
    exit(1);
}
