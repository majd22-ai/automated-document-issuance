<?php

namespace App\Policies;

use App\Models\CitizenServiceRequest;
use App\Models\User;

class CitizenServiceRequestPolicy
{
    public function create(User $user): bool
    {
        return $user->is_active && $user->role === 'citizen';
    }

    public function view(User $user, CitizenServiceRequest $application): bool
    {
        return $user->is_active && (($user->role === 'citizen' && $application->user_id === $user->id)
            || ($user->role === 'employee' && $user->can('viewCitizenData', $application->citizen)));
    }

    public function transition(User $user, CitizenServiceRequest $application): bool
    {
        // No review/approval/cancellation permission is granted by requirement 4.
        return false;
    }
}
