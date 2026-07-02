<?php

/*
|--------------------------------------------------------------------------
| Security settings (Phase A)
|--------------------------------------------------------------------------
| One place for the app's security toggles. Read by SecureWebHeaders +
| SecureApiHeaders middleware and AppServiceProvider. Override any of these
| in your .env file.
|
| Safe defaults: nothing here will break local development. HTTPS forcing and
| HSTS only activate in production; the Content-Security-Policy is OFF by
| default (set SECURITY_CSP to a policy string to switch it on once you've
| tested it — a strict CSP can block inline scripts/styles).
*/

return [

    // Redirect all http:// traffic to https://. On automatically in production.
    'force_https' => (bool) env('FORCE_HTTPS', env('APP_ENV') === 'production'),

    // HTTP Strict Transport Security — tells browsers "always use HTTPS here".
    // Only sent over a secure connection. max-age in seconds (default ~1 year).
    'hsts'        => (bool) env('SECURITY_HSTS', true),
    'hsts_max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),

    // Clickjacking protection. SAMEORIGIN lets the app frame its own pages
    // (e.g. PDF/receipt previews); DENY would block those.
    'frame_options' => env('SECURITY_FRAME_OPTIONS', 'SAMEORIGIN'),

    // Referrer + MIME-sniffing protections.
    'referrer_policy' => env('SECURITY_REFERRER_POLICY', 'strict-origin-when-cross-origin'),

    // Content-Security-Policy. EMPTY = not sent (safe default). Provide a full
    // policy string to enable, e.g.
    //   "default-src 'self'; img-src 'self' data:; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline'"
    'csp' => env('SECURITY_CSP', ''),

];
