<?php

/**
 * Relationship Score Engine — Configuration
 *
 * Phase 6, Dentfluence Relationship Engine
 *
 * All factor weights MUST sum to 100.
 * Adjust per clinic by publishing this config and editing weights.
 *
 * Score range: 0–100 (stored in relationships.score)
 * Recalculated automatically when any event in 'recalculate_on_events' fires
 * through the ActivityEngine → RelationshipScoreEngine::recalculate() job.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Score Factors
    |--------------------------------------------------------------------------
    |
    | Each factor contributes `weight` points (max) to the total score.
    | RelationshipScoreEngine calculates a 0–1 normalised value per factor,
    | then multiplies by weight and sums all factors.
    |
    */
    'factors' => [

        'visit_frequency' => [
            'weight'      => 25,
            'description' => 'How regularly the patient visits (based on appointment history)',
            // Full score if visited within this many days
            'ideal_days'  => 180,
        ],

        'recall_compliance' => [
            'weight'      => 20,
            'description' => 'Recall follow-up rate — did the patient book when recalled?',
        ],

        'treatment_completion' => [
            'weight'      => 20,
            'description' => 'Treatment plan completion rate — plans marked completed vs created',
        ],

        'communication_response' => [
            'weight'      => 15,
            'description' => 'Response rate to outbound calls/messages (CommunicationQueue outcomes)',
        ],

        'membership_active' => [
            'weight'      => 10,
            'description' => 'Patient has an active membership',
        ],

        'referral_activity' => [
            'weight'      => 10,
            'description' => 'Referrals given by this patient (tracked in leads.referral_source)',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Events That Trigger Score Recalculation
    |--------------------------------------------------------------------------
    |
    | When ActivityEngine logs any of these events, RecalculateRelationshipScoreJob
    | is dispatched (queued, async) to recalculate and save the score.
    |
    */
    'recalculate_on_events' => [
        'appointment.completed',
        'recall.responded',
        'treatment.completed',
        'membership.enrolled',
        'referral.created',
    ],

    /*
    |--------------------------------------------------------------------------
    | Score Bands
    |--------------------------------------------------------------------------
    |
    | Used by AnalyticsController for distribution charts.
    |
    */
    'bands' => [
        'high'   => ['min' => 80, 'max' => 100, 'label' => 'High (80–100)',   'color' => '#1a7a45'],
        'medium' => ['min' => 60, 'max' => 79,  'label' => 'Medium (60–79)', 'color' => '#a05c00'],
        'low'    => ['min' => 0,  'max' => 59,  'label' => 'Low (<60)',      'color' => '#b52020'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache TTL (seconds)
    |--------------------------------------------------------------------------
    */
    'cache_ttl' => 3600, // 1 hour — analytics queries are cached for this long

];
