<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Services\DashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboards) {}

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        return match ($user->role) {
            Role::Admin => redirect()->route('admin.dashboard'),
            Role::Staff => view('dashboard.staff', ['summary' => $this->dashboards->staff($user)]),
            default => view('dashboard.student', ['summary' => $this->dashboards->student($user)]),
        };
    }

    public function admin(): View
    {
        return view('dashboard.admin', ['summary' => $this->dashboards->admin()]);
    }
}
