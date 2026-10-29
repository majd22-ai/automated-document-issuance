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
        $this->simulate();
        session()->put('registration_local_code', $code);
    }

    /** Activation returns its preview once to the authorized caller, without persisting it in a session. */
    public function simulate(): void
    {
        if (! self::enabled()) {
            throw new \RuntimeException('Local OTP is disabled.');
        }

    }
}
