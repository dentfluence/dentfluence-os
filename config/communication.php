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
    | WhatsApp — current phase: open web only
    |--------------------------------------------------------------------------
    */
    'whatsapp' => [
        'mode'       => 'web_open',  // future: 'api'
        'web_url'    => 'https://wa.me/',
        'api_ready'  => false,
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
