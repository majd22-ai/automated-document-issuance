<?php

namespace App\Policies;

use App\Models\GovernmentService;
use App\Models\User;

class UserPolicy
{
    public function manageCitizenActivation(User $actor): bool
    {
        $serviceId = GovernmentService::where('code', 'identity-first-issue')->value('id');

        return $actor->canReviewService($serviceId, 'can_activate');
    }

    public function activateCitizen(User $actor, User $citizen): bool
    {
        return $citizen->role === 'citizen' && $citizen->is_active && $this->manageCitizenActivation($actor);
    }

    public function viewCitizenData(User $actor, User $citizen): bool
    {
        return $actor->is_active && $citizen->role === 'citizen'
            && (($actor->role === 'citizen' && $actor->is($citizen)) || $actor->role === 'employee');
    }

    public function searchCitizens(User $actor): bool
    {
        return $actor->is_active && $actor->role === 'employee';
    }
}
