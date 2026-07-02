<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SecureApiHeaders
 * ----------------
 * Adds a few safe, standard security headers to every API response.
 * Applied to the whole "api" middleware group (see bootstrap/app.php).
 */
class SecureApiHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-XSS-Protection', '0');

        // HSTS — force HTTPS at the browser level (secure connections only). (Phase A)
        if (config('security.hsts') && $request->isSecure()) {
            $maxAge = (int) config('security.hsts_max_age', 31536000);
            $response->headers->set('Strict-Transport-Security', "max-age={$maxAge}; includeSubDomains");
        }

        return $response;
    }
}
