<?php

namespace App\Services\Integrations;

use App\Enums\VerificationStatus;

final readonly class VerificationResult
{
    public function __construct(public VerificationStatus $status, public bool $bound = false, public ?int $httpStatus = null, public int $attempts = 0) {}
}
