<?php

return [
    // Educational fixtures only, never an official Yemen Post integration.
    'enabled' => env('POSTAL_NETWORK_ENABLED', false),
    'allow_seed' => env('POSTAL_NETWORK_ALLOW_SEED', false),
    'passwords' => [
        'SANA-001' => env('POSTAL_DEMO_CENTRAL_PASSWORD'),
        'SANA-002' => env('POSTAL_DEMO_TAHRIR_PASSWORD'),
        'SANA-003' => env('POSTAL_DEMO_HADDA_PASSWORD'),
    ],
];
