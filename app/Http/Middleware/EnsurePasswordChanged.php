<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    /**
     * Routes a user with a temporary password may still use.
     *
     * @var list<string>
     */
    private const ALLOWED_ROUTES = ['profile.edit', 'password.update', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->must_change_password || $request->routeIs(...self::ALLOWED_ROUTES)) {
            return $next($request);
        }

        return redirect()->route('profile.edit')
            ->with('warning', 'Please change your temporary password before continuing.');
    }
}
