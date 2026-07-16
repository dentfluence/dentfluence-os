<?php

/*
|--------------------------------------------------------------------------
| Dentfluence Feature Flags (Architecture Baseline v1.0 — Phase 0)
|--------------------------------------------------------------------------
|
| Every migration cutover in the Implementation Blueprint is gated by a flag
| declared HERE. There are no hard-coded flags anywhere else in the code.
|
| Resolution order (see App\Support\Features\FeatureFlagService):
|   1. Per-clinic override   (feature_flags row for a branch_id)
|   2. Global override       (feature_flags row with branch_id = null)
|   3. The 'default' below    (which may itself read an env var)
|   4. false
|
| RULE: every flag defaults to the CURRENT (legacy) behaviour. Phase 0 ships
| all of them OFF, so nothing changes until a later phase flips one.
|
*/

return [

    /*
    | The cache TTL (seconds) for the feature_flags override table. Keep short;
    | flags are read often but flipped rarely. Set to 0 to disable caching.
    */
    'cache_ttl' => (int) env('FEATURE_CACHE_TTL', 60),

    /*
    | Flag registry. Key => ['default' => bool, 'description' => string].
    | Adding a flag here is the ONLY supported way to introduce one.
    */
    'flags' => [

        // ── Phase 0 (Safety & Foundations) ──────────────────────────────
        'guard.fail_closed' => [
            'default'     => (bool) env('FEATURE_GUARD_FAIL_CLOSED', false),
            'description' => 'CommunicationGuard blocks (instead of failing open) when a guard check errors.',
        ],
        'guard.consent_required' => [
            'default'     => (bool) env('FEATURE_GUARD_CONSENT_REQUIRED', false),
            'description' => 'CommunicationGuard enforces consent. Consent can NEVER be overridden by urgency.',
        ],

        // ── Phase 1 (Relationship Foundation) ───────────────────────────
        // Flipped on 2026-07-04: the Relationships module was showing as
        // empty/"dummy" because this was never turned on, so no Relationship
        // row was ever created for real patients. Turning this on links new
        // patients going forward; existing patients still need a one-time
        // `php artisan relationship:backfill --apply --force` run (see runbook).
        'identity.link_patient'        => ['default' => true, 'description' => 'Auto-link new patients to a Master Relationship.'],
        'identity.reads_relationship'  => ['default' => false, 'description' => 'Reads resolve through the relationship spine.'],
        'activity.single_ledger_reads' => ['default' => false, 'description' => 'Timeline reads from the single Activity ledger.'],
        'journey.authoritative'        => ['default' => false, 'description' => 'Journeys are the authoritative pipeline state.'],
        'relationship.pipeline_journey_column' => ['default' => false, 'description' => 'PRE Lead Pipeline shows the shadow relationship-journey state on each card (context only; journeys are not authoritative until journey.authoritative).'],
        'relationship.opportunity_journey_column' => ['default' => false, 'description' => 'PRE Opportunity Pipeline shows the shadow opportunity-journey state on each card (context only; journeys are not authoritative until journey.authoritative).'],

        // ── Phase 2 (Automation) ────────────────────────────────────────
        'automation.engine'   => ['default' => false, 'description' => 'Automation Engine owns recall/reminders/retries/cooldowns.'],
        'rules.single_engine' => ['default' => false, 'description' => 'Legacy FollowUpRulesService retired in favour of the Rules Engine.'],

        // ── Phase 3 (Work surfaces) ─────────────────────────────────────
        'today.projection'         => ['default' => false, 'description' => "Today's Actions served from a projection (no live 12-domain reads)."],
        'tasks.human_system_split' => ['default' => false, 'description' => 'Task Engine separates Human and System tasks.'],

        // ── Phase 4 (Communication) ─────────────────────────────────────
        'comm.single_gateway'       => ['default' => false, 'description' => 'All patient sends route through the Communication Engine.'],
        'guard.full_8factor'        => ['default' => false, 'description' => 'Full 8-factor Communication Guard evaluation.'],
        'notifications.single_store'=> ['default' => false, 'description' => 'One internal notification store.'],

        // ── Phase 5 (Workflow) ──────────────────────────────────────────
        'workflow.engine'   => ['default' => false, 'description' => 'Workflow Engine active (linear-first).'],
        'marketing.via_guard' => ['default' => false, 'description' => 'Marketing sends pass through the Communication Guard.'],

        // ── Phase 6 (Read & Insights) ───────────────────────────────────
        'insights.signals' => ['default' => false, 'description' => 'Insights multi-signal projections replace the single score.'],
        'search.index'     => ['default' => false, 'description' => 'Search served from the index projection.'],

        // ── Phase 7 (Integration) — per-provider pattern ────────────────
        'integration.whatsapp' => ['default' => false, 'description' => 'WhatsApp routed through the Integration Engine boundary.'],
        'integration.google'   => ['default' => false, 'description' => 'Google (Calendar/Reviews, Business Profile OAuth + publish, Analytics) routed through Integration.'],
        'integration.meta'     => ['default' => false, 'description' => 'Meta (OAuth, Instagram/Facebook publish, Lead Ads webhook) routed through Integration.'],
        'integration.website'  => ['default' => false, 'description' => "The clinic's own website (WordPress publish) routed through Integration. Added Slice 3 — named in the Phase 7 deliverables but missing from this table until now."],
        'integration.payments' => ['default' => false, 'description' => 'Payment gateways routed through Integration.'],
        'integration.abdm'     => ['default' => false, 'description' => 'ABDM routed through Integration.'],

        // ── Marketing re-engineering (V3) — provider abstraction ─────────
        // Docs: docs/marketing-module-reengineering-plan.md §8-9.
        // OFF = Marketing reads Revenue/Patient/Treatment/Media/Review/
        // Appointment data from manual entry (Standalone providers) or, for
        // Media/Review, from Marketing's own tables where that's already
        // real data. ON = Integrated providers wire those same interfaces
        // to real Dentfluence records (Invoices, the Reviews system, etc.)
        // instead. Per-clinic override, same mechanism as integration.*.
        'marketing.integrated_providers' => [
            'default'     => false,
            'description' => 'Marketing provider interfaces (Revenue/Patient/Treatment/Media/Review/Appointment) resolve to Integrated implementations instead of Standalone/manual ones.',
        ],

        // ── Case Acceptance Engine (V1 — replaces Smart Presentation) ────
        // Docs: docs/plan-case-acceptance-engine.md (FROZEN architecture) +
        // docs/plan-case-acceptance-engine-implementation.md (milestones).
        // OFF = the existing Smart Presentation module is the only presentation
        // experience (unchanged). ON = the new Case Acceptance Engine (Case
        // Journey builder + /p/{token} patient microsite) is exposed alongside
        // it. Flip OFF at any time to instantly restore Smart Presentation —
        // the engine's tables/services are additive and dormant while off.
        // Stays OFF until validated on real patients.
        'case_acceptance.enabled' => [
            'default'     => (bool) env('FEATURE_CASE_ACCEPTANCE', false),
            'description' => 'Case Acceptance Engine (Case Journey builder + /p/{token} patient microsite) coexists with Smart Presentation. OFF restores Smart Presentation instantly.',
        ],
    ],
];
