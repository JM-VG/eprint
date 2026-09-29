<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StaffRequest;
use App\Models\User;
use App\Services\StaffAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function __construct(private StaffAccountService $accounts) {}

    public function index(): View
    {
        $staff = User::whereIn('role', [Role::Staff, Role::Admin])
            ->orderBy('role')
            ->orderBy('last_name')
            ->paginate(25);

        return view('admin.staff.index', ['staff' => $staff]);
    }

    public function create(): View
    {
        return view('admin.staff.form', ['user' => new User(['role' => Role::Staff])]);
    }

    public function store(StaffRequest $request): RedirectResponse
    {
        [$user, $password] = $this->accounts->create($request->validated(), $request->user());

        return redirect()->route('admin.staff.index')
            ->with('success', "Account {$user->username} created. Temporary password (shown once): {$password}");
    }

    public function edit(User $user): View
    {
        abort_if($user->role === Role::Student, 404);

        return view('admin.staff.form', ['user' => $user]);
    }

    public function update(StaffRequest $request, User $user): RedirectResponse
    {
        abort_if($user->role === Role::Student, 404);

        $this->accounts->update($user, $request->validated());

        return redirect()->route('admin.staff.index')->with('success', "Account {$user->username} updated.");
    }
}
