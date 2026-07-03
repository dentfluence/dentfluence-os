<?php

/**
 * Insights Engine — Configuration (Phase 6 · Slice 1)
 *
 * Three independent, event-fed signals replace the single 0–100
 * RelationshipScoreEngine score (kept running untouched until a future
 * cutover — see config/relationship_score.php). Marketing's own engagement
 * score (App\Services\Marketing\MarketingScoreService) stays a distinct,
 * unrelated concept per the target architecture.
 *
 * Each signal is computed by its own calculator
 * (app/Services/Insights/*SignalCalculator.php), reading only the raw tables
 * that signal needs. Nothing here decides anything — Insights summarizes,
 * it never acts (§9 of the target architecture).
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Relationship Health — is this bond warming or cooling?
    |--------------------------------------------------------------------------
    | Weights must sum to 100. Each factor is normalised 0.0–1.0 then
    | multiplied by its weight; the sum (0–100) is the health score.
    */
    'health' => [
        'factors' => [
            'visit_regularity' => [
                'weight'      => 50,
                'description' => 'How regularly the patient visits (based on completed appointments).',
                'ideal_days'  => 180, // full score if seen within this window, decays linearly to 2x
            ],
            'communication_responsiveness' => [
                'weight'      => 50,
                'description' => 'Response rate to outbound calls/messages (CommunicationQueue outcomes).',
            ],
        ],
        'bands' => [
            'warming' => ['min' => 70, 'max' => 100, 'label' => 'Warming (70–100)'],
            'steady'  => ['min' => 40, 'max' => 69,  'label' => 'Steady (40–69)'],
            'cooling' => ['min' => 0,  'max' => 39,  'label' => 'Cooling (<40)'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Lifetime Value — realized + projected
    |--------------------------------------------------------------------------
    | No weighted score: value_realized is the sum of all recorded payments;
    | value_projected adds accepted-but-not-yet-fully-paid treatment plan value
    | (the near-term expected value). `level` buckets value_projected into a
    | simple tier for at-a-glance display.
    */
    'ltv' => [
        'tiers' => [
            'platinum' => ['min' => 100000, 'label' => 'Platinum (≥ ₹1,00,000)'],
            'gold'     => ['min' => 40000,  'label' => 'Gold (≥ ₹40,000)'],
            'silver'   => ['min' => 10000,  'label' => 'Silver (≥ ₹10,000)'],
            'bronze'   => ['min' => 0,      'label' => 'Bronze (< ₹10,000)'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Risk — dormancy, missed recalls, no-shows, unanswered outreach
    |--------------------------------------------------------------------------
    | Weights must sum to 100. Higher score = higher risk (opposite polarity
    | to Health, deliberately — they are independent signals, not mirrors).
    */
    'risk' => [
        'factors' => [
            'dormancy' => [
                'weight'      => 40,
                'description' => 'Days since last completed visit relative to the expected recall interval.',
                'recall_interval_days' => 180,
            ],
            'no_show_rate' => [
                'weight'      => 30,
                'description' => 'Proportion of recent appointments that were no-shows or last-minute cancellations.',
                'lookback'    => 10, // consider the last N appointments
            ],
            'unanswered_outreach' => [
                'weight'      => 30,
                'description' => 'Recall/communication attempts that queued but produced no positive outcome.',
            ],
        ],
        'bands' => [
            'low'      => ['min' => 0,  'max' => 29,  'label' => 'Low (<30)'],
            'medium'   => ['min' => 30, 'max' => 59,  'label' => 'Medium (30–59)'],
            'high'     => ['min' => 60, 'max' => 79,  'label' => 'High (60–79)'],
            'critical' => ['min' => 80, 'max' => 100, 'label' => 'Critical (80–100)'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Events that trigger an incremental recompute
    |--------------------------------------------------------------------------
    | When ActivityEngine logs any of these events for a relationship,
    | RecalculateInsightSignalsJob is queued to refresh that relationship's 3
    | signals. Gated by the `insights.signals` flag — while it is off (the
    | default), the listener still hears the event but does nothing, so
    | behaviour is byte-for-byte unchanged until the flag is flipped.
    |
    | Includes events already logged today (recall.queued, journey.transitioned,
    | lead.created) AND target events other phases will wire up later
    | (appointment.completed, payment.received, treatment.completed) — adding
    | a publisher for those later needs no change here, same pattern already
    | used by config/relationship_score.php.
    */
    'recalculate_on_events' => [
        'lead.created',
        'recall.queued',
        'recall.responded',
        'journey.transitioned',
        'appointment.completed',
        'appointment.no_show',
        'payment.received',
        'treatment.completed',
        'membership.enrolled',
        'referral.created',
    ],

];
