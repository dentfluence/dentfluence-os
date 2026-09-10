<?php

namespace App\Services\Notifications;

use App\Models\Role;

/**
 * NotificationCatalog — the ONE list of events the clinic can be told about.
 *
 * Confirmed by the CEO on 9 Sep 2026 (tracker N-1). Every entry answers at
 * least one of: saves time · prevents a mistake · protects revenue. Nothing
 * is here because other clinic software has it.
 *
 * Shape per event:
 *   module   → grouping for the Settings matrix
 *   label    → what the admin sees in the matrix
 *   type     → AppNotification::type (drives the bell icon/colour)
 *   targets  → role slug => [level, push]   level: off | bell | popup
 *   owner    → [level, push] for the record's OWNER (case doctor, task
 *              assignee) or null when the event has no owner. Resolved by
 *              the caller, never by role lookup.
 *
 * TWO LEVELS ONLY. Popup means a human must act within ~5 minutes while the
 * patient is still standing at the desk. Six events qualify. Everything
 * else is bell — the admin can promote or demote in the matrix (N-4), but
 * the shipped defaults are these.
 *
 * Keys are stable identifiers: rules, rows and the matrix all reference them.
 * Rename a label freely; never rename a key without a data migration.
 */
final class NotificationCatalog
{
    public const LEVEL_OFF   = 'off';
    public const LEVEL_BELL  = 'bell';
    public const LEVEL_POPUP = 'popup';

    public const LEVELS = [self::LEVEL_OFF, self::LEVEL_BELL, self::LEVEL_POPUP];

    /** The literal role used for "the person this record belongs to". */
    public const OWNER = 'owner';

    public const MODULES = [
        'chairside'    => 'Chairside → Front desk',
        'appointments' => 'Appointments',
        'billing'      => 'Billing',
        'lab'          => 'Lab',
        'inventory'    => 'Inventory',
        'relationship' => 'Patient relationship',
        'hr'           => 'HR & Tasks',
        'system'       => 'System',
    ];

    private const B  = [self::LEVEL_BELL, false];
    private const BP = [self::LEVEL_BELL, true];
    private const P  = [self::LEVEL_POPUP, true];

    /** @var array<string, array{module:string,label:string,type:string,targets:array<string,array{0:string,1:bool}>,owner:?array{0:string,1:bool}}> */
    private const EVENTS = [
        // ── Chairside → desk ─────────────────────────────────────────────────
        'consultation.saved' => [
            'module' => 'chairside', 'type' => 'clinical',
            'label'  => 'Consultation saved — patient is coming to the desk',
            'targets' => [Role::FRONT_DESK => self::P], 'owner' => null,
        ],
        'visit.saved' => [
            'module' => 'chairside', 'type' => 'clinical',
            'label'  => 'Treatment visit saved — bill + next appointment',
            'targets' => [Role::FRONT_DESK => self::P], 'owner' => null,
        ],
        'billing_prompt.stale' => [
            'module' => 'chairside', 'type' => 'payment',
            'label'  => 'Billing prompt pending for 2+ hours',
            'targets' => [Role::FRONT_DESK => self::B, Role::MANAGER => self::B], 'owner' => null,
        ],

        // ── Appointments ─────────────────────────────────────────────────────
        'patient.arrived' => [
            'module' => 'appointments', 'type' => 'appointment',
            'label'  => 'Patient checked in',
            'targets' => [], 'owner' => self::BP,
        ],
        'patient.waiting' => [
            'module' => 'appointments', 'type' => 'appointment',
            'label'  => 'Patient waiting 15+ minutes',
            'targets' => [Role::FRONT_DESK => self::B], 'owner' => self::B,
        ],
        'appointment.cancelled' => [
            'module' => 'appointments', 'type' => 'appointment',
            'label'  => 'Appointment cancelled / no-show',
            'targets' => [Role::MANAGER => self::B], 'owner' => self::B,
        ],
        'appointment.lab_missing' => [
            'module' => 'appointments', 'type' => 'lab',
            'label'  => "Tomorrow's appointment but lab work not received",
            'targets' => [Role::FRONT_DESK => self::P], 'owner' => self::P,
        ],
        'appointment.next_booked' => [
            'module' => 'appointments', 'type' => 'appointment',
            'label'  => 'Next appointment booked after handover',
            'targets' => [], 'owner' => self::B,
        ],

        // ── Billing ──────────────────────────────────────────────────────────
        'invoice.created' => [
            'module' => 'billing', 'type' => 'payment',
            'label'  => 'Invoice created',
            'targets' => [], 'owner' => self::B,
        ],
        'payment.received' => [
            'module' => 'billing', 'type' => 'payment',
            'label'  => 'Payment received',
            'targets' => [Role::ADMIN => self::BP], 'owner' => null,
        ],
        'patient.left_with_dues' => [
            'module' => 'billing', 'type' => 'payment',
            'label'  => 'Patient left with dues (partial payment)',
            'targets' => [Role::MANAGER => self::B, Role::ADMIN => self::B], 'owner' => null,
        ],
        'invoice.cancelled' => [
            'module' => 'billing', 'type' => 'payment',
            'label'  => 'Invoice cancelled / refund',
            'targets' => [Role::ADMIN => self::P], 'owner' => null,
        ],
        'emi.due' => [
            'module' => 'billing', 'type' => 'payment',
            'label'  => 'EMI due today / overdue',
            'targets' => [Role::ACCOUNTS => self::B, Role::FRONT_DESK => self::B], 'owner' => null,
        ],
        'cash.close_missing' => [
            'module' => 'billing', 'type' => 'payment',
            'label'  => 'Day-end cash close not done',
            'targets' => [Role::ADMIN => self::BP, Role::MANAGER => self::BP], 'owner' => null,
        ],

        // ── Lab ──────────────────────────────────────────────────────────────
        'lab.draft_created' => [
            'module' => 'lab', 'type' => 'lab',
            'label'  => 'Lab case drafted — needs to be sent',
            'targets' => [Role::FRONT_DESK => self::B, Role::ASSISTANT => self::B], 'owner' => null,
        ],
        'lab.draft_stale' => [
            'module' => 'lab', 'type' => 'lab',
            'label'  => 'Lab draft not sent for 24 hours',
            'targets' => [Role::MANAGER => self::B], 'owner' => null,
        ],
        // Split from a single 'lab.received' on 9 Sep: trial and final have
        // DIFFERENT audiences in the code that already exists — a trial goes to
        // the doctor to review before it is returned, a final goes to the front
        // desk to book the delivery. One event would have silently dropped one
        // of those two jobs.
        'lab.trial_received' => [
            'module' => 'lab', 'type' => 'lab',
            'label'  => 'Trial work received — doctor to review',
            'targets' => [], 'owner' => self::BP,
        ],
        'lab.final_received' => [
            'module' => 'lab', 'type' => 'lab',
            'label'  => 'Final work received — book the delivery',
            'targets' => [Role::FRONT_DESK => self::B, Role::ADMIN => self::B], 'owner' => null,
        ],
        'lab.complete' => [
            'module' => 'lab', 'type' => 'lab',
            'label'  => 'Lab case complete and delivered',
            // The weakest of the five lab events — nobody acts on it. Kept
            // because it already existed; turn it off in the matrix if it
            // is just noise.
            'targets' => [], 'owner' => self::B,
        ],
        'lab.overdue' => [
            'module' => 'lab', 'type' => 'lab',
            'label'  => 'Lab case overdue',
            'targets' => [Role::ADMIN => self::B], 'owner' => self::B,
        ],
        'lab.rejected' => [
            'module' => 'lab', 'type' => 'lab',
            'label'  => 'Lab work rejected / rework',
            'targets' => [Role::ADMIN => self::B], 'owner' => self::B,
        ],

        // ── Inventory ────────────────────────────────────────────────────────
        'stock.reorder' => [
            'module' => 'inventory', 'type' => 'inventory',
            'label'  => 'Stock below reorder level',
            'targets' => [Role::ASSISTANT => self::B, Role::MANAGER => self::B], 'owner' => null,
        ],
        'stock.expiring' => [
            'module' => 'inventory', 'type' => 'inventory',
            'label'  => 'Stock expiring in 60 / 30 days',
            'targets' => [Role::ASSISTANT => self::B], 'owner' => null,
        ],
        'implant.consumed' => [
            'module' => 'inventory', 'type' => 'inventory',
            'label'  => 'Implant placed — stock consumed',
            'targets' => [Role::ADMIN => self::B], 'owner' => null,
        ],

        // ── Patient relationship ─────────────────────────────────────────────
        'lead.new' => [
            'module' => 'relationship', 'type' => 'system',
            'label'  => 'New lead',
            'targets' => [Role::FRONT_DESK => self::BP], 'owner' => null,
        ],
        'followup.due' => [
            'module' => 'relationship', 'type' => 'task_reminder',
            'label'  => 'Follow-up / recall due today',
            'targets' => [Role::FRONT_DESK => self::B], 'owner' => null,
        ],
        'whatsapp.failed' => [
            'module' => 'relationship', 'type' => 'system',
            'label'  => 'WhatsApp message failed to send',
            'targets' => [Role::FRONT_DESK => self::B], 'owner' => null,
        ],
        'review.negative' => [
            'module' => 'relationship', 'type' => 'system',
            'label'  => 'Negative review / complaint',
            'targets' => [Role::ADMIN => self::P], 'owner' => null,
        ],
        'membership.expiring' => [
            'module' => 'relationship', 'type' => 'payment',
            'label'  => 'AOCP membership expiring in 30 days',
            'targets' => [Role::FRONT_DESK => self::B], 'owner' => null,
        ],
        'membership.sold' => [
            'module' => 'relationship', 'type' => 'payment',
            'label'  => 'AOCP membership sold',
            'targets' => [Role::ADMIN => self::BP], 'owner' => null,
        ],

        // ── HR & Tasks ───────────────────────────────────────────────────────
        'task.assigned' => [
            'module' => 'hr', 'type' => 'task_assigned',
            'label'  => 'Task assigned / overdue',
            'targets' => [Role::MANAGER => self::B], 'owner' => self::BP,
        ],
        'staff.absent' => [
            'module' => 'hr', 'type' => 'shift_start',
            'label'  => 'Staff late / absent',
            'targets' => [Role::MANAGER => self::B, Role::ADMIN => self::B], 'owner' => null,
        ],
        'leave.requested' => [
            'module' => 'hr', 'type' => 'task_assigned',
            'label'  => 'Leave requested',
            'targets' => [Role::MANAGER => self::BP], 'owner' => null,
        ],
        'staff_document.expiring' => [
            'module' => 'hr', 'type' => 'system',
            'label'  => 'Staff document expiring',
            'targets' => [Role::MANAGER => self::B], 'owner' => null,
        ],

        // ── System ───────────────────────────────────────────────────────────
        'system.failure' => [
            'module' => 'system', 'type' => 'system',
            'label'  => 'Backup failed / queue jobs failing',
            'targets' => [Role::ADMIN => self::P], 'owner' => null,
        ],
        'dpdp.consent_missing' => [
            'module' => 'system', 'type' => 'system',
            'label'  => 'Photo uploaded without DPDP consent',
            'targets' => [], 'owner' => self::B,
        ],
        'login.new_device' => [
            'module' => 'system', 'type' => 'system',
            'label'  => 'Login from a new device',
            'targets' => [Role::ADMIN => self::BP], 'owner' => null,
        ],
    ];

    /** @return array<string, array> every event, keyed by event_key */
    public static function all(): array
    {
        return self::EVENTS;
    }

    public static function has(string $eventKey): bool
    {
        return isset(self::EVENTS[$eventKey]);
    }

    public static function get(string $eventKey): array
    {
        if (! self::has($eventKey)) {
            throw new \InvalidArgumentException("Unknown notification event '{$eventKey}'. Add it to NotificationCatalog first.");
        }

        return self::EVENTS[$eventKey];
    }

    /**
     * Shipped defaults as a flat rule list — what NotificationRuleSeeder
     * writes and what the dispatcher falls back to when no rule row exists.
     *
     * @return array<int, array{event_key:string, role:string, level:string, push:bool}>
     */
    public static function defaultRules(?string $eventKey = null): array
    {
        $events = $eventKey ? [$eventKey => self::get($eventKey)] : self::EVENTS;
        $rules  = [];

        foreach ($events as $key => $def) {
            foreach ($def['targets'] as $role => [$level, $push]) {
                $rules[] = ['event_key' => $key, 'role' => $role, 'level' => $level, 'push' => $push];
            }
            if ($def['owner']) {
                [$level, $push] = $def['owner'];
                $rules[] = ['event_key' => $key, 'role' => self::OWNER, 'level' => $level, 'push' => $push];
            }
        }

        return $rules;
    }

    /** Role slugs the Settings matrix shows as columns, in display order. */
    public static function roleColumns(): array
    {
        return [Role::ADMIN, Role::MANAGER, Role::DOCTOR, Role::ASSISTANT, Role::FRONT_DESK, Role::ACCOUNTS];
    }

    /** Events grouped by module, for the matrix. */
    public static function byModule(): array
    {
        $out = [];
        foreach (self::MODULES as $module => $label) {
            $out[$module] = ['label' => $label, 'events' => []];
        }
        foreach (self::EVENTS as $key => $def) {
            $out[$def['module']]['events'][$key] = $def;
        }

        return $out;
    }
}
