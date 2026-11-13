<?php

namespace App\Policies;

use App\Models\DemoCivilPerson;
use App\Models\User;

class DemoCivilPersonPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('viewAnyDemoCivilRegistry', User::class);
    }

    public function view(User $user, DemoCivilPerson $person): bool
    {
        return $person->is_synthetic && ! $person->archived_at && $this->viewAny($user);
    }

    public function manage(User $user): bool
    {
        return $user->can('manageDemoCivilRegistry', User::class);
    }
}