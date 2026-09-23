<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Communication OS — Module Configuration
    | Dentfluence · Tulip Dental
    |--------------------------------------------------------------------------
    | All module-level settings are centralized here.
    | Nothing is hardcoded in controllers, views, or Blade files.
    */

    'enabled' => env('COMMUNICATION_MODULE_ENABLED', true),

    'version' => '1.0.0',

    /*
    |--------------------------------------------------------------------------
    | Communication Sources
    |--------------------------------------------------------------------------
    */
    'sources' => [
        'call'        => ['label' => 'Call',          'icon' => 'phone',          'color' => 'teal'],
        'whatsapp'    => ['label' => 'WhatsApp',       'icon' => 'brand-whatsapp', 'color' => 'green'],
        'sms'         => ['label' => 'SMS',            'icon' => 'message',        'color' => 'blue'],
        'email'       => ['label' => 'Email',          'icon' => 'mail',           'color' => 'blue'],
        'instagram'   => ['label' => 'Instagram',      'icon' => 'brand-instagram','color' => 'pink'],
        'facebook'    => ['label' => 'Facebook',       'icon' => 'brand-facebook', 'color' => 'blue'],
        'walk_in'     => ['label' => 'Walk-in',        'icon' => 'walk',           'color' => 'amber'],
        'website'     => ['label' => 'Website Lead',   'icon' => 'world',          'color' => 'purple'],
        'manual_note' => ['label' => 'Manual Note',    'icon' => 'notes',          'color' => 'gray'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Call Classification Types
    |--------------------------------------------------------------------------
    */
    'classifications' => [
        'existing_patient'  => ['label' => 'Existing Patient',  'workflow' => 'treatment_followup'],
        'new_patient'       => ['label' => 'New Patient',       'workflow' => 'prm_pipeline'],
        'ongoing_case'      => ['label' => 'Ongoing Case',      'workflow' => 'treatment_continuity'],
        'doctor'            => ['label' => 'Doctor',            'workflow' => 'referral_queue'],
        'vendor'            => ['label' => 'Vendor',            'workflow' => 'vendor_queue'],
        'lab'               => ['label' => 'Lab',               'workflow' => 'lab_queue'],
        'spam'              => ['label' => 'Spam',              'workflow' => 'ignore'],
        'other_important'   => ['label' => 'Other Important',   'workflow' => 'manual_review'],
        'other'             => ['label' => 'Other',             'workflow' => null],
    ],

    /*
    |--------------------------------------------------------------------------
    | PRM Pipeline Stages
    |--------------------------------------------------------------------------
    */
    'pipeline_stages' => [
        'new_lead'            => ['label' => 'New Lead',            'order' => 1,  'color' => 'blue',   'active' => true],
        'contacted'           => ['label' => 'Contacted',           'order' => 2,  'color' => 'teal',   'active' => true],
        'consultation_booked' => ['label' => 'Consultation Booked', 'order' => 3,  'color' => 'teal',   'active' => true],
        'visited_clinic'      => ['label' => 'Visited Clinic',      'order' => 4,  'color' => 'amber',  'active' => true],
        'estimate_given'      => ['label' => 'Estimate Given',      'order' => 5,  'color' => 'amber',  'active' => true],
        'treatment_started'   => ['label' => 'Treatment Started',   'order' => 6,  'color' => 'green',  'active' => true],
        'treatment_completed' => ['label' => 'Treatment Completed', 'order' => 7,  'color' => 'green',  'active' => true],
        'lost'                => ['label' => 'Lost',                'order' => 8,  'color' => 'red',    'active' => false],
        'delayed'             => ['label' => 'Delayed',             'order' => 9,  'color' => 'gray',   'active' => false],
        'no_response'         => ['label' => 'No Response',         'order' => 10, 'color' => 'gray',   'active' => false],
        'second_opinion'      => ['label' => 'Second Opinion',      'order' => 11, 'color' => 'purple', 'active' => false],
        'price_concern'       => ['label' => 'Price Concern',       'order' => 12, 'color' => 'coral',  'active' => false],
        'treatment_fear'      => ['label' => 'Treatment Fear',      'order' => 13, 'color' => 'coral',  'active' => false],
    ],

    /*
    |--------------------------------------------------------------------------
    | Lead Sources
    |--------------------------------------------------------------------------
    */
    'lead_sources' => [
        'whatsapp'          => 'WhatsApp',
        'instagram'         => 'Instagram',
        'facebook'          => 'Facebook',
        'google'            => 'Google',
        'website'           => 'Website',
        'walk_in'           => 'Walk-in',
        'camp'              => 'Camp',
        'referral'          => 'Referral',
        'existing_inquiry'  => 'Existing Patient Inquiry',
        'manual'            => 'Manual Entry',
    ],

    /*
    |--------------------------------------------------------------------------
    | Follow-up Types
    |--------------------------------------------------------------------------
    */
    'followup_types' => [
        'post_op'               => 'Post-Op Review',
        'pending_treatment'     => 'Pending Treatment',
        'recall'                => 'Recall',
        'inactive_recovery'     => 'Inactive Patient Recovery',
        'consultation'          => 'Consultation Follow-up',
        'estimate'              => 'Estimate Follow-up',
        'implant_review'        => 'Implant Review',
        'ortho_review'          => 'Ortho Review',
        'annual_checkup'        => 'Annual Checkup',
        'birthday'              => 'Birthday Greeting',
        'festival'              => 'Festival Greeting',
    ],

    /*
    |--------------------------------------------------------------------------
    | Task Statuses
    |--------------------------------------------------------------------------
    */
    'task_statuses' => [
        'pending'     => ['label' => 'Pending',     'color' => 'amber'],
        'in_progress' => ['label' => 'In Progress', 'color' => 'blue'],
        'completed'   => ['label' => 'Completed',   'color' => 'green'],
        'overdue'     => ['label' => 'Overdue',     'color' => 'red'],
        'escalated'   => ['label' => 'Escalated',   'color' => 'coral'],
        'cancelled'   => ['label' => 'Cancelled',   'color' => 'gray'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Quick Actions available per communication item
    |--------------------------------------------------------------------------
    */
    'quick_actions' => [
        'call'               => ['label' => 'Call',               'icon' => 'phone'],
        'whatsapp'           => ['label' => 'WhatsApp',           'icon' => 'brand-whatsapp'],
        'add_note'           => ['label' => 'Add Note',           'icon' => 'notes'],
        'schedule_followup'  => ['label' => 'Schedule Follow-up', 'icon' => 'calendar-plus'],
        'assign_staff'       => ['label' => 'Assign Staff',       'icon' => 'user-plus'],
        'move_pipeline'      => ['label' => 'Move Pipeline',      'icon' => 'arrows-right'],
        'mark_completed'     => ['label' => 'Mark Completed',     'icon' => 'circle-check'],
        'escalate'           => ['label' => 'Escalate',           'icon' => 'alert-triangle'],
        'convert_opportunity'=> ['label' => 'Convert to Opportunity', 'icon' => 'sparkles'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation Items (drives sidebar)
    |--------------------------------------------------------------------------
    */
    'navigation' => [
        [
            'key'   => 'manager',
            'label' => 'Communication Manager',
            'icon'  => 'messages',
            'route' => 'communication.manager.index',
            'badge' => 'overdue_count',
        ],
        [
            'key'   => 'prm',
            'label' => 'Pipeline',
            'icon'  => 'layout-kanban',
            'route' => 'relationship.pipeline', // prm.index retired in Phase 8
            'badge' => null,
        ],
        [
            'key'   => 'followup',
            'label' => 'Follow-up Engine',
            'icon'  => 'clock-bolt',
            'route' => 'communication.followup.index',
            'badge' => 'followup_overdue_count',
        ],
        [
            'key'   => 'opportunities',
            'label' => 'Opportunity Engine',
            'icon'  => 'sparkles',
            'route' => 'relationship.opportunities', // retired 2026-07-06, now the PRE Opportunity Pipeline
            'badge' => null,
        ],
        [
            'key'   => 'tasks',
            'label' => 'Tasks & Assignments',
            'icon'  => 'checklist',
            'route' => 'communication.tasks.index',
            'badge' => 'pending_tasks_count',
        ],
        // 'timeline' tile removed 2026-07-14 (production hardening) — the
        // TimelineController still renders hardcoded SAMPLE patients
        // (getDummyPatients/getDummyTimeline). Restore this entry only after
        // the controller is wired to live data (see the TODO in
        // App\Http\Controllers\Communication\TimelineController).
        // [
        //     'key'   => 'timeline',
        //     'label' => 'Communication Timeline',
        //     'icon'  => 'timeline',
        //     'route' => 'communication.timeline.index',
        //     'badge' => null,
        // ],
        // 'templates' tile removed 2026-07-06 — Message Templates moved to the
        // Relationship/PRE module (relationship.templates.*), reached via gear
        // icons on Recall/Birthday/Anniversary settings, not a Communication OS
        // dashboard tile anymore.
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp — current phase: click-to-chat (open web only)
    |--------------------------------------------------------------------------
    | Until the WhatsApp Cloud API (Meta) is approved, all transactional sends
    | go through click-to-chat deep links (wa.me). Staff tap the WhatsApp icon,
    | their own WhatsApp opens with the message pre-filled, they hit send.
    | No template pre-approval, no business verification required.
    |
    | One service (App\Services\Communication\WhatsAppLinkService) is the single
    | source of truth for phone normalization, consent gating and message copy.
    | When 'mode' flips to 'api', callers keep working — only the service changes.
    */
    'whatsapp' => [
        'mode'         => 'web_open',   // future: 'api'
        'web_url'      => 'https://wa.me/',
        'country_code' => env('WHATSAPP_DEFAULT_CC', '91'), // India; prepended to 10-digit numbers
        'api_ready'    => false,
        'review_url'   => env('REVIEWS_GOOGLE_URL'), // default {review_url} for review_request sends

        // The number patients are told to ring back on. Printed in every
        // appointment template, so it lives in ONE place — a clinic changing
        // its number must not have to hunt through five message strings.
        'contact_phone' => env('CLINIC_CONTACT_PHONE', ''),

        /*
        | Which template a Today's Actions row uses, by category. The board's
        | categories are the clinic's language; the template keys are the
        | message's. Keeping the map here rather than in the phone means a
        | clinic can re-point a queue at different copy without a new APK.
        | A category with no entry falls through to 'generic' — free text,
        | never a wrong template sent confidently.
        */
        'category_templates' => [
            'appointment_reminders'  => 'appointment_reminder',
            'appointment_reminder'   => 'appointment_reminder',
            'follow_up_calls'        => 'follow_up',
            'follow_ups'             => 'follow_up',
            'missed_calls'           => 'missed_call',
            'missed_appointments'    => 'missed_appointment',
            'opportunities'          => 'opportunity',
            'opportunity'            => 'opportunity',
            'recalls'                => 'recall',
            'recall'                 => 'recall',
            'birthdays'              => 'birthday',
            'review_requests'        => 'review_request',
        ],

        /*
        | Message templates for click-to-chat sends. Placeholders in {braces}
        | are filled at send time. {clinic} defaults to config('app.clinic_name').
        | Keep copy short — this is the text the patient receives on WhatsApp.
        | 'generic' has no template: the caller-supplied message is used as-is.
        | Structure is locale-ready: a value may be a plain string (current) or
        | later an array keyed by locale without changing any caller.
        */
        'templates' => [
            'appointment_reminder' =>
                "🦷 *{clinic}*\n".
                "Hi {patient}, a reminder for your dental appointment on *{date}* at *{time}*{doctor}.\n".
                "Please reply here to confirm or reschedule.",

            // Sent at the moment of booking. Carries the treatment and the
            // clinic's own number, because the two questions a patient asks
            // after booking are "what was it for?" and "who do I ring if
            // something changes?". {treatment} and {contact} render empty
            // when unknown, so the message never shows a dangling label.
            'appointment_confirmation' =>
                "🦷 *{clinic}*\n".
                "Hi {patient}, your appointment is confirmed for *{date}* at *{time}*{doctor}{treatment}.\n".
                "See you then!{contact}",

            'follow_up' =>
                "🦷 *{clinic}*\n".
                "Hi {patient}, just following up on your last visit with us. ".
                "How are you getting on?\n".
                "Reply here if anything needs looking at.{contact}",

            // The patient did not pick up. Deliberately does NOT say "we
            // tried to reach you and failed" — it gives them something to
            // reply to instead.
            'missed_call' =>
                "🦷 *{clinic}*\n".
                "Hi {patient}, we tried to reach you just now. ".
                "Reply here whenever suits you and we'll pick it up.{contact}",

            'missed_appointment' =>
                "🦷 *{clinic}*\n".
                "Hi {patient}, we missed you at your appointment on *{date}*. ".
                "Reply here and we'll find you another slot.{contact}",

            // Treatment discussed but not yet booked. No pressure and no
            // price — this opens a conversation, it does not close a sale.
            'opportunity' =>
                "🦷 *{clinic}*\n".
                "Hi {patient}, about the {treatment_plain} we discussed — ".
                "happy to answer any questions before you decide.\n".
                "Reply here and we'll take it from there.{contact}",

            'review_request' =>
                "🦷 *{clinic}*\n".
                "Hi {patient}, thank you for visiting us! If you had a good experience, ".
                "we'd really appreciate a quick review:\n{review_url}",

            'recall' =>
                "🦷 *{clinic}*\n".
                "Hi {patient}, it's time for your dental check-up. ".
                "Reply here and we'll help you book a convenient slot.",

            'birthday' =>
                "🎂 Happy Birthday, {patient}!\n".
                "Wishing you a healthy, bright smile all year. — *{clinic}*",

            // ── Appointment lifecycle ───────────────────────────────────
            // Rescheduled carries BOTH dates. "Your appointment has moved"
            // with only the new time makes a patient who wrote the old one
            // down wonder whether they misread it.
            'appointment_rescheduled' =>
                "🦷 *{clinic}*\n".
                "Hi {patient}, your appointment on *{old_date}* has been moved to ".
                "*{date}* at *{time}*{doctor}.\n".
                "Reply here if that does not suit.{contact}",

            // No reason is given. The clinic may have cancelled for a dozen
            // reasons and a template cannot know which; a wrong reason is
            // worse than none.
            'appointment_cancelled' =>
                "🦷 *{clinic}*\n".
                "Hi {patient}, your appointment on *{date}* at *{time}* has been cancelled.\n".
                "Reply here and we'll rebook you whenever suits.{contact}",

            // ── Prescription ────────────────────────────────────────────
            // Deliberately carries NO drug names. A prescription is health
            // data and this message travels over a channel the clinic does
            // not control — it says a prescription is ready, nothing more.
            'prescription_ready' =>
                "🦷 *{clinic}*\n".
                "Hi {patient}, your prescription from today's visit is ready.\n".
                "Collect it at the clinic or reply here and we'll send it across.{contact}",

            // ── Business recipients ─────────────────────────────────────
            // A lab and a dealer are businesses, not patients: no greeting
            // by first name, no "hope you are well", and the case or order
            // number leads because that is what they will search for.
            'lab_instructions' =>
                "*{clinic}* — Lab case *{case_number}*\n".
                "Patient: {patient}\n".
                "Work: {work}\n".
                "Due: {due_date}\n".
                "{instructions}\n".
                "Please confirm receipt.{contact}",

            'purchase_order' =>
                "*{clinic}* — Purchase Order *{po_number}*\n".
                "{items}\n".
                "Please confirm availability and expected delivery.{contact}",

            'generic' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Overdue thresholds (hours)
    |--------------------------------------------------------------------------
    */
    'overdue_thresholds' => [
        'callback'      => 4,
        'followup'      => 24,
        'task'          => 48,
        'recall'        => 72,
        'lead_response' => 6,
    ],

];
