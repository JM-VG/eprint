<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ShiftHistoryRequest;
use App\Models\Location;
use App\Models\User;
use App\Services\ShiftHistoryService;
use Illuminate\View\View;

class ShiftHistoryController extends Controller
{
    public function index(ShiftHistoryRequest $request, ShiftHistoryService $history): View
    {
        return view('admin.shifts.index', [
            'shifts' => $history->search($request->validated()),
            'filters' => $request->validated(),
            'staff' => User::whereIn('role', [Role::Staff, Role::Admin])->orderBy('last_name')->get(),
            'locations' => Location::orderBy('id')->get(),
        ]);
    }
}
