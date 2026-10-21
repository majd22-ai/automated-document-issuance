<?php

namespace App\Policies;

use App\Models\CitizenAttachment;
use App\Models\User;

class CitizenAttachmentPolicy
{
    public function view(User $actor, CitizenAttachment $file): bool
    {
        return $actor->is_active && (($actor->role === 'citizen' && $actor->id === $file->user_id)
            || ($actor->role === 'employee' && $actor->can('viewCitizenData', $file->citizen)));
    }

    public function update(User $actor, CitizenAttachment $file): bool
    {
        return $actor->is_active && $actor->role === 'citizen' && $actor->id === $file->user_id
            && (! $file->citizen_service_request_id || $file->application?->status === 'requires_correction');
    }

    public function review(User $actor, CitizenAttachment $file): bool
    {
        return $actor->is_active && $actor->role === 'employee' && $this->view($actor, $file)
            && (! $file->citizen_service_request_id || in_array($file->application?->status, ['submitted', 'under_review', 'requires_correction'], true));
    }
}
