<?php

namespace App\Contracts;

use App\Services\Integrations\VerificationContext;
use App\Services\Integrations\VerificationResult;

interface IntegrationProvider
{
    /** Read-only query. Adapter MUST bind source response to correlation and authoritative subject. */
    public function verify(VerificationContext $context, array $settings): VerificationResult;
}
