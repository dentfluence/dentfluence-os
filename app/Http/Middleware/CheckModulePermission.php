<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware: CheckModulePermission
 *
 * Usage in routes:
 *   ->middleware('module:tasks')           // checks can_view
 *   ->middleware('module:tasks,edit')      // checks can_edit
 *   ->middleware('module:tasks,delete')    // checks can_delete
 */
class CheckModulePermission
{
    public function handle(Request $request, Closure $next, string $module, string $action = 'view'): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->canAccess($module, $action)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Access denied.'], 403);
            }

            abort(403, 'You do not have permission to access this section.');
        }

        return $next($request);
    }
}
