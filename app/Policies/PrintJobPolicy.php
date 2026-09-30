<?php

namespace App\Policies;

use App\Enums\JobStatus;
use App\Enums\Role;
use App\Models\PrintJob;
use App\Models\User;

class PrintJobPolicy
{
    /**
     * Owners see their own jobs; staff and admins see every job.
     */
    public function view(User $user, PrintJob $job): bool
    {
        return $this->owns($user, $job) || in_array($user->role, [Role::Staff, Role::Admin], true);
    }

    /**
     * Owners may edit only while the job is pending.
     */
    public function update(User $user, PrintJob $job): bool
    {
        return $this->owns($user, $job) && $job->status === JobStatus::Pending;
    }

    /**
     * Owners may delete only while the job is pending.
     */
    public function delete(User $user, PrintJob $job): bool
    {
        return $this->update($user, $job);
    }

    private function owns(User $user, PrintJob $job): bool
    {
        return $job->user_id === $user->id;
    }
}
