<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RespondsWithAccessDenied;
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
    use RespondsWithAccessDenied;

    public function handle(Request $request, Closure $next, string $module, string $action = 'view'): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->canAccess($module, $action)) {
            return $this->denyAccess($request, 'You do not have permission to access this section.');
        }

        return $next($request);
    }
}
