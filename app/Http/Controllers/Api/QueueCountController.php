<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\QueueService;
use App\Services\StationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QueueCountController extends Controller
{
    /**
     * Pending job counts for the sidebar badge, polled every 15 seconds when it cannot stream /public/queue.
     */
    public function __invoke(Request $request, QueueService $queue, StationService $stations): JsonResponse
    {
        $counts = $queue->pendingCounts();
        $locationId = $stations->current($request->user())?->location_id;

        return response()->json([
            'location_id' => $locationId,
            'pending' => $locationId ? ($counts[$locationId] ?? 0) : 0,
            'counts' => (object) $counts,
        ]);
    }
}
