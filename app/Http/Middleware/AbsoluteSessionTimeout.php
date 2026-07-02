<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * AbsoluteSessionTimeout (Phase A — security)
 * -------------------------------------------
 * Laravel's `session.lifetime` is an IDLE timeout — every request pushes it
 * forward, so an active session can live forever. This middleware adds a HARD
 * cap on total session age (config `session.absolute_lifetime`, in minutes):
 * once that many minutes have passed since login, the user is logged out and
 * sent back to the login screen, no matter how active they've been.
 *
 * The clock starts on the first authenticated request after login (the login
 * flow regenerates the session, so the stamp is always fresh). Set
 * `absolute_lifetime` to 0 to disable.
 */
class AbsoluteSessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        $max = (int) config('session.absolute_lifetime', 0);

        if ($max > 0 && Auth::check()) {
            $startedAt = $request->session()->get('auth_started_at');

            if (! $startedAt) {
                $request->session()->put('auth_started_at', now()->timestamp);
            } elseif (now()->timestamp - (int) $startedAt > $max * 60) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Session expired. Please sign in again.'], 401);
                }

                return redirect()->route('login')
                    ->withErrors(['email' => 'Your session expired. Please sign in again.']);
            }
        }

        return $next($request);
    }
}
