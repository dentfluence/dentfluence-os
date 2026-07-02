<?php

/*
|------------------------------------------------------------------------------
| PRM (Patient Relationship Manager) — config
|------------------------------------------------------------------------------
| Knobs for the PRM pipeline and its AI layer. Change a value, then run
| `php artisan config:clear`. The AI features here reuse the SAME local Ollama
| stack as Tulip / Voice Notes — no cloud, no API keys, no per-lead cost.
*/

return [

    // ── AI lead enrichment (Phase 1) ──────────────────────────────────────────
    'ai' => [

        // Master switch for AI enrichment. Set PRM_AI_ENABLED=false in .env +
        // `php artisan config:clear` to turn it off everywhere instantly.
        'enabled' => filter_var(env('PRM_AI_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

        // Run enrichment automatically when a new lead is created.
        'auto_on_create' => filter_var(env('PRM_AI_AUTO', true), FILTER_VALIDATE_BOOLEAN),

        // Which local model classifies the lead. Blank → falls back to the
        // Tulip default (config('assistant.model'), usually qwen2.5:7b).
        'model' => env('PRM_AI_MODEL', null),

        // Show the AI summary line + tags on board cards. Flip off to hide the
        // AI bits from the board without disabling enrichment itself.
        'show_on_cards' => filter_var(env('PRM_AI_SHOW_CARDS', true), FILTER_VALIDATE_BOOLEAN),
    ],

    // ── Auto-assign / lead routing (Phase 2a) ─────────────────────────────────
    'routing' => [

        // Master switch. PRM_ROUTING_ENABLED=false + config:clear to turn off.
        'enabled' => filter_var(env('PRM_ROUTING_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

        // Auto-assign each NEW lead the moment it's created.
        'auto_on_create' => filter_var(env('PRM_ROUTING_AUTO', true), FILTER_VALIDATE_BOOLEAN),

        // Don't override a lead a human already assigned (front desk picked someone).
        'respect_manual' => filter_var(env('PRM_ROUTING_RESPECT_MANUAL', true), FILTER_VALIDATE_BOOLEAN),

        // Which staff ROLES can receive leads (role slugs from the roles table).
        'assignable_roles' => ['front_desk', 'manager'],

        // How to choose among eligible staff:
        //   'least_loaded' = whoever has the fewest open leads (self-balancing, default)
        //   'random'       = pick at random
        'strategy' => env('PRM_ROUTING_STRATEGY', 'least_loaded'),

        // Only assign to staff in the lead's own branch. Off by default because
        // leads aren't branch-scoped yet (revisit in Phase 4).
        'restrict_to_branch' => filter_var(env('PRM_ROUTING_BRANCH', false), FILTER_VALIDATE_BOOLEAN),

        // OPTIONAL overrides: send specific treatments to a specific role slug.
        // e.g. high-value cases to a manager. Keys = lowercased treatment label.
        // Leave empty to route everything to the general pool above.
        'treatment_roles' => [
            // 'dental implant' => 'manager',
            // 'smile makeover' => 'manager',
        ],
    ],

    // ── Website chatbot (Phase 6) ─────────────────────────────────────────────
    'chatbot' => [
        'enabled'  => filter_var(env('PRM_CHATBOT_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        // Shown in the widget header + opening message.
        'greeting' => env('PRM_CHATBOT_GREETING', 'Hi! 👋 How can we help with your smile today?'),
    ],

    // ── Inbound channel webhooks (Phase 4) ────────────────────────────────────
    'webhooks' => [
        // Skip creating a duplicate lead if the same phone came in within this
        // many minutes (guards against double form submits).
        'dedupe_minutes' => (int) env('PRM_WEBHOOK_DEDUPE_MIN', 10),

        // 4a — Website contact form.
        'website' => [
            'enabled' => filter_var(env('PRM_WEBHOOK_WEBSITE_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
            // Shared secret the website must send (header X-PRM-Token or `token`
            // field). MUST be set in .env or the endpoint rejects everything.
            'secret'  => env('PRM_WEBHOOK_WEBSITE_SECRET'),
        ],

        // 4b — Meta Lead Ads (Facebook / Instagram lead forms).
        'meta' => [
            'enabled'       => filter_var(env('PRM_WEBHOOK_META_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
            // Token YOU choose in the Meta app webhook setup (GET verification).
            'verify_token'  => env('PRM_META_VERIFY_TOKEN'),
            // Meta App Secret — used to validate the X-Hub-Signature-256 of POSTs.
            'app_secret'    => env('PRM_META_APP_SECRET'),
            // Page access token — needed to fetch the actual lead fields from the
            // Graph API using the leadgen_id Meta sends.
            'access_token'  => env('PRM_META_PAGE_TOKEN'),
            'graph_version' => env('PRM_META_GRAPH_VERSION', 'v19.0'),
            // Which channel to tag these as (facebook | instagram).
            'default_source'=> env('PRM_META_SOURCE', 'facebook'),
        ],

        // 4c — WhatsApp Cloud API (inbound messages become leads).
        'whatsapp' => [
            'enabled'      => filter_var(env('PRM_WEBHOOK_WA_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
            'verify_token' => env('PRM_WA_VERIFY_TOKEN'),
            'app_secret'   => env('PRM_WA_APP_SECRET'),
        ],
    ],

    // ── AI draft replies (Phase 3) ────────────────────────────────────────────
    'replies' => [
        // Master switch. PRM_REPLIES_ENABLED=false + config:clear to turn off.
        'enabled' => filter_var(env('PRM_REPLIES_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

        // Local model for drafting. Blank → Tulip default (config('assistant.model')).
        'model' => env('PRM_REPLIES_MODEL', null),

        // Clinic name used in sign-offs. Defaults to the app name.
        'clinic_name' => env('PRM_CLINIC_NAME', null),

        // Channels the front desk can draft for.
        'channels' => ['whatsapp', 'sms', 'email'],
    ],

    // ── Follow-up reminders (Phase 2b) ────────────────────────────────────────
    'followups' => [
        // Auto-create follow-up reminders (in the Follow-up Engine) when a lead
        // enters a pipeline stage. Rules live in config/followup_rules.php under
        // 'prm_stage_changed'. PRM_FOLLOWUPS_ENABLED=false + config:clear = off.
        'enabled' => filter_var(env('PRM_FOLLOWUPS_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    ],

    // ── Treatment → rough ₹ value bands ───────────────────────────────────────
    // The AI classifies the treatment; we look the value up HERE so the number
    // is deterministic and never hallucinated. Tune these to your pricing.
    // Keys must match (lowercased) the treatment labels the AI returns.
    'value_bands' => [
        'dental implant'        => 35000,
        'smile makeover'        => 60000,
        'veneers'               => 45000,
        'braces / orthodontics' => 40000,
        'aligners'              => 55000,
        'crown & bridge'        => 12000,
        'root canal treatment'  => 8000,
        'dentures'              => 15000,
        'gum treatment'         => 9000,
        'teeth whitening'       => 6000,
        'pediatric dentistry'   => 3000,
        'scaling & polishing'   => 1500,
        'other'                => 2000,
    ],

    // ── Channel ad spend (Phase 5 — Channel ROI report) ───────────────────────
    // Monthly ad spend per source in ₹, used to compute cost-per-lead, cost-per-
    // acquisition and ROI. Leave 0 / omit a channel if it has no paid spend.
    // Keys must match lead_source enum values.
    'ad_spend' => [
        'google_ads' => 0,
        'instagram'  => 0,
        'facebook'   => 0,
        'seo'        => 0,
    ],

    // The treatment labels the AI is allowed to choose from (kept in sync with
    // PrmController::formData()'s treatment list). Forces clean, mappable output.
    'treatments' => [
        'Dental Implant', 'Teeth Whitening', 'Braces / Orthodontics', 'Root Canal Treatment',
        'Crown & Bridge', 'Scaling & Polishing', 'Aligners', 'Veneers', 'Dentures',
        'Smile Makeover', 'Pediatric Dentistry', 'Gum Treatment', 'Other',
    ],
];
