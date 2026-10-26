<?php

namespace App\Contracts;

/** Bind a provider-specific adapter that throws on failure; never log the code or silently accept. */
interface RegistrationSms
{
    public function send(string $phone, string $code): void;
}
