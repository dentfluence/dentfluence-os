<?php

namespace App\Models;

use App\Enums\CancellationReason;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One decision about one cancelled appointment.
 *
 * Written only by AppointmentService::cancel() — nothing else creates these,
 * so there is one writer for this fact (the rule that W-4 and W-5 were spent
 * restoring for money). Read by the quick-view card and, later, by the
 * recovery-rate report.
 *
 * Note: this model deliberately does NOT use BelongsToBranch. That trait adds a
 * global scope, and a global scope on an audit-shaped table is how a report
 * quietly loses rows. branch_id is set explicitly by the service and filtered
 * explicitly by the reader.
 */
class AppointmentCancellation extends Model
{
    use Auditable;

    /** Tag audit-log entries for this model with the "appointments" module. */
    protected $auditModule = 'appointments';

    /** The patient was told what happens next, and it is recorded. */
    public const OUTCOME_CALLBACK      = 'callback';
    public const OUTCOME_NOT_RETURNING = 'not_returning';
    public const OUTCOME_REBOOKED      = 'rebooked';
    /** Nobody decided. Counting these is the point of having the column. */
    public const OUTCOME_UNSPECIFIED   = 'unspecified';

    public const OUTCOMES = [
        self::OUTCOME_CALLBACK      => 'Call back',
        self::OUTCOME_NOT_RETURNING => 'Not returning',
        self::OUTCOME_REBOOKED      => 'Rebooked',
        self::OUTCOME_UNSPECIFIED   => 'No decision recorded',
    ];

    /**
     * The outcomes the cancel modal may send. `rebooked` is absent on purpose:
     * moving a patient to a new slot goes through reschedule(), which never
     * cancels the appointment at all. `unspecified` is absent because a human
     * at a screen must decide — only the older mobile API may leave it blank.
     */
    public const CHOOSABLE_OUTCOMES = [
        self::OUTCOME_CALLBACK,
        self::OUTCOME_NOT_RETURNING,
    ];

    protected $fillable = [
        'appointment_id',
        'patient_id',
        'branch_id',
        'reason_code',
        'reason_note',
        'cancelled_party',
        'outcome',
        'callback_date',
        'task_id',
        'rebooked_appointment_id',
        'cancelled_by',
        'cancelled_at',
    ];

    protected $casts = [
        'callback_date' => 'date',
        'cancelled_at'  => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function rebookedAppointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'rebooked_appointment_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    // ── Helpers ───────────────────────────────────────────────────

    /** Human label for the reason, falling back to the raw code if the list changed. */
    public function reasonLabel(): string
    {
        return CancellationReason::tryFrom($this->reason_code)?->label()
            ?? ucfirst(str_replace('_', ' ', (string) $this->reason_code));
    }

    public function outcomeLabel(): string
    {
        return self::OUTCOMES[$this->outcome] ?? $this->outcome;
    }

    /**
     * Was this cancellation dealt with?
     *
     * A rebooking or a decided "not returning" is dealt with. A callback is only
     * dealt with once the task is actually done — an unfinished callback task is
     * a patient still sitting in the gap, and the recovery rate must not flatter
     * itself by counting the intention instead of the act.
     */
    public function isResolved(): bool
    {
        return match ($this->outcome) {
            self::OUTCOME_REBOOKED, self::OUTCOME_NOT_RETURNING => true,
            self::OUTCOME_CALLBACK => (bool) $this->task?->isDone(),
            default => false,
        };
    }
}
