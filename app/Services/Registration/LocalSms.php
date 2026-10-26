<?php

namespace App\Services\Registration;

use App\Contracts\RegistrationSms;

class LocalSms implements RegistrationSms
{
    public static function enabled(): bool
    {
        return app()->environment('local') && config('registration.local_otp') === true;
    }

    public function send(string $phone, string $code): void
    {
        if (! self::enabled()) {
            throw new \RuntimeException('Local OTP is disabled.');
        }

        session()->put('registration_local_code', $code);
    }
}
