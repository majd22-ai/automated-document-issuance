<?php

use App\Services\Integrations\LocalCivilRegistryProvider;

return [
    // Register only adapters implemented against a supplied official contract.
    // URLs, credentials and approval are deployment configuration, never browser input.
    'providers' => [
        'civil_registry_demo' => [
            'adapter' => LocalCivilRegistryProvider::class,
            'label' => 'محاكاة السجل المدني — غير معتمدة رسميًا',
            'enabled' => env('CIVIL_REGISTRY_DEMO', false),
            'approved' => false,
        ],
    ],
    'timeout' => (int) env('INTEGRATION_TIMEOUT_SECONDS', 8),
    'connect_timeout' => (int) env('INTEGRATION_CONNECT_TIMEOUT_SECONDS', 3),
    'retries' => 0,
];
