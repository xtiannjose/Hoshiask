<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->canManageStaffAccounts();
    }

    public function create(User $actor): bool
    {
        return $actor->canManageStaffAccounts();
    }

    public function update(User $actor, Department $department): bool
    {
        return $actor->canManageStaffAccounts();
    }
}
