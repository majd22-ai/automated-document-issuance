<?php

namespace App\Policies;

use App\Models\User;

class AppointmentSlotPolicy
{
    public function manage(User $actor): bool
    {
        return $actor->is_active && $actor->role === 'employee';
    }
}
