<?php

namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Enums\AppointmentStatus;
use App\Traits\Auditable;
use Carbon\Carbon;

class Appointment extends Model
{
    use SoftDeletes, Auditable, \App\Traits\BelongsToBranch;

    /** Tag audit-log entries for this model with the "appointments" module. */
    protected $auditModule = 'appointments';

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'branch_id',
        'created_by',
        'treatment_category_id',
        'treatment_id',
        'appointment_date',
        'appointment_time',
        'duration_minutes',
        'type',
        'status',
        'previous_status', // for the "revert" (undo) button on the day sheet
        'cancel_reason',   // reason captured when an appointment is cancelled
        'cancelled_party', // who initiated the cancellation: patient | clinic
        'notes',
        'chief_complaint',
        'staff_instruction',
        // Today's Patient Flow popup (Huddle board, 2026-07-06)
        'amount_to_collect',
        'prep_item',
        'chairside_assistant_id',
        // Phase 2 additions
        'is_walkin',
        'checked_in_at',
        'in_chair_at',
        'completed_at',
        'chair_number',
        // Operatory layer (Phase: Operatory)
        'operatory_id',
        // Soft-hide a cancelled appointment from the calendar day sheet
        'hidden_from_calendar',
    ];
 
    protected $casts = [
        'appointment_date'  => 'date',
        'is_walkin'         => 'boolean',
        'checked_in_at'     => 'datetime',
        'in_chair_at'       => 'datetime',
        'completed_at'      => 'datetime',
        'amount_to_collect' => 'decimal:2',
        'hidden_from_calendar' => 'boolean',
    ];
 
    // ── Relationships ─────────────────────────────────────────────
 
    public function patient()         { return $this->belongsTo(Patient::class); }
    public function doctor()          { return $this->belongsTo(User::class, 'doctor_id'); }
    public function createdBy()       { return $this->belongsTo(User::class, 'created_by'); }
    public function treatment()       { return $this->belongsTo(Treatment::class); }
    public function treatmentCategory() { return $this->belongsTo(TreatmentCategory::class); }
    public function operatory()          { return $this->belongsTo(Operatory::class); }
    public function chairsideAssistant() { return $this->belongsTo(User::class, 'chairside_assistant_id'); }
 
    // ── Scopes ────────────────────────────────────────────────────
 
    public function scopeToday($query)
    {
        return $query->whereDate('appointment_date', today());
    }
 
    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }
 
    public function scopeForDoctor($query, $doctorId)
    {
        return $query->where('doctor_id', $doctorId);
    }

    /**
     * Live/active appointments — everything except the terminal
     * (cancelled / no-show) statuses. Canonical replacement for the
     * `whereNotIn('status', ['cancelled','no_show'])` that was copy-pasted
     * across the app.
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', AppointmentStatus::terminalValues());
    }

    /** Appointments on a specific date (Y-m-d string or Carbon). */
    public function scopeForDate($query, $date)
    {
        return $query->whereDate('appointment_date', $date);
    }

    /** Appointments within an inclusive date range. */
    public function scopeInDateRange($query, $from, $to)
    {
        return $query->whereBetween('appointment_date', [$from, $to]);
    }

    /**
     * Appointments the calendar shows — a cancelled appointment hidden via the
     * calendar's 3-dot "hide" action is excluded. Canonical replacement for the
     * copy-pasted `where('hidden_from_calendar', false)`.
     */
    public function scopeVisibleOnCalendar($query)
    {
        return $query->where('hidden_from_calendar', false);
    }
 
    // ── Helpers ───────────────────────────────────────────────────
 
    public function isActive(): bool
    {
        return in_array($this->status, AppointmentStatus::inProgressValues(), true);
    }
 
    public function getEndTimeAttribute(): string
    {
        return Carbon::parse($this->appointment_time)
            ->addMinutes($this->duration_minutes ?? 30)
            ->format('H:i');
    }
}
 