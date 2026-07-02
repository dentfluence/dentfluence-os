<?php

/**
 * ============================================================
 * DENTFLUENCE — FOLLOW-UP RULES ENGINE
 * ============================================================
 * File: config/followup_rules.php
 *
 * THIS IS THE SINGLE SOURCE OF TRUTH FOR ALL FOLLOW-UP RULES.
 *
 * To add a new rule tomorrow:
 *   1. Add a new entry in the correct section below.
 *   2. Done. The FollowUpRulesService picks it up automatically.
 *      No touching controllers, no touching services.
 *
 * TRIGGER TYPES:
 *   - treatment_status_changed   → fired when treatment tab status changes
 *   - prm_stage_changed          → fired when lead moves to a pipeline stage
 *   - appointment_event          → fired on appointment events (missed, completed etc.)
 *   - manual                     → staff creates follow-up manually (no auto rules needed)
 *
 * CHANNELS:
 *   call | whatsapp | clinic_visit | any
 *
 * PRIORITIES:
 *   high | medium | low
 *
 * DAY OFFSET:
 *   0 = same day, 1 = next day, etc.
 *   For recalls: use large numbers e.g. 180 = 6 months
 * ============================================================
 */

return [

    // =========================================================
    // SECTION 1: TREATMENT-BASED FOLLOW-UP RULES
    // Triggered when doctor/front desk changes treatment status
    // in the patient profile (Active / Ongoing / Complete tabs)
    // =========================================================

    'treatment_status_changed' => [

        // ---------------------------------------------------------
        // EXTRACTION
        // ---------------------------------------------------------
        'extraction' => [
            'active' => [
                // When treatment marked Active → schedule post-op reviews
                [
                    'label'       => 'Post-Op Day 1 Review',
                    'day_offset'  => 1,
                    'channel'     => 'call',
                    'priority'    => 'high',
                    'note'        => 'Check pain, swelling and bleeding after extraction.',
                    'appears_in'  => ['communication_manager', 'daily_huddle'],
                ],
                [
                    'label'       => 'Post-Op Day 5 Review',
                    'day_offset'  => 5,
                    'channel'     => 'call',
                    'priority'    => 'medium',
                    'note'        => 'Check healing progress after extraction.',
                    'appears_in'  => ['communication_manager', 'daily_huddle'],
                ],
                [
                    'label'       => 'Post-Op Day 10 Review',
                    'day_offset'  => 10,
                    'channel'     => 'call',
                    'priority'    => 'medium',
                    'note'        => 'Final check — confirm full healing.',
                    'appears_in'  => ['communication_manager', 'daily_huddle'],
                ],
            ],
            'complete' => [
                // When treatment marked Complete → 6-month recall
                [
                    'label'       => '6-Month Recall',
                    'day_offset'  => 180,
                    'channel'     => 'call',
                    'priority'    => 'low',
                    'note'        => 'Routine 6-month recall after extraction.',
                    'appears_in'  => ['communication_manager', 'daily_huddle'],
                ],
            ],
        ],

        // ---------------------------------------------------------
        // ROOT CANAL TREATMENT (RCT)
        // ---------------------------------------------------------
        'rct' => [
            'active' => [
                [
                    'label'       => 'Post-RCT Day 1 Check',
                    'day_offset'  => 1,
                    'channel'     => 'call',
                    'priority'    => 'high',
                    'note'        => 'Check pain levels and sensitivity after RCT.',
                    'appears_in'  => ['communication_manager', 'daily_huddle'],
                ],
                [
                    'label'       => 'Post-RCT Day 7 Review',
                    'day_offset'  => 7,
                    'channel'     => 'call',
                    'priority'    => 'medium',
                    'note'        => 'Check if crown placement is scheduled.',
                    'appears_in'  => ['communication_manager', 'daily_huddle'],
                ],
            ],
            'complete' => [
                [
                    'label'       => '6-Month Recall',
                    'day_offset'  => 180,
                    'channel'     => 'call',
                    'priority'    => 'low',
                    'note'        => 'Routine 6-month recall after RCT completion.',
                    'appears_in'  => ['communication_manager', 'daily_huddle'],
                ],
            ],
        ],

        // ---------------------------------------------------------
        // DENTAL IMPLANT
        // ---------------------------------------------------------
        'implant' => [
            'active' => [
                [
                    'label'       => 'Post-Surgery Day 1 Call',
                    'day_offset'  => 1,
                    'channel'     => 'call',
                    'priority'    => 'high',
                    'note'        => 'Check pain, swelling and comfort after implant surgery.',
                    'appears_in'  => ['communication_manager', 'daily_huddle'],
                ],
                [
                    'label'       => 'Post-Surgery Day 5 Review',
                    'day_offset'  => 5,
                    'channel'     => 'call',
                    'priority'    => 'high',
                    'note'        => 'Check healing and suture status.',
                    'appears_in'  => ['communication_manager', 'daily_huddle'],
                ],
                [
                    'label'       => '3-Month Osseointegration Check',
                    'day_offset'  => 90,
                    'channel'     => 'clinic_visit',
                    'priority'    => 'high',
                    'note'        => 'Check osseointegration — critical milestone for implant.',
                    'appears_in'  => ['communication_manager', 'daily_huddle'],
                ],
                [
                    'label'       => '6-Month Crown Review',
                    'day_offset'  => 180,
                    'channel'     => 'clinic_visit',
                    'priority'    => 'medium',
                    'note'        => 'Review implant crown and occlusion.',
                    'appears_in'  => ['communication_manager', 'daily_huddle'],
                ],
            ],
            'complete' => [
                [
                    'label'       => '1-Year Implant Recall',
                    'day_offset'  => 365,
                    'channel'     => 'call',
                    'priority'    => 'low',
                    'note'        => 'Annual implant health check.',
                    'appears_in'  => ['communication_manager', 'daily_huddle'],
                ],
            ],
        ],

        // ---------------------------------------------------------
        // ORTHODONTICS (BRACES / ALIGNERS)
        // ---------------------------------------------------------
        'orthodontics' => [
            'active' => [
                [
                    'label'       => 'Ortho Day 3 Comfort Check',
                    'day_offset'  => 3,
                    'channel'     => 'call',
                    'priority'    => 'medium',
                    'note'        => 'Check comfort and compliance with braces/aligners.',
                    'appears_in'  => ['communication_manager', 'daily_huddle'],
                ],
                [
                    'label'       => 'Ortho Monthly Review',
                    'day_offset'  => 30,
                    'channel'     => 'call',
                    'priority'    => 'medium',
                    'note'        => 'Monthly check-in for orthodontic progress.',
                    'appears_in'  => ['communication_manager', 'daily_huddle'],
                ],
            ],
            'complete' => [
                [
                    'label'       => 'Post-Ortho 6-Month Recall',
                    'day_offset'  => 180,
                    'channel'     => 'call',
                    'priority'    => 'low',
                    'note'        => 'Check retention compliance and bite stability.',
                    'appears_in'  => ['communication_manager', 'daily_huddle'],
                ],
            ],
        ],

        // ---------------------------------------------------------
        // TEETH WHITENING
        // ---------------------------------------------------------
        'whitening' => [
            'active' => [
                [
                    'label'       => 'Post-Whitening Day 2 Check',
                    'day_offset'  => 2,
                    'channel'     => 'call',
                    'priority'    => 'low',
                    'note'        => 'Check sensitivity and satisfaction after whitening.',
                    'appears_in'  => ['communication_manager'],
                ],
            ],
            'complete' => [
                [
                    'label'       => 'Whitening 6-Month Recall',
                    'day_offset'  => 180,
                    'channel'     => 'call',
                    'priority'    => 'low',
                    'note'        => 'Touch-up or maintenance reminder.',
                    'appears_in'  => ['communication_manager', 'daily_huddle'],
                ],
            ],
        ],

        // ---------------------------------------------------------
        // SCALING & CLEANING
        // ---------------------------------------------------------
        'scaling' => [
            'active' => [],
            'complete' => [
                [
                    'label'       => 'Scaling 6-Month Recall',
                    'day_offset'  => 180,
                    'channel'     => 'call',
                    'priority'    => 'low',
                    'note'        => 'Remind patient for next routine cleaning.',
                    'appears_in'  => ['communication_manager', 'daily_huddle'],
                ],
            ],
        ],

        // ---------------------------------------------------------
        // CROWN / BRIDGE
        // ---------------------------------------------------------
        'crown_bridge' => [
            'active' => [
                [
                    'label'       => 'Crown Fit Day 3 Check',
                    'day_offset'  => 3,
                    'channel'     => 'call',
                    'priority'    => 'medium',
                    'note'        => 'Check bite and comfort after crown placement.',
                    'appears_in'  => ['communication_manager', 'daily_huddle'],
                ],
            ],
            'complete' => [
                [
                    'label'       => 'Crown 6-Month Recall',
                    'day_offset'  => 180,
                    'channel'     => 'call',
                    'priority'    => 'low',
                    'note'        => 'Check crown integrity and gum health.',
                    'appears_in'  => ['communication_manager', 'daily_huddle'],
                ],
            ],
        ],

        // ---------------------------------------------------------
        // DENTURES
        // ---------------------------------------------------------
        'dentures' => [
            'active' => [
                [
                    'label'       => 'Denture Day 2 Comfort Check',
                    'day_offset'  => 2,
                    'channel'     => 'call',
                    'priority'    => 'high',
                    'note'        => 'Check fit, comfort and any sore spots.',
                    'appears_in'  => ['communication_manager', 'daily_huddle'],
                ],
                [
                    'label'       => 'Denture Day 7 Review',
                    'day_offset'  => 7,
                    'channel'     => 'call',
                    'priority'    => 'medium',
                    'note'        => 'Check adjustment needs.',
                    'appears_in'  => ['communication_manager', 'daily_huddle'],
                ],
            ],
            'complete' => [
                [
                    'label'       => 'Denture 6-Month Recall',
                    'day_offset'  => 180,
                    'channel'     => 'call',
                    'priority'    => 'low',
                    'note'        => 'Check denture fit and oral tissue health.',
                    'appears_in'  => ['communication_manager', 'daily_huddle'],
                ],
            ],
        ],

        // ---------------------------------------------------------
        // GENERAL CONSULTATION
        // (add more treatment types below as needed)
        // ---------------------------------------------------------
        'consultation' => [
            'active' => [],
            'complete' => [
                [
                    'label'       => 'Post-Consultation Annual Recall',
                    'day_offset'  => 365,
                    'channel'     => 'call',
                    'priority'    => 'low',
                    'note'        => 'Annual recall for general consultation patient.',
                    'appears_in'  => ['communication_manager', 'daily_huddle'],
                ],
            ],
        ],

    ],


    // =========================================================
    // SECTION 2: PRM PIPELINE STAGE FOLLOW-UP RULES
    // Triggered when a lead moves to a new stage in PRM
    // =========================================================

    'prm_stage_changed' => [

        'new_lead' => [
            [
                'label'       => 'New Lead First Contact',
                'day_offset'  => 0, // same day
                'channel'     => 'call',
                'priority'    => 'high',
                'note'        => 'First contact attempt for new lead.',
                'appears_in'  => ['communication_manager', 'daily_huddle'],
            ],
        ],

        'contacted' => [
            [
                'label'       => 'Follow-up After Contact',
                'day_offset'  => 2,
                'channel'     => 'call',
                'priority'    => 'medium',
                'note'        => 'Follow up after initial contact.',
                'appears_in'  => ['communication_manager'],
            ],
        ],

        // ── PRM live stage keys (match Lead pipeline stages) ──────────────────
        // These keys mirror the actual PRM stages so stage changes fire rules.
        'appointment' => [
            [
                'label'       => 'Appointment Reminder',
                'day_offset'  => 0, // same day the appointment is set
                'channel'     => 'call',
                'priority'    => 'high',
                'note'        => 'Confirm the upcoming appointment with the lead.',
                'appears_in'  => ['communication_manager', 'daily_huddle'],
            ],
        ],

        'consultation' => [
            [
                'label'       => 'Post-Consultation Follow-up',
                'day_offset'  => 1,
                'channel'     => 'call',
                'priority'    => 'high',
                'note'        => 'Follow up after consultation — gauge interest and next step.',
                'appears_in'  => ['communication_manager', 'daily_huddle'],
            ],
        ],

        // The big one for dental revenue: don't let "Plan Given" go cold.
        'plan_given' => [
            [
                'label'       => 'Treatment Plan Follow-up',
                'day_offset'  => 2,
                'channel'     => 'call',
                'priority'    => 'high',
                'note'        => 'Follow up on the treatment plan — help the patient decide.',
                'appears_in'  => ['communication_manager', 'daily_huddle'],
            ],
            [
                'label'       => 'Treatment Plan Final Nudge',
                'day_offset'  => 7,
                'channel'     => 'whatsapp',
                'priority'    => 'medium',
                'note'        => 'Final nudge on the treatment plan before it goes cold.',
                'appears_in'  => ['communication_manager'],
            ],
        ],

        'consultation_booked' => [
            [
                'label'       => 'Consultation Reminder',
                'day_offset'  => -1, // 1 day before appointment
                'channel'     => 'call',
                'priority'    => 'high',
                'note'        => 'Remind patient about tomorrow\'s consultation.',
                'appears_in'  => ['communication_manager', 'daily_huddle'],
            ],
        ],

        'estimate_given' => [
            [
                'label'       => 'Estimate Follow-up',
                'day_offset'  => 3,
                'channel'     => 'call',
                'priority'    => 'high',
                'note'        => 'Follow up on treatment estimate — check decision.',
                'appears_in'  => ['communication_manager', 'daily_huddle'],
            ],
        ],

        'visited_clinic' => [
            [
                'label'       => 'Post-Visit Follow-up',
                'day_offset'  => 1,
                'channel'     => 'call',
                'priority'    => 'high',
                'note'        => 'Follow up after clinic visit — check interest and next step.',
                'appears_in'  => ['communication_manager', 'daily_huddle'],
            ],
        ],

        'no_response' => [
            [
                'label'       => 'No Response Re-attempt',
                'day_offset'  => 3,
                'channel'     => 'call',
                'priority'    => 'medium',
                'note'        => 'Re-attempt contact — lead not responding.',
                'appears_in'  => ['communication_manager'],
            ],
            [
                'label'       => 'No Response Final Attempt',
                'day_offset'  => 7,
                'channel'     => 'whatsapp',
                'priority'    => 'low',
                'note'        => 'Final WhatsApp attempt before marking lead cold.',
                'appears_in'  => ['communication_manager'],
            ],
        ],

        'price_concern' => [
            [
                'label'       => 'Price Concern Follow-up',
                'day_offset'  => 5,
                'channel'     => 'call',
                'priority'    => 'medium',
                'note'        => 'Discuss payment options and address price concern.',
                'appears_in'  => ['communication_manager'],
            ],
        ],

        'treatment_fear' => [
            [
                'label'       => 'Treatment Fear Counselling Follow-up',
                'day_offset'  => 3,
                'channel'     => 'call',
                'priority'    => 'medium',
                'note'        => 'Gentle follow-up — address treatment anxiety.',
                'appears_in'  => ['communication_manager'],
            ],
        ],

        'second_opinion' => [
            [
                'label'       => 'Second Opinion Follow-up',
                'day_offset'  => 7,
                'channel'     => 'call',
                'priority'    => 'low',
                'note'        => 'Check if patient has made a decision after second opinion.',
                'appears_in'  => ['communication_manager'],
            ],
        ],

        'delayed' => [
            [
                'label'       => 'Delayed Lead Check-in',
                'day_offset'  => 30,
                'channel'     => 'call',
                'priority'    => 'low',
                'note'        => 'Monthly check-in for delayed lead.',
                'appears_in'  => ['communication_manager'],
            ],
        ],

    ],


    // =========================================================
    // SECTION 3: APPOINTMENT EVENT RULES
    // Triggered by appointment status changes
    // =========================================================

    'appointment_event' => [

        'missed' => [
            [
                'label'       => 'Missed Appointment Recovery Call',
                'day_offset'  => 0, // same day
                'channel'     => 'call',
                'priority'    => 'high',
                'note'        => 'Patient missed appointment — call immediately.',
                'appears_in'  => ['communication_manager', 'daily_huddle'],
            ],
            [
                'label'       => 'Missed Appointment Day 3 Follow-up',
                'day_offset'  => 3,
                'channel'     => 'call',
                'priority'    => 'medium',
                'note'        => 'Second attempt if no response after missed appointment.',
                'appears_in'  => ['communication_manager'],
            ],
        ],

        'completed' => [
            // Post-op follow-ups for appointment completion are
            // handled by treatment_status_changed rules above.
            // Add appointment-specific rules here if needed.
        ],

        'cancelled' => [
            [
                'label'       => 'Cancelled Appointment Re-booking',
                'day_offset'  => 1,
                'channel'     => 'call',
                'priority'    => 'high',
                'note'        => 'Patient cancelled — try to rebook.',
                'appears_in'  => ['communication_manager', 'daily_huddle'],
            ],
        ],

        'no_show' => [
            [
                'label'       => 'No-Show Same Day Call',
                'day_offset'  => 0,
                'channel'     => 'call',
                'priority'    => 'high',
                'note'        => 'Patient did not show up — call to check and rebook.',
                'appears_in'  => ['communication_manager', 'daily_huddle'],
            ],
        ],

    ],


    // =========================================================
    // SECTION 4: SPECIAL OCCASION RULES
    // Birthday, anniversary, festival greetings etc.
    // These are generated by a separate scheduler command.
    // =========================================================

    'special_occasion' => [

        'birthday' => [
            [
                'label'       => 'Birthday Greeting',
                'day_offset'  => 0,
                'channel'     => 'whatsapp',
                'priority'    => 'low',
                'note'        => 'Send birthday wishes to patient.',
                'appears_in'  => ['communication_manager', 'daily_huddle'],
            ],
        ],

        'anniversary' => [
            [
                'label'       => 'Anniversary Greeting',
                'day_offset'  => 0,
                'channel'     => 'whatsapp',
                'priority'    => 'low',
                'note'        => 'Send anniversary wishes to patient.',
                'appears_in'  => ['communication_manager'],
            ],
        ],

    ],

];
