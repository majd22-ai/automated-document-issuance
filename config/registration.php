<?php

return [
    // No generic HTTP contract is assumed. Bind RegistrationSms after implementing the provider contract.
    'local_otp' => env('REGISTRATION_LOCAL_OTP', false),
    'otp_minutes' => 10,
    'max_attempts' => 5,
    'resend_seconds' => 60,
    'max_sends' => 3,
];
