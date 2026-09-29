<?php

namespace App\Http\Middleware;

use App\Services\StationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStation
{
    public function __construct(private StationService $stations) {}

    /**
     * Job-processing pages need an open station session. The session is shared
     * with the controller as the "station" request attribute.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $session = $request->user() ? $this->stations->current($request->user()) : null;

        if (! $session) {
            return redirect()->route('staff.station')
                ->with('warning', 'Clock in at a location before processing jobs.');
        }

        $request->attributes->set('station', $session);

        return $next($request);
    }
}
