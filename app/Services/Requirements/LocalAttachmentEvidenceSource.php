<?php

namespace App\Services\Requirements;

use App\Contracts\ServiceEvidenceSource;
use App\Models\CitizenAttachment;
use App\Models\CitizenServiceRequest;
use App\Models\GovernmentService;
use App\Models\User;

class LocalAttachmentEvidenceSource implements ServiceEvidenceSource
{
    public function __construct(private UnavailableEvidenceSource $otherSources) {}

    public function lookup(User $citizen, GovernmentService $service, string $kind, string $key, ?CitizenServiceRequest $request = null): EvidenceLookup
    {
        if ($kind !== 'attachment') {
            return $this->otherSources->lookup($citizen, $service, $kind, $key, $request);
        }
        $file = CitizenAttachment::where('user_id', $citizen->id)->where('government_service_id', $service->id)
            ->where('document_code', $key)->where('citizen_service_request_id', $request?->id)->first();
        if (! $file) {
            return new EvidenceLookup(true);
        }
        if ($file->rules_version !== $service->rules_version || ! $file->intact()) {
            return new EvidenceLookup;
        }
        $approved = $file->review_status === 'approved' && $file->reviewed_by && $file->reviewed_at && $file->reviewed_at->lte(now());

        return new EvidenceLookup(true, [[
            'user_id' => $file->user_id, 'service_id' => $file->government_service_id, 'request_id' => $file->citizen_service_request_id,
            'uploaded' => true, 'mime_type' => $file->mime_type, 'trusted' => true,
            'source_reference' => 'local-attachment:'.$file->id, 'verified_at' => $file->updated_at->toIso8601String(),
            'review_status' => $file->review_status === 'replacement_required' ? 'rejected' : ($approved ? 'approved' : ($file->review_status === 'approved' ? 'pending' : $file->review_status)),
            'expires_at' => $approved ? $file->expires_at?->toDateString() : null,
        ]]);
    }
}
