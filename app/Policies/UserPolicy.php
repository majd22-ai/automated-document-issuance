<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
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
