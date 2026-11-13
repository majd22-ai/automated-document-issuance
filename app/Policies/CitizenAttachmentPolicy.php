<?php

namespace App\Policies;

use App\Models\CitizenAttachment;
use App\Models\User;
use App\Services\Workspace\EmployeeWorkspace;

class CitizenAttachmentPolicy
{
    public function view(User $actor, CitizenAttachment $file): bool
    {
        return $actor->is_active && (($actor->role === 'citizen' && $actor->id === $file->user_id)
            || ($actor->role === 'employee' && in_array((int) $file->government_service_id, app(EmployeeWorkspace::class)->serviceIds($actor, ['can_review']), true)));
    }

    public function update(User $actor, CitizenAttachment $file): bool
    {
        if (str_starts_with($file->document_code, 'identity_visit_')) { return false; }
        return $actor->is_active && $actor->role === 'citizen' && $actor->id === $file->user_id
            && (! $file->citizen_service_request_id || $file->application?->status === 'requires_correction');
    }

    public function review(User $actor, CitizenAttachment $file): bool
    {
        if (str_starts_with($file->document_code, 'identity_visit_')) { return false; }
        return $actor->is_active && $actor->role === 'employee' && $this->view($actor, $file)
            && (! $file->citizen_service_request_id || in_array($file->application?->status, ['submitted', 'under_review', 'requires_correction'], true));
    }
}
