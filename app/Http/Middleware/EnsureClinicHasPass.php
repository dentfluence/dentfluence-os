<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The door-lock: refuses entry to a store/module unless the clinic's pass covers it.
 *
 * Usage (once wired):  Route::middleware('pass:marketing')->group(...)
 *
 * NOT ACTIVE YET. Enforcement needs a "current clinic" on the request, which
 * arrives with the tenancy wave (Door 02/04 — users get clinic memberships).
 * Until then this middleware is intentionally not registered on any route.
 * The pass data itself (Plan::unlocks, Clinic::passes/hasPass) is live and
 * visible in HQ from day one.
 */
class EnsureClinicHasPass
{
    public function handle(Request $request, Closure $next, string $code): Response
    {
        // TODO (tenancy wave): resolve the clinic from the authenticated
        // user's active membership instead of this placeholder.
        $clinic = $request->user()?->clinic;

        if (! $clinic || ! $clinic->hasPass($code)) {
            abort(403, 'Your subscription does not include this module. Renew or upgrade to regain access.');
        }

        return $next($request);
    }
}
