<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware: EnsureMarketingActive
 *
 * Checks that a row with slug='marketing' exists in the modules table.
 * If the module has not been provisioned / is not active, abort 403.
 *
 * Usage (applied automatically via routes/marketing.php group):
 *   ->middleware('marketing.active')
 */
class EnsureMarketingActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $exists = DB::table('modules')
            ->where('slug', 'marketing')
            ->exists();

        if (! $exists) {
            abort(403, 'The Marketing module is not active on this account.');
        }

        return $next($request);
    }
}
