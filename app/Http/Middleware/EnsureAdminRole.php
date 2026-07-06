<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RespondsWithAccessDenied;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware: EnsureAdminRole
 *
 * Restricts a route to Admin only, regardless of what any per-module
 * view/edit/delete permission toggle says.
 *
 * Used for Roles & Permissions management specifically: granting hr:edit
 * to a non-admin role must never let that role reach the screen that
 * assigns permissions — including its own — or it could grant itself
 * Admin. This check is intentionally not configurable via the permission
 * grid; it is a hard rule, not a toggle.
 */
class EnsureAdminRole
{
    use RespondsWithAccessDenied;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->isAdminRole()) {
            return $this->denyAccess($request, 'Only Admin can manage roles and permissions.');
        }

        return $next($request);
    }
}
