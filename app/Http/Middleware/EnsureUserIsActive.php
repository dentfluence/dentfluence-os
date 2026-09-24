<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A deactivated staff member loses access on their very next request, on web
 * and on the phone app — not after the 30-day token or the session runs out.
 * Audit AUTH-02 (24 Sep 2026). Only an explicit is_active = false blocks.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken()) {
            $user = Auth::guard('sanctum')->user();
            if ($user && $user->is_active === false) {
                $user->currentAccessToken()?->delete();
                AuditLog::event('login_blocked_inactive', $user->id, ['via' => 'api'], ['module' => 'auth']);

                return response()->json(['success' => false, 'message' => 'This account has been deactivated.'], 401);
            }

            return $next($request);
        }

        $user = Auth::guard('web')->user();
        if ($user && $user->is_active === false) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            AuditLog::event('login_blocked_inactive', $user->id, ['via' => 'web'], ['module' => 'auth']);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'This account has been deactivated.'], 401);
            }

            return redirect()->route('login')->withErrors(['email' => 'This account has been deactivated.']);
        }

        return $next($request);
    }
}
