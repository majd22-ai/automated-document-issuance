<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
try {
    $app->make(\Illuminate\Contracts\Http\Kernel::class);
    $blade = $app['view'];
    $compiled = $blade->make('public.home')->render();
    echo 'SUCCESS: Template compiled OK, length=' . strlen($compiled);
} catch(\Illuminate\View\ViewException $e) {
    echo 'BLADE ERROR: ' . $e->getMessage();
} catch(Exception $e) {
    echo 'ERROR: ' . get_class($e) . ': ' . $e->getMessage();
}
