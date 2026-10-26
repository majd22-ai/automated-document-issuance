<?php

return [
    // Register only adapters implemented against a supplied official contract.
    // URLs, credentials and approval are deployment configuration, never browser input.
    'providers' => [],
    'timeout' => (int) env('INTEGRATION_TIMEOUT_SECONDS', 8),
    'connect_timeout' => (int) env('INTEGRATION_CONNECT_TIMEOUT_SECONDS', 3),
    'retries' => 0,
];
