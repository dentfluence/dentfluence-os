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
 *   ->middleware('api.role:admin,front_desk')      // any of these roles
 *   ->middleware('api.role:module:billing,edit')   // permission-table check
 *
 * The third form (2026-07-14) routes through User::canAccess() — the SAME
 * RoleModulePermission table the web middleware (CheckModulePermission) uses —
 * so an API route can be gated by exactly the permission that gates its web
 * equivalent, instead of a parallel role-name list that can drift from it.
 *
 * Admins always pass. Role checks now consult both role systems (see
 * User::hasRole), so the API and the web can no longer disagree about who
 * holds a role.
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
            // 'module:billing' (+ optional next param as the action) → check the
            // permission table rather than a role name.
            if (str_starts_with($role, 'module:')) {
                $module = substr($role, strlen('module:'));
                $action = $this->actionAfter($roles, $role);

                if ($user->canAccess($module, $action)) {
                    return $next($request);
                }

                continue;
            }

            // Skip bare action words that belong to a preceding module: token.
            if (in_array($role, ['view', 'edit', 'delete'], true)) {
                continue;
            }

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

    /**
     * The parameter following a 'module:x' token is its action, when present
     * and valid. Defaults to 'view'.
     */
    private function actionAfter(array $roles, string $moduleToken): string
    {
        $i    = array_search($moduleToken, $roles, true);
        $next = $i !== false ? ($roles[$i + 1] ?? null) : null;

        return in_array($next, ['view', 'edit', 'delete'], true) ? $next : 'view';
    }
}
