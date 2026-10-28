<?php

namespace App\Services\Fingerprint;

final readonly class FingerprintCaptureResult
{
    public function __construct(public string $result, public ?string $error = null) {}
}
