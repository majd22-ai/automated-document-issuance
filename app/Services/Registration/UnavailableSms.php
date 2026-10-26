<?php

namespace App\Services\Registration;

use App\Contracts\RegistrationSms;

class UnavailableSms implements RegistrationSms
{
    public function send(string $phone, string $code): void
    {
        throw new \RuntimeException('Registration SMS provider is not configured.');
    }
}
