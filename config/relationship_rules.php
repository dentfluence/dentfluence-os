<?php

/**
 * config/relationship_rules.php
 *
 * Configuration for the Dentfluence Relationship Engine.
 *
 * Phase 2: TodayActionsEngine thresholds + dynamic call checklists.
 * Phase 5: RulesEngine automation timings will also live here.
 *
 * All values are overridable via .env.
 */

return [

    /* ─────────────────────────────────────────────────────────────────────
     | TODAY'S ACTIONS — thresholds and windows
     ───────────────────────────────────────────────────────────────────── */
    'today_actions' => [

        // How many days before membership expires to flag a renewal call
        'membership_renewal_days_ahead'    => (int) env('REL_RENEWAL_DAYS', 30),

        // Minimum ₹ balance_due on an overdue invoice to trigger a payment reminder call
        'payment_reminder_threshold'       => (int) env('REL_PAYMENT_THRESHOLD', 500),

        // Days past an invoice's due_date at which `payment.overdue` fires
        // (producer: php artisan payments:scan-overdue). Matches the
        // payment_overdue_3d rule's name/intent.
        'payment_overdue_days'             => (int) env('REL_PAYMENT_OVERDUE_DAYS', 3),

        // Days of lead time before a birthday at which `birthday.approaching`
        // fires (producer: php artisan birthdays:scan). Matches birthday_3d.
        'birthday_days_ahead'              => (int) env('REL_BIRTHDAY_DAYS_AHEAD', 3),

        // Hours before appointment to include in reminder list (default: 24 = tomorrow's appts)
        'appointment_reminder_hours_ahead' => (int) env('REL_APPT_REMINDER_HOURS', 24),

        // Window (days) for "birthday today ± N days" to catch yesterday/tomorrow too
        'birthday_window_days'             => (int) env('REL_BIRTHDAY_WINDOW', 1),

        // Lab statuses considered "ready for patient to collect"
        'lab_ready_statuses'               => ['final_received', 'complete'],

        // Max items per category to return (prevents overwhelming UI)
        'max_per_category'                 => (int) env('REL_MAX_PER_CAT', 50),
    ],

    /* ─────────────────────────────────────────────────────────────────────
     | DYNAMIC CALL CHECKLISTS
     | Used in the Call Workflow drawer for each category.
     | Each entry is a list of checkbox items staff should confirm.
     ───────────────────────────────────────────────────────────────────── */
    'call_checklists' => [

        'appointment_reminder' => [
            'Confirm patient is coming',
            'Confirm appointment time',
            'Any prep instructions given?',
        ],

        'recall_calls' => [
            'Greet patient',
            'Mention last visit date',
            'Suggest appointment',
            'Confirm preferred time',
        ],

        'lead_followups' => [
            'Remind them of their enquiry',
            'Ask if they still need the treatment',
            'Address any concerns',
            'Offer to book a consultation',
        ],

        'opportunities' => [
            'Reference previous discussion',
            'Ask about current status',
            'Offer to book consultation',
        ],

        'pending_estimates' => [
            'Mention the treatment plan / estimate shared',
            'Ask if they have any questions about the cost',
            'Check if they are ready to proceed',
            'Offer flexible payment options if relevant',
        ],

        'birthday' => [
            'Wish patient',
            'Ask how they are doing',
            'Mention upcoming recall if due',
        ],

        'membership_renewals' => [
            'Mention expiry date',
            'Explain renewal benefits',
            'Confirm payment method',
        ],

        'missed_appointments_yesterday' => [
            'Acknowledge they missed the appointment',
            'Ask if everything is okay',
            'Offer to reschedule',
        ],

        'missed_calls_yesterday' => [
            'Apologise for the missed contact',
            'Explain reason for the call',
            'Check if this is still a good time to talk',
        ],

        'payment_reminders' => [
            'Mention outstanding balance amount',
            'Ask if they have any billing questions',
            'Confirm expected payment date',
        ],

        'lab_ready' => [
            'Inform patient their work is ready',
            'Confirm preferred pickup/fitting appointment time',
            'Mention any prep or care instructions',
        ],

        'new_enquiries' => [
            'Introduce the clinic warmly',
            'Understand what treatment they are looking for',
            'Offer to book a consultation',
        ],
    ],

    /* ─────────────────────────────────────────────────────────────────────
     | LOG RESPONSE OPTIONS
     | Dropdown options shown in the Call Workflow drawer after checklist.
     | Keyed by category (falls back to 'default').
     ───────────────────────────────────────────────────────────────────── */
    'response_options' => [

        'default' => [
            'connected_booked'        => 'Connected — Appointment booked',
            'connected_callback'      => 'Connected — Will call back later',
            'connected_not_interested'=> 'Connected — Not interested',
            'no_answer'               => 'No answer',
            'busy'                    => 'Line busy',
            'wrong_number'            => 'Wrong number',
            'voicemail'               => 'Left voicemail',
        ],

        'birthday' => [
            'wished_happy'            => 'Wished — patient was happy',
            'wished_booked'           => 'Wished — also booked appointment',
            'no_answer'               => 'No answer',
            'not_reachable'           => 'Not reachable',
        ],

        'payment_reminders' => [
            'payment_promised'        => 'Payment promised (date noted)',
            'payment_made'            => 'Payment made on call',
            'dispute_raised'          => 'Patient raised a dispute',
            'no_answer'               => 'No answer',
        ],
    ],

    /* ─────────────────────────────────────────────────────────────────────
     | NEXT ACTION SUGGESTIONS
     | Auto-suggested "next action" based on log response.
     ───────────────────────────────────────────────────────────────────── */
    'next_actions' => [
        'connected_booked'         => 'Send appointment confirmation',
        'connected_callback'       => 'Schedule callback for tomorrow',
        'connected_not_interested' => 'Mark as lost — update journey',
        'no_answer'                => 'Retry tomorrow',
        'busy'                     => 'Retry in 2 hours',
        'wrong_number'             => 'Update contact number',
        'voicemail'                => 'Follow up if no callback in 24 hours',
        'wished_happy'             => 'Log and close — no action needed',
        'wished_booked'            => 'Send appointment confirmation',
        'payment_promised'         => 'Follow up on promised date',
        'payment_made'             => 'Mark invoice as settled',
        'dispute_raised'           => 'Escalate to front desk manager',
    ],


    /* =====================================================================
     | PHASE 5 — AUTOMATION RULES (RulesEngine)
     | =====================================================================
     |
     | Each entry in 'rules' is a named automation rule.
     | Rule shape:
     |   'rule_name' => [
     |       'trigger'       => string   // event key from ActivityEngine ('domain.action')
     |       'conditions'    => array    // key=>value pairs matched against activity context
     |       'action'        => string   // 'create_task' | 'create_reminder'
     |       'action_config' => array    // passed to TaskEngine or ReminderEngine
     |       'cooldown_days' => int      // skip if rule fired for same relationship within N days
     |       'enabled'       => bool
     |   ]
     |
     | action_config keys:
     |   For create_task:    category, title, priority, days_after
     |   For create_reminder: type, days_after
     |
     | Flip 'enabled' => false to pause any rule without deleting it.
     ===================================================================== */

    'rules' => [

        /*
         * 1. Implant follow-up call — 7 days after implant treatment completed.
         *    Post-op monitoring; reception calls to check on healing.
         */
        'implant_followup' => [
            'trigger'       => 'treatment.completed',
            'conditions'    => ['treatment_type' => 'implant'],
            'action'        => 'create_task',
            'action_config' => [
                'category'   => 'call',
                'title'      => 'Implant follow-up call',
                'priority'   => 'high',
                'days_after' => 7,
            ],
            'cooldown_days' => 90,
            'enabled'       => true,
        ],

        /*
         * 2. General post-treatment follow-up — 3 days after any treatment completed.
         *    Checks patient satisfaction and flags complications early.
         */
        'post_treatment_followup' => [
            'trigger'       => 'treatment.completed',
            'conditions'    => [],   // fires for all treatment types
            'action'        => 'create_task',
            'action_config' => [
                'category'   => 'call',
                'title'      => 'Post-treatment follow-up call',
                'priority'   => 'medium',
                'days_after' => 3,
            ],
            'cooldown_days' => 30,
            'enabled'       => true,
        ],

        /*
         * 3. 6-month recall reminder — 180 days after a completed visit.
         *
         * DISABLED 2026-07-14 (production hardening) — deliberately starved,
         * and the config was lying about it. TreatmentVisitService explicitly
         * does NOT log 'visit.completed' (see the comment at
         * TreatmentVisitService.php:384) because it already creates the 6-month
         * recall task inline; letting this rule fire too would double-queue
         * every recall. The 6-month recall IS still produced — by the inline
         * path and by RecallEngineService's no_visit_6months trigger.
         *
         * Left in place (not deleted) so the intent is documented. Enable ONLY
         * if the inline creation in TreatmentVisitService is removed first.
         */
        'recall_6months' => [
            'trigger'       => 'visit.completed',
            'conditions'    => [],
            'action'        => 'create_reminder',
            'action_config' => [
                'type'       => 'recall_6month',
                'days_after' => 180,
            ],
            'cooldown_days' => 150,   // don't re-queue if one already pending
            'enabled'       => false,
        ],

        /*
         * REMOVED 2026-07-09 (docs/backend-orchestration-plan.md §2.2 / §3.8):
         * was 'appointment_reminder' — 1 day before a booked appointment. Left
         * disabled since 2026-07-06 (metadata key bug + double-contact risk
         * against the existing bulk AppointmentReminderEngine job, which
         * already covers this ground daily). Removed rather than fixed:
         * enabling it would create a second reminder task alongside the bulk
         * job's, violating the "no duplicate tasks" rule for this phase. If
         * Sumit wants a rule-engine-driven reminder later, it should REPLACE
         * the bulk job, not run alongside it.
         */

        /*
         * 5. Membership renewal — when membership.expiring fires (engine fires at 30d mark).
         *    Revenue protection. Reception calls before it lapses.
         */
        'membership_renewal_30d' => [
            'trigger'       => 'membership.expiring',
            'conditions'    => [],
            'action'        => 'create_task',
            'action_config' => [
                'category'   => 'call',
                'title'      => 'Membership renewal call',
                'priority'   => 'high',
                'days_after' => 0,
            ],
            'cooldown_days' => 25,
            'enabled'       => true,
        ],

        /*
         * 6. Birthday greeting — 3 days before birthday (birthday.approaching fires at 3d).
         *    Relationship builder. WhatsApp preferred.
         */
        'birthday_3d' => [
            'trigger'       => 'birthday.approaching',
            'conditions'    => [],
            'action'        => 'create_task',
            'action_config' => [
                'category'   => 'whatsapp',
                'title'      => 'Birthday greeting',
                'priority'   => 'low',
                'days_after' => 0,
            ],
            'cooldown_days' => 360,   // once per year
            'enabled'       => true,
        ],

        /*
         * 7. Opportunity nudge — 7 days after opportunity created in 'prospect' stage.
         *    Prospect has gone cold; reception follows up to convert.
         */
        'opportunity_nudge_7d' => [
            'trigger'       => 'opportunity.created',
            'conditions'    => ['stage' => 'prospect'],
            'action'        => 'create_task',
            'action_config' => [
                'category'   => 'call',
                'title'      => 'Opportunity follow-up — no appointment yet',
                'priority'   => 'medium',
                'days_after' => 7,
            ],
            'cooldown_days' => 14,
            'enabled'       => true,
        ],

        /*
         * 8. Estimate follow-up — 3 days after a treatment estimate is sent.
         *    Patient received a quote; check for questions before they go cold.
         *
         *    2026-07-14: trigger corrected from 'estimate.sent' (which NOTHING
         *    in the app ever emitted, so this rule was dead) to the event that
         *    is actually fired when an estimate goes out —
         *    PresentationController::send()/resend() log 'presentation.sent'.
         */
        'estimate_followup_3d' => [
            'trigger'       => 'presentation.sent',
            'conditions'    => [],
            'action'        => 'create_task',
            'action_config' => [
                'category'   => 'call',
                'title'      => 'Estimate follow-up call',
                'priority'   => 'medium',
                'days_after' => 3,
            ],
            'cooldown_days' => 7,
            'enabled'       => true,
        ],

        /*
         * 9. Missed appointment follow-up — same day the appointment is missed.
         *    Re-schedule before the patient drifts away entirely.
         */
        'missed_appointment_followup' => [
            'trigger'       => 'appointment.missed',
            'conditions'    => [],
            'action'        => 'create_task',
            'action_config' => [
                'category'   => 'call',
                'title'      => 'Missed appointment — reschedule call',
                'priority'   => 'urgent',
                'days_after' => 0,
            ],
            'cooldown_days' => 3,
            'enabled'       => true,
        ],

        /*
         * 10. Lab ready — notify patient when lab case received with no appointment.
         *     Prevents finished work sitting idle in the clinic.
         */
        'lab_ready_call' => [
            'trigger'       => 'lab.received',
            'conditions'    => ['appointment_booked' => false],
            'action'        => 'create_task',
            'action_config' => [
                'category'   => 'call',
                'title'      => 'Lab ready — book delivery appointment',
                'priority'   => 'high',
                'days_after' => 0,
            ],
            'cooldown_days' => 7,
            'enabled'       => true,
        ],

        /*
         * 11. Payment overdue — 3 days past invoice due date with balance outstanding.
         */
        'payment_overdue_3d' => [
            'trigger'       => 'payment.overdue',
            'conditions'    => [],
            'action'        => 'create_task',
            'action_config' => [
                'category'   => 'call',
                'title'      => 'Overdue payment follow-up',
                'priority'   => 'high',
                'days_after' => 0,
            ],
            'cooldown_days' => 7,
            'enabled'       => true,
        ],

        /*
         * 12. Smart Presentation callback request — patient asked to be
         *     called back instead of accepting/declining outright. This is a
         *     warm, active request (not a cold nudge), so same-day + high
         *     priority. Without this rule the callback request would just
         *     sit in the Opportunity pipeline unseen — same gap 'decline'
         *     had before it was wired to create/update the Opportunity.
         */
        'presentation_callback_requested' => [
            'trigger'       => 'opportunity.callback_requested',
            'conditions'    => [],
            'action'        => 'create_task',
            'action_config' => [
                'category'   => 'call',
                'title'      => 'Patient requested a callback — Smart Presentation',
                'priority'   => 'high',
                'days_after' => 0,
            ],
            'cooldown_days' => 1,
            'enabled'       => true,
        ],

        /*
         * 13. Case Acceptance — patient opened the journey but hasn't decided.
         *     A warm nudge two days after they viewed their options, so a live
         *     estimate doesn't go cold. Fires off 'case.opened' (logged by
         *     PublicCaseController on first view). Config-only — no engine code.
         */
        'case_opened_followup_2d' => [
            'trigger'       => 'case.opened',
            'conditions'    => [],
            'action'        => 'create_task',
            'action_config' => [
                'category'   => 'call',
                'title'      => 'Case journey viewed — follow-up call',
                'priority'   => 'medium',
                'days_after' => 2,
            ],
            'cooldown_days' => 5,
            'enabled'       => true,
        ],

        /*
         * 14. Case Acceptance — patient asked for more time / a callback. An
         *     active, warm request, so same-day + high priority (mirrors the
         *     Smart Presentation callback rule above).
         */
        'case_more_time_requested' => [
            'trigger'       => 'case.more_time_requested',
            'conditions'    => [],
            'action'        => 'create_task',
            'action_config' => [
                'category'   => 'call',
                'title'      => 'Patient requested a callback — Case Journey',
                'priority'   => 'high',
                'days_after' => 0,
            ],
            'cooldown_days' => 1,
            'enabled'       => true,
        ],

    ], // end rules


    /* ─────────────────────────────────────────────────────────────────────
     | COMMUNICATION GUARD THRESHOLDS
     | CommunicationGuard::canContact() reads these before queuing any
     | outbound message. Prevents over-messaging patients.
     ───────────────────────────────────────────────────────────────────── */

    'communication_guard' => [

        // Same channel may not contact the same relationship twice within this window
        'same_channel_cooldown_hours' => (int) env('COMM_GUARD_CHANNEL_HOURS', 24),

        // Max total contacts (any channel) in a rolling window
        'max_contacts_per_days'       => (int) env('COMM_GUARD_WINDOW_DAYS', 7),
        'max_contacts_per_count'      => (int) env('COMM_GUARD_MAX_CONTACTS', 3),

        // If a birthday message was sent today, block all promotional contacts for the day
        'birthday_blocks_promotional' => true,

        // Channels recognised by the guard
        'channels' => ['call', 'whatsapp', 'sms', 'email'],

        // Contact types classified as 'promotional' (birthday-block logic applies)
        'promotional_types' => [
            'marketing',
            'offer',
            'recall_campaign',
            'newsletter',
        ],

        /* ─────────────────────────────────────────────────────────────────
         | Phase 0 — Guard hardening foundation (all DEFAULT-OFF = no change).
         | Enforcement is gated by feature flags (config/features.php):
         |   - guard.consent_required   → enforce consent
         |   - guard.fail_closed        → block (not fail open) on guard error
         | These config keys only tune behaviour once the flags are flipped in
         | a later phase. In Phase 0 they change nothing.
         ───────────────────────────────────────────────────────────────── */

        // Quiet hours (foundation only; disabled by default). When enabled and
        // NOT urgent, contact is blocked during this window.
        'quiet_hours' => [
            'enabled' => (bool) env('COMM_GUARD_QUIET_HOURS', false),
            'start'   => env('COMM_GUARD_QUIET_START', '21:00'),
            'end'     => env('COMM_GUARD_QUIET_END', '08:00'),
        ],

        // Urgency policy — the INVARIANT that protects patients AND compliance:
        // urgency may relax frequency and quiet-hours, but NEVER consent.
        'urgency' => [
            'relaxes'      => ['frequency', 'quiet_hours'],
            'never_relaxes'=> ['consent'], // hard rule — do not add anything here
        ],
    ],


    /* ─────────────────────────────────────────────────────────────────────
     | FAILSAFE THRESHOLDS
     | RelationshipAutomationFailedJob reads these to decide when to escalate.
     ───────────────────────────────────────────────────────────────────── */

    'failsafe' => [
        // Failures for the same rule + relationship within this window → create admin task
        'escalation_count'         => (int) env('FAILSAFE_ESCALATION_COUNT', 3),
        'escalation_window_hours'  => (int) env('FAILSAFE_ESCALATION_HOURS', 24),
    ],

];
