<?php

namespace App\Contracts;

use App\Services\Fingerprint\FingerprintCaptureResult;

interface FingerprintConnector
{
    public function capture(bool $forceFailure = false): FingerprintCaptureResult;
}
