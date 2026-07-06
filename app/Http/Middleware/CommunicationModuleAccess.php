<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RespondsWithAccessDenied;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CommunicationModuleAccess
{
    use RespondsWithAccessDenied;

    /**
     * Handle an incoming request.
     *
     * Enforces that the Communication OS module is:
     * 1. Enabled in config
     * 2. Accessible by the authenticated user's role (via the 'communication'
     *    module row in Roles & Permissions)
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Module kill-switch
        if (! config('communication.enabled', true)) {
            abort(503, 'Communication OS is currently disabled.');
        }

        // Require authentication
        if (! auth()->check()) {
            return redirect()->route('login')
                ->with('intended', $request->fullUrl());
        }

        if (! auth()->user()->canAccess('communication')) {
            return $this->denyAccess($request, 'You do not have permission to access Communication OS.');
        }

        return $next($request);
    }
}
