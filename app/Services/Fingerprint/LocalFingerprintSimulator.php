<?php

namespace App\Services\Fingerprint;

use App\Contracts\FingerprintConnector;
use App\Services\FingerprintService;

class LocalFingerprintSimulator implements FingerprintConnector
{
    public function capture(bool $forceFailure = false): FingerprintCaptureResult
    {
        abort_unless(FingerprintService::enabled(), 404);

        return $forceFailure ? new FingerprintCaptureResult('failure', 'فشل مقصود لاختبار إعادة المحاولة؛ لا يوجد جهاز أو التقاط حيوي.') : new FingerprintCaptureResult('success');
    }
}
