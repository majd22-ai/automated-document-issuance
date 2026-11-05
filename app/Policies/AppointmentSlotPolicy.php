<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Workspace\EmployeeWorkspace;

class AppointmentSlotPolicy
{
    public function manage(User $actor): bool
    {
        return app(EmployeeWorkspace::class)->serviceIds($actor, ['can_review']) !== [];
    }
}
