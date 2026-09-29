<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AvailabilityService;
use Illuminate\Http\JsonResponse;

class AvailabilityController extends Controller
{
    /**
     * Public: which printing rooms are open right now. Polled by the banner every 15 seconds.
     */
    public function __invoke(AvailabilityService $availability): JsonResponse
    {
        return response()->json(['locations' => $availability->locations()]);
    }
}
