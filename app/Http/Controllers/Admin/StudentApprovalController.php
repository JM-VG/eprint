<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectStudentRequest;
use App\Models\User;
use App\Services\AccountApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StudentApprovalController extends Controller
{
    public function __construct(private AccountApprovalService $approvals) {}

    public function index(Request $request): View
    {
        $status = UserStatus::tryFrom((string) $request->query('status')) ?? UserStatus::Pending;

        $students = User::where('role', Role::Student)
            ->where('status', $status)
            ->orderBy($status === UserStatus::Pending ? 'created_at' : 'updated_at', $status === UserStatus::Pending ? 'asc' : 'desc')
            ->paginate(25)
            ->withQueryString();

        return view('admin.students.index', [
            'students' => $students,
            'status' => $status,
            'statuses' => UserStatus::cases(),
        ]);
    }

    public function approve(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('review', $user);

        $this->approvals->approve($user, $request->user());

        return back()->with('success', "{$user->name} ({$user->student_id}) was approved.");
    }

    public function reject(RejectStudentRequest $request, User $user): RedirectResponse
    {
        $this->approvals->reject($user, $request->user(), $request->validated('reason'));

        return back()->with('success', "{$user->name} ({$user->student_id}) was rejected.");
    }
}
