<?php

namespace App\Policies;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;

class UserPolicy
{
    /**
     * Approve or reject a student signup.
     */
    public function review(User $actor, User $target): bool
    {
        return $actor->role === Role::Admin && $target->role === Role::Student;
    }

    /**
     * Disable any account except your own.
     */
    public function disable(User $actor, User $target): bool
    {
        return $actor->role === Role::Admin && ! $actor->is($target);
    }

    /**
     * Re-enable a disabled account (not your own). Pending and rejected
     * students go through the approval queue instead.
     */
    public function enable(User $actor, User $target): bool
    {
        return $this->disable($actor, $target) && $target->status === UserStatus::Disabled;
    }

    /**
     * Reset any other account to a temporary password.
     */
    public function resetPassword(User $actor, User $target): bool
    {
        return $this->disable($actor, $target);
    }
}
