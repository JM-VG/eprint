<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\AccountApproved;
use App\Notifications\AccountRejected;

class AccountApprovalService
{
    public function approve(User $student, User $admin): void
    {
        $student->forceFill([
            'status' => UserStatus::Approved,
            'approved_by' => $admin->id,
            'approved_at' => now(),
            'rejection_reason' => null,
        ])->save();

        $student->notify(new AccountApproved);
    }

    public function reject(User $student, User $admin, string $reason): void
    {
        $student->forceFill([
            'status' => UserStatus::Rejected,
            'approved_by' => $admin->id,
            'approved_at' => null,
            'rejection_reason' => $reason,
        ])->save();

        $student->notify(new AccountRejected($reason));
    }

    public function disable(User $user): void
    {
        $user->forceFill(['status' => UserStatus::Disabled])->save();
    }
}
