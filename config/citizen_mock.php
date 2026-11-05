<?php

return [
    'enabled' => env('CITIZEN_MOCK_REGISTRATION', env('APP_ENV') === 'local'),
    'otp_minutes' => 3,
    'max_face_attempts' => 3,
];
