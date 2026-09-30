<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\StationSession;
use App\Services\QueueService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QueueController extends Controller
{
    /**
     * The queue opens on the staff member's station location; ?location= shows the other tab.
     */
    public function index(Request $request, QueueService $queue): View
    {
        /** @var StationSession $station */
        $station = $request->attributes->get('station');

        $location = $request->filled('location')
            ? Location::findOrFail($request->integer('location'))
            : Location::findOrFail($station->location_id);

        $locations = Location::orderBy('id')->get()
            ->sortBy(fn (Location $l) => $l->id === $station->location_id ? 0 : 1)
            ->values();

        return view('staff.queue', [
            'location' => $location,
            'stationLocationId' => $station->location_id,
            'locations' => $locations,
            'counts' => $queue->pendingCounts(),
            'jobs' => $queue->forLocation($location),
        ]);
    }
}
