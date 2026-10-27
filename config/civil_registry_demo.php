<?php

return [
    'enabled' => env('CIVIL_REGISTRY_DEMO', false),
    // Synthetic fixtures only. Never replace these with real citizen data.
    'records' => [
        '99000000001' => ['name' => 'مواطن تجريبي أول', 'available' => true],
        '99000000002' => ['name' => 'مواطن تجريبي ثان', 'available' => false],
    ],
];
