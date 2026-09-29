<?php

namespace App\Policies;

use App\Enums\Role;
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
     * Re-enable any account except your own.
     */
    public function enable(User $actor, User $target): bool
    {
        return $this->disable($actor, $target);
    }

    /**
     * Reset any other account to a temporary password.
     */
    public function resetPassword(User $actor, User $target): bool
    {
        return $this->disable($actor, $target);
    }
}
