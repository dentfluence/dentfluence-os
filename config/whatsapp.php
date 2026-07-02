<?php

/**
 * config/whatsapp.php — WhatsApp Cloud API (outbound), Phase B item 1.2.
 * ----------------------------------------------------------------------------
 * Everything is .env-driven so moving from local (Laragon) to the VPS is a
 * config change only — no code edits. Outbound send works from localhost; only
 * INBOUND webhooks need a public URL (those already live in routes/api.php and
 * read their secrets from config/prm.php).
 *
 * After changing .env, run:  php artisan config:clear
 */
return [

    // Master switch for OUTBOUND sending. If false, the app behaves as before
    // (no messages go out). Inbound webhook is unaffected (config/prm.php).
    'enabled' => filter_var(env('WHATSAPP_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    // SAFETY: when true, nothing is actually sent to Meta — the payload is built,
    // logged, and recorded as status "dry_run" so you can verify exactly what
    // WOULD go out before going live. Flip to false for real sends.
    'dry_run' => filter_var(env('WHATSAPP_DRY_RUN', true), FILTER_VALIDATE_BOOLEAN),

    // ── Meta Cloud API credentials (from Meta Business / developers.facebook.com)
    'graph_version'       => env('WHATSAPP_GRAPH_VERSION', 'v21.0'),
    'phone_number_id'     => env('WHATSAPP_PHONE_NUMBER_ID'),     // the sending number's ID
    'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'), // WABA id (needed for templates, Chunk 4)
    'access_token'        => env('WHATSAPP_ACCESS_TOKEN'),        // permanent system-user token

    // Default country code prepended to local numbers with no country code
    // (India = 91). Used by phone normalization so "98765 43210" becomes
    // "919876543210" as Meta expects.
    'default_country_code' => env('WHATSAPP_DEFAULT_CC', '91'),

    // Network timeout (seconds) for calls to the Graph API.
    'timeout' => (int) env('WHATSAPP_TIMEOUT', 15),

    // ── DPDP consent gate (Chunk 2 uses these) ────────────────────────────────
    // Before sending, we check the patient has GRANTED the matching consent
    // purpose. These keys map to rows seeded by ConsentPurposeSeeder.
    'consent' => [
        // Service/transactional messages (reminders, replies to their query).
        'service_purpose_key'   => env('WHATSAPP_CONSENT_SERVICE_KEY', 'whatsapp_comms'),
        // Promotional/marketing messages — stricter, separate consent.
        'marketing_purpose_key' => env('WHATSAPP_CONSENT_MARKETING_KEY', 'marketing_promotions'),
    ],

    // ── Message templates (Chunk 4) ───────────────────────────────────────────
    // Templates are the ONLY way to message a patient OUTSIDE Meta's 24-hour
    // window (reminders, recalls, confirmations). Each template here must ALSO
    // exist and be APPROVED in your Meta WhatsApp Manager under the same name +
    // language. The `meta_name` is what Meta knows it as (env-overridable so you
    // can rename without code changes).
    //
    //   body_vars : the ordered variables your approved template body uses
    //               ({{1}}, {{2}}, …). The app fills them in this order.
    //   category  : 'service' (transactional → whatsapp_comms consent) or
    //               'marketing' (promotional → marketing_promotions consent).
    //   sample    : a local preview string shown in the inbox (NOT sent to Meta).
    'templates' => [

        'appointment_reminder' => [
            'meta_name' => env('WA_TPL_APPOINTMENT_REMINDER', 'appointment_reminder'),
            'language'  => 'en',
            'category'  => 'service',
            'label'     => 'Appointment reminder',
            'body_vars' => ['name', 'date', 'time'],
            'sample'    => 'Hi {{1}}, this is a reminder of your dental appointment on {{2}} at {{3}}. Reply here if you need to reschedule.',
        ],

        'appointment_confirmation' => [
            'meta_name' => env('WA_TPL_APPOINTMENT_CONFIRMATION', 'appointment_confirmation'),
            'language'  => 'en',
            'category'  => 'service',
            'label'     => 'Appointment confirmation',
            'body_vars' => ['name', 'date', 'time'],
            'sample'    => 'Hi {{1}}, your appointment is confirmed for {{2}} at {{3}}. See you then!',
        ],

        'recall_due' => [
            'meta_name' => env('WA_TPL_RECALL_DUE', 'recall_due'),
            'language'  => 'en',
            'category'  => 'service',
            'label'     => 'Recall due (hygiene / check-up)',
            'body_vars' => ['name', 'treatment'],
            'sample'    => 'Hi {{1}}, it has been a while since your {{2}}. It may be time to book your next visit — reply here and we will help you schedule.',
        ],

        'payment_reminder' => [
            'meta_name' => env('WA_TPL_PAYMENT_REMINDER', 'payment_reminder'),
            'language'  => 'en',
            'category'  => 'service',
            'label'     => 'Payment reminder',
            'body_vars' => ['name', 'amount'],
            'sample'    => 'Hi {{1}}, our records show a pending balance of {{2}}. Reply here for any questions or to pay.',
        ],

        'lab_ready' => [
            'meta_name' => env('WA_TPL_LAB_READY', 'lab_ready'),
            'language'  => 'en',
            'category'  => 'service',
            'label'     => 'Lab work ready',
            'body_vars' => ['name', 'work'],
            'sample'    => 'Hi {{1}}, good news — your {{2}} is back from the lab and ready. Please reply here to book your fitting appointment.',
        ],

        'review_request' => [
            'meta_name' => env('WA_TPL_REVIEW_REQUEST', 'review_request'),
            'language'  => 'en',
            'category'  => 'service',
            'label'     => 'Review request',
            'body_vars' => ['name', 'link'],
            'sample'    => 'Hi {{1}}, thank you for visiting us! We would love your feedback — please tap: {{2}}',
        ],

        'festive_offer' => [
            'meta_name' => env('WA_TPL_FESTIVE_OFFER', 'festive_offer'),
            'language'  => 'en',
            'category'  => 'marketing', // requires marketing_promotions consent
            'label'     => 'Festive offer (promotional)',
            'body_vars' => ['name', 'offer'],
            'sample'    => 'Hi {{1}}, {{2}} Reply STOP to opt out.',
        ],
    ],
];
