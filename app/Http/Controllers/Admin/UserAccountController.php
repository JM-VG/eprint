<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountApprovalService;
use App\Services\StaffAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Actions an admin can take on any account (student, staff or admin).
 */
class UserAccountController extends Controller
{
    public function disable(User $user, AccountApprovalService $approvals): RedirectResponse
    {
        Gate::authorize('disable', $user);

        $approvals->disable($user);

        return back()->with('success', "{$user->name} was disabled.");
    }

    public function enable(User $user, AccountApprovalService $approvals): RedirectResponse
    {
        Gate::authorize('enable', $user);

        $approvals->enable($user);

        return back()->with('success', "{$user->name} was enabled.");
    }

    public function resetPassword(User $user, StaffAccountService $accounts): RedirectResponse
    {
        Gate::authorize('resetPassword', $user);

        $password = $accounts->resetPassword($user);
        $login = $user->student_id ?? $user->username;

        return back()->with('success', "Temporary password for {$login} (shown once): {$password}");
    }
}
