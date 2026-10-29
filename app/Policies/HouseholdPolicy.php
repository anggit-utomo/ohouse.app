<?php

namespace App\Policies;

use App\Enums\HouseholdRoleEnum;
use App\Models\Household;
use App\Models\User;

class HouseholdPolicy
{
    public function view(User $user, Household $household): bool
    {
        return $user->roleIn($household) !== null;
    }

    public function edit(User $user, Household $household): bool
    {
        return $user->roleIn($household)?->canEdit() ?? false;
    }

    public function manage(User $user, Household $household): bool
    {
        return $user->roleIn($household) === HouseholdRoleEnum::OWNER;
    }
}
