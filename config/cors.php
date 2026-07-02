<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS)
|--------------------------------------------------------------------------
| Controls which web origins may call the API from a browser.
| The Flutter app and Postman are NOT browsers, so they're unaffected by this.
| For the future Next.js web app, list its URL in CORS_ALLOWED_ORIGINS
| (comma-separated) in your .env file.
*/

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    // Security (Phase A): default to the app's own URL instead of "*". The
    // Flutter app and Postman aren't browsers, so they're unaffected. When the
    // Next.js web app ships, list its URL(s) in CORS_ALLOWED_ORIGINS (comma-sep).
    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', rtrim((string) env('APP_URL', 'http://localhost'), '/'))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
