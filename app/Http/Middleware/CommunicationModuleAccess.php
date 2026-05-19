<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CommunicationModuleAccess
{
    /**
     * Handle an incoming request.
     *
     * Enforces that the Communication OS module is:
     * 1. Enabled in config
     * 2. Accessible by the authenticated user's role
     *
     * Role-based granularity is expanded in Session 11 with Policies.
     * For now: any authenticated user can access the module.
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

        // Future: role-based checks via Gate / Policy
        // Gate::authorize('access-communication-module');

        return $next($request);
    }
}
