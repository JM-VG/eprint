<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureApproved
{
    /**
     * End the session of any signed-in account that is not approved
     * (for example, disabled by an admin while logged in).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->status === UserStatus::Approved) {
            return $next($request);
        }

        $message = $user->status->loginMessage($user->rejection_reason);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['login' => $message]);
    }
}
