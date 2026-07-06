<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RespondsWithAccessDenied;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware: EnsureMarketingActive
 *
 * Checks that a row with slug='marketing' exists in the modules table.
 * If the module has not been provisioned / is not active, deny access.
 *
 * Usage (applied automatically via routes/marketing.php group):
 *   ->middleware('marketing.active')
 */
class EnsureMarketingActive
{
    use RespondsWithAccessDenied;

    public function handle(Request $request, Closure $next): Response
    {
        $exists = DB::table('modules')
            ->where('slug', 'marketing')
            ->exists();

        if (! $exists) {
            return $this->denyAccess($request, 'The Marketing module is not active on this account.');
        }

        return $next($request);
    }
}
