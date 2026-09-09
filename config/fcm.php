<?php

/**
 * N-5 — Firebase Cloud Messaging (2026-09-09).
 *
 * Push is the phone's copy of a POPUP-level notification, and nothing else.
 * A bell-level row never pushes, whatever the Settings matrix says about the
 * 📱 tick — the dispatcher already enforces that when it writes the row.
 *
 * SETUP (Sumit, once):
 *   1. console.firebase.google.com → create the project → add an Android app
 *      with the package name from android/app/build.gradle.
 *   2. Download google-services.json → dentfluence_mobile/android/app/.
 *   3. Project settings → Service accounts → Generate new private key.
 *      Save the JSON OUTSIDE the repo (it is a credential), e.g.
 *      /opt/dentfluence/secrets/fcm.json, and point FCM_CREDENTIALS at it.
 *   4. .env:  FCM_ENABLED=true
 *             FCM_PROJECT_ID=your-project-id
 *             FCM_CREDENTIALS=/opt/dentfluence/secrets/fcm.json
 *
 * Until FCM_ENABLED is true the sender is a no-op that logs and marks the row
 * handled — the web popup and the bell keep working exactly as they do now.
 */
return [

    'enabled' => env('FCM_ENABLED', false),

    'project_id' => env('FCM_PROJECT_ID'),

    // Absolute path to the service-account JSON. NEVER commit this file.
    'credentials' => env('FCM_CREDENTIALS'),

    /*
     * Quiet hours — the clinic's working window. A push outside it is dropped,
     * not queued for the morning: a popup about a patient standing at the desk
     * is worthless at 11pm, and a phone buzzing at midnight is how staff turn
     * notifications off for good. The in-app bell still carries it.
     *
     * Admins are exempt (system failures, cancelled invoices).
     */
    'quiet_hours' => [
        'enabled' => env('FCM_QUIET_HOURS', true),
        'from'    => env('FCM_QUIET_FROM', '21:30'),  // push stops at
        'until'   => env('FCM_QUIET_UNTIL', '09:30'), // push resumes at
        'exempt_roles' => ['admin'],
    ],

    // Android notification channel the app creates (see main.dart).
    'android_channel' => 'dentfluence_desk',
];
