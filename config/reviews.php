<?php

/**
 * config/reviews.php — reputation / review-request loop (Phase B item 2.4).
 * All .env-driven so the VPS switch is config-only.
 *
 * After changing .env: php artisan config:clear
 */
return [

    // Master switch for sending review requests.
    'enabled' => filter_var(env('REVIEWS_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    // A rating at or above this (out of 5) counts as "happy" → routed to Google.
    // Below it, the feedback is kept private/internal to be addressed.
    'positive_threshold' => (int) env('REVIEWS_POSITIVE_THRESHOLD', 4),

    // Your public Google review link (e.g. https://g.page/r/XXXX/review).
    // Leave blank to keep everything internal (no Google routing).
    'google_review_url' => env('REVIEWS_GOOGLE_URL'),

    // Clinic name shown on the rating page. Falls back to the app name.
    'clinic_name' => env('REVIEWS_CLINIC_NAME', null),

    // How long a review link stays valid (days).
    'link_ttl_days' => (int) env('REVIEWS_LINK_TTL_DAYS', 14),
];
