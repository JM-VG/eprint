<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\SalesFilterRequest;
use App\Models\Location;
use App\Services\CommissionReportService;
use Illuminate\View\View;

class CommissionController extends Controller
{
    public function index(SalesFilterRequest $request, CommissionReportService $reports): View
    {
        $filters = $request->validated();

        return view('staff.commission', [
            'summary' => $reports->summary($request->user()),
            'sales' => $reports->sales($request->user(), $filters),
            'filters' => $filters,
            'locations' => Location::orderBy('id')->get(),
        ]);
    }
}
