<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SecureWebHeaders (Phase A — security)
 * -------------------------------------
 * Adds defence-in-depth HTTP headers to every WEB response (the API has its own
 * SecureApiHeaders). All values come from config/security.php so they can be
 * tuned per-environment without code changes.
 *
 *  - X-Content-Type-Options: nosniff   → stop MIME-type guessing
 *  - X-Frame-Options                   → clickjacking protection (SAMEORIGIN)
 *  - Referrer-Policy                   → limit referrer leakage
 *  - Strict-Transport-Security (HSTS)  → force HTTPS in the browser (secure conns only)
 *  - Content-Security-Policy           → only if explicitly configured (off by default)
 */
class SecureWebHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', config('security.frame_options', 'SAMEORIGIN'));
        $response->headers->set('Referrer-Policy', config('security.referrer_policy', 'strict-origin-when-cross-origin'));

        // HSTS only makes sense (and is only honoured) over HTTPS.
        if (config('security.hsts') && $request->isSecure()) {
            $maxAge = (int) config('security.hsts_max_age', 31536000);
            $response->headers->set('Strict-Transport-Security', "max-age={$maxAge}; includeSubDomains");
        }

        // CSP is opt-in — only sent when a policy string is configured.
        $csp = (string) config('security.csp', '');
        if ($csp !== '') {
            $response->headers->set('Content-Security-Policy', $csp);
        }

        return $response;
    }
}
