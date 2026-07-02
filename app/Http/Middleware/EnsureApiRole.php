<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureApiRole
 * -------------
 * Backend permission check for API routes. The frontend may hide buttons,
 * but the real gate is HERE — every protected action is verified server-side.
 *
 * Usage in routes:
 *   ->middleware('api.role:admin')
 *   ->middleware('api.role:admin,front_desk')   // any of these roles
 *
 * Admins always pass. Otherwise the user's role must be in the allowed list.
 * Reuses the User model's existing role helpers (isAdminRole / hasRole).
 */
class EnsureApiRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'errors'  => [],
            ], 401);
        }

        // Admin / clinic owner can do everything.
        if ($user->isAdminRole()) {
            return $next($request);
        }

        foreach ($roles as $role) {
            if ($user->hasRole($role)) {
                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'You do not have permission to perform this action.',
            'errors'  => [],
        ], 403);
    }
}
