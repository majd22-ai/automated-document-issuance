<?php

namespace App\Services\Fingerprint;

use App\Contracts\FingerprintConnector;

class UnavailableFingerprintConnector implements FingerprintConnector
{
    public function capture(bool $forceFailure = false): FingerprintCaptureResult
    {
        abort(503, 'لا يوجد موصل جهاز بصمة موثوق مهيأ. المحاكاة محلية فقط.');
    }
}
