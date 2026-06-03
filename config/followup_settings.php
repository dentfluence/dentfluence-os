<?php

/**
 * ============================================================
 * DENTFLUENCE — FOLLOW-UP ENGINE SETTINGS
 * ============================================================
 * File: config/followup_settings.php
 *
 * All operational settings for the Follow-up Engine.
 * Change values here — no code changes needed anywhere else.
 *
 * These settings are read by:
 *   - FollowUpRulesService
 *   - FollowUpController
 *   - Scheduled commands (ProcessOverdueFollowUps etc.)
 *   - Daily Huddle integration
 * ============================================================
 */

return [

    // =========================================================
    // OVERDUE SETTINGS
    // A follow-up becomes overdue at the START of the next day
    // after its scheduled date. This is evaluated daily by
    // the ProcessOverdueFollowUps scheduled command.
    // =========================================================

    'overdue' => [

        // How many days after due date before marking overdue.
        // 0 = overdue from start of next day (recommended)
        // 1 = overdue after 1 full day has passed
        'grace_days'           => 0,

        // Should overdue items appear prominently in Daily Huddle?
        'show_in_daily_huddle' => true,

        // Should overdue items appear at top of Communication Manager?
        'show_in_comm_manager' => true,

        // Auto-escalate if overdue for more than X days (0 = disabled)
        'auto_escalate_after_days' => 3,

        // Who to escalate to (role name — matched against staff roles)
        'escalate_to_role'     => 'treatment_coordinator',

    ],


    // =========================================================
    // WORKING HOURS
    // Follow-ups are only scheduled within these hours.
    // Auto follow-ups falling outside working hours are pushed
    // to the next working day at default_time.
    // =========================================================

    'working_hours' => [
        'start' => '09:00',
        'end'   => '19:00',
    ],

    'working_days' => [
        // 1 = Monday, 7 = Sunday
        1, 2, 3, 4, 5, 6, // Mon–Sat (Sunday off by default)
    ],

    // Default time for auto-scheduled follow-ups
    'default_followup_time' => '10:00',


    // =========================================================
    // REMINDER SETTINGS
    // In-app reminders before a follow-up is due
    // =========================================================

    'reminders' => [
        // Minutes before follow-up time to send reminder
        'remind_before_minutes' => 15,

        // Send reminder via notification?
        'notify_in_app'         => true,

        // Also show in Daily Huddle morning briefing?
        'show_in_huddle'        => true,
    ],


    // =========================================================
    // DAILY HUDDLE INTEGRATION
    // Controls what appears in the Daily Huddle from Follow-up Engine
    // =========================================================

    'daily_huddle' => [
        // Show overdue follow-ups in huddle
        'show_overdue'          => true,

        // Show today's scheduled follow-ups in huddle
        'show_todays'           => true,

        // Max items to show per section in huddle (0 = no limit)
        'max_overdue_items'     => 10,
        'max_today_items'       => 20,

        // Priority threshold — only show medium and above in huddle
        // Options: high | medium | low
        'min_priority'          => 'medium',
    ],


    // =========================================================
    // COMMUNICATION MANAGER INTEGRATION
    // Controls what appears in the Communication Manager list
    // =========================================================

    'comm_manager' => [
        // Show follow-ups in today's communication list
        'show_todays'           => true,

        // Show overdue in communication list
        'show_overdue'          => true,

        // Default sort order in comm manager
        // Options: priority | time | overdue_first
        'default_sort'          => 'overdue_first',
    ],


    // =========================================================
    // RECALL SETTINGS
    // Rules for the 6-month / annual recall system
    // =========================================================

    'recall' => [
        // Default recall period in days (180 = ~6 months)
        'default_recall_days'  => 180,

        // Channel for recall follow-ups
        'default_channel'      => 'call',

        // Priority for recall follow-ups
        'default_priority'     => 'low',

        // How many days before recall date to show in
        // Communication Manager "upcoming" list
        'show_upcoming_days'   => 7,

        // How many days before recall to send first reminder
        'first_reminder_days'  => 14,

        // How many days before recall to send second reminder
        'second_reminder_days' => 3,
    ],


    // =========================================================
    // ASSIGNMENT SETTINGS
    // Rules for auto-assigning follow-ups to staff
    // =========================================================

    'assignment' => [
        // Who gets auto follow-ups by default?
        // Options: lead_owner | round_robin | specific_role
        'default_assignment'   => 'lead_owner',

        // Role to assign to when lead_owner is not set
        'fallback_role'        => 'front_desk',

        // Re-assign if original assignee has not acted for X hours
        // 0 = disabled
        'reassign_after_hours' => 0,
    ],


    // =========================================================
    // LIMITS & THROTTLING
    // =========================================================

    'limits' => [
        // Max follow-ups per staff per day (0 = no limit)
        'max_per_staff_per_day' => 75,

        // Min gap in hours between two follow-ups for same patient
        // Prevents spam — 0 = no minimum gap
        'min_gap_hours_per_patient' => 4,
    ],


    // =========================================================
    // DUPLICATE PREVENTION
    // Prevent the same follow-up type from being created twice
    // for the same patient within a window
    // =========================================================

    'duplicate_prevention' => [
        // Enable duplicate check
        'enabled'              => true,

        // If same trigger + same patient exists within X days, skip
        'skip_if_exists_days'  => 2,
    ],


    // =========================================================
    // CHANNELS
    // Available follow-up channels across the system
    // =========================================================

    'channels' => [
        'call'         => ['label' => 'Call',              'icon' => 'phone',    'color' => '#4F46E5'],
        'whatsapp'     => ['label' => 'WhatsApp',          'icon' => 'message',  'color' => '#22C55E'],
        'clinic_visit' => ['label' => 'Clinic Visit',      'icon' => 'building', 'color' => '#F97316'],
        'sms'          => ['label' => 'SMS',               'icon' => 'sms',      'color' => '#6B7280'],
        'email'        => ['label' => 'Email',             'icon' => 'mail',     'color' => '#0EA5E9'],
        'any'          => ['label' => 'Any Channel',       'icon' => 'layers',   'color' => '#8B5CF6'],
    ],


    // =========================================================
    // PRIORITIES
    // =========================================================

    'priorities' => [
        'high'   => ['label' => 'High',   'color' => '#EF4444', 'badge_class' => 'priority-high'],
        'medium' => ['label' => 'Medium', 'color' => '#F97316', 'badge_class' => 'priority-medium'],
        'low'    => ['label' => 'Low',    'color' => '#6B7280', 'badge_class' => 'priority-low'],
    ],


    // =========================================================
    // FOLLOW-UP OUTCOME OPTIONS
    // Shown in the Complete Follow-up modal
    // =========================================================

    'outcomes' => [
        'connected'        => 'Connected',
        'not_reachable'    => 'Not Reachable',
        'callback_later'   => 'Callback Requested',
        'interested'       => 'Interested',
        'not_interested'   => 'Not Interested',
        'appointment_done' => 'Appointment Confirmed',
        'converted'        => 'Converted to Patient',
        'already_visited'  => 'Already Visited Clinic',
        'other'            => 'Other',
    ],

    // Next step options after completing a follow-up
    'next_steps' => [
        'schedule_followup'   => 'Schedule Next Follow-up',
        'move_pipeline'       => 'Move Pipeline Stage',
        'close_lead'          => 'Close Lead',
        'convert_to_patient'  => 'Convert to Patient',
        'escalate'            => 'Escalate',
        'no_action'           => 'No Further Action',
    ],

];
