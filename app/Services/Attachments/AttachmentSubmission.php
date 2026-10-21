<?php

namespace App\Services\Attachments;

use App\Models\CitizenAttachment;
use App\Models\CitizenServiceRequest;
use App\Models\GovernmentService;
use App\Models\User;

class AttachmentSubmission
{
    public function fingerprint(User $citizen, GovernmentService $service): string
    {
        $files = CitizenAttachment::where('user_id', $citizen->id)->where('government_service_id', $service->id)->whereNull('citizen_service_request_id')->orderBy('id')->get();

        return hash('sha256', json_encode($files->map(fn ($file) => $file->only(['id', 'version_token', 'sha256', 'rules_version', 'review_status', 'reviewed_by', 'reviewed_at', 'expires_at']))->all(), JSON_THROW_ON_ERROR));
    }

    public function bind(User $citizen, GovernmentService $service, CitizenServiceRequest $application): void
    {
        // The caller holds the owner and service locks, shared by every attachment mutation.
        CitizenAttachment::where('user_id', $citizen->id)->where('government_service_id', $service->id)->whereNull('citizen_service_request_id')
            ->lockForUpdate()->get()->each(function ($file) use ($application) {
                $file->forceFill(['citizen_service_request_id' => $application->id, 'slot_key' => CitizenAttachment::slot($file->user_id, $file->government_service_id, $application->id, $file->document_code)])->save();
            });
    }
}
