<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo 'Connection: '.config('database.default').PHP_EOL;
try {
    $schema = Illuminate\Support\Facades\Schema::getFacadeRoot();
    echo json_encode($schema->getColumns('users'), JSON_PRETTY_PRINT).PHP_EOL;
    echo json_encode($schema->getIndexes('users'), JSON_PRETTY_PRINT).PHP_EOL;
    echo 'Users: '.App\Models\User::count().PHP_EOL;
} catch (Throwable $e) {
    echo 'Database inspection failed ('.get_class($e).').'.PHP_EOL;
    exit(1);
}
