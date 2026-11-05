<?php
require __DIR__.'/vendor/autoload.php';
putenv('APP_KEY=base64:dGVzdGtleWZvcnRlc3Rpbmd0ZXN0a2V5Zm9y');
\ = require __DIR__.'/bootstrap/app.php';
try {
    \ = \->make('view');
    \ = \->make('public.home')->render();
    echo 'SUCCESS: Template compiled OK';
} catch(Exception \) {
    echo 'ERROR: ' . \->getMessage();
}

