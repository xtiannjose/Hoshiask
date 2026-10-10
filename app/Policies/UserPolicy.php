<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->canManageUsers();
    }

    public function create(User $actor): bool
    {
        return $actor->canManageUsers();
    }

    public function view(User $actor, User $target): bool
    {
        return $this->canManageDirectoryTarget($actor, $target);
    }

    public function update(User $actor, User $target): bool
    {
        return $this->canManageDirectoryTarget($actor, $target);
    }

    public function delete(User $actor, User $target): bool
    {
        return $this->canManageDirectoryTarget($actor, $target)
            && ! $target->isShadow()
            && ! $target->is_profile_locked;
    }

    public function toggleStatus(User $actor, User $target): bool
    {
        return $this->canManageDirectoryTarget($actor, $target)
            && ! $target->isShadow();
    }

    public function resetManagedPassword(User $actor, User $target): bool
    {
        return $actor->isShadow()
            && ! $target->isSystemReplacementAccount()
            && ! $target->isShadow()
            && (int) $actor->id !== (int) $target->id;
    }

    public function revealManagedPassword(User $actor, User $target): bool
    {
        return $this->resetManagedPassword($actor, $target);
    }

    private function canManageDirectoryTarget(User $actor, User $target): bool
    {
        return $actor->canManageUsers()
            && ! $target->isSystemReplacementAccount()
            && (int) $actor->id !== (int) $target->id
            && $actor->canManageUserTarget($target);
    }
}
