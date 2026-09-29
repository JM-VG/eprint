<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ClockInRequest;
use App\Models\Location;
use App\Services\StationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StationController extends Controller
{
    public function __construct(private StationService $stations) {}

    public function show(Request $request): View
    {
        return view('staff.station', [
            'current' => $this->stations->current($request->user())?->load('location'),
            'locations' => Location::orderBy('id')->get(),
        ]);
    }

    public function store(ClockInRequest $request): RedirectResponse
    {
        $location = Location::findOrFail($request->validated('location_id'));
        $session = $this->stations->clockIn($request->user(), $location);

        if (! $session) {
            return redirect()->route('staff.station')
                ->with('warning', 'You are already clocked in. Clock out or switch location first.');
        }

        return redirect()->route('staff.station')->with('success', "Clocked in at {$location->name}.");
    }
}
