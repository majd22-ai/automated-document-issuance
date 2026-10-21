<?php

namespace App\Policies;

use App\Models\User;

class GovernmentServicePolicy
{
    public function manage(User $user): bool
    {
        return $user->is_active && $user->role === 'admin';
    }
}
