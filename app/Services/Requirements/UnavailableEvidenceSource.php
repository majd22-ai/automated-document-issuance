<?php

namespace App\Services\Requirements;

use App\Contracts\ServiceEvidenceSource;
use App\Models\CitizenServiceRequest;
use App\Models\GovernmentService;
use App\Models\User;

class UnavailableEvidenceSource implements ServiceEvidenceSource
{
    public function lookup(User $citizen, GovernmentService $service, string $kind, string $key, ?CitizenServiceRequest $request = null): EvidenceLookup
    {
        // Task 6/medical/registry modules must supply real evidence through this contract.
        return new EvidenceLookup;
    }
}
