<?php

namespace App\Contracts;

use App\Models\CitizenServiceRequest;
use App\Models\GovernmentService;
use App\Models\User;
use App\Services\Requirements\EvidenceLookup;

interface ServiceEvidenceSource
{
    public function lookup(User $citizen, GovernmentService $service, string $kind, string $key, ?CitizenServiceRequest $request = null): EvidenceLookup;
}
