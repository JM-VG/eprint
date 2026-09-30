<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JobStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AuditLogRequest;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(AuditLogRequest $request, AuditLogService $audit): View
    {
        return view('admin.audit.index', [
            'logs' => $audit->search($request->validated()),
            'filters' => $request->validated(),
            'actors' => User::whereIn('role', [Role::Staff, Role::Admin])->orderBy('last_name')->get(),
            'statuses' => JobStatus::cases(),
        ]);
    }
}
