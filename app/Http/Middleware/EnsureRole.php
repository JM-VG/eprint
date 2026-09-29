<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Allow the request only when the user's role is one of the given roles.
     * Usage: role:admin or role:staff,admin
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $role = $request->user()?->role?->value;

        abort_unless($role !== null && in_array($role, $roles, true), 403);

        return $next($request);
    }
}
