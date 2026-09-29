<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HeartbeatController extends Controller
{
    /**
     * Staff pages call this every 30 seconds to keep their station open.
     */
    public function __invoke(Request $request, StationService $stations): JsonResponse
    {
        $session = $stations->heartbeat($request->user())?->load('location');

        return response()->json([
            'clocked_in' => $session !== null,
            'location' => $session ? ['id' => $session->location->id, 'name' => $session->location->name] : null,
        ]);
    }
}
