<?php

namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
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
        'queue_position',
        'estimated_wait_minutes',
        'chair_number',
        // Operatory layer (Phase: Operatory)
        'operatory_id',
    ];
 
    protected $casts = [
        'appointment_date'  => 'date',
        'is_walkin'         => 'boolean',
        'checked_in_at'     => 'datetime',
        'in_chair_at'       => 'datetime',
        'completed_at'      => 'datetime',
        'amount_to_collect' => 'decimal:2',
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
 
    // ── Helpers ───────────────────────────────────────────────────
 
    public function isActive(): bool
    {
        return in_array($this->status, ['scheduled', 'checkin', 'in_chair']);
    }
 
    public function getEndTimeAttribute(): string
    {
        return Carbon::parse($this->appointment_time)
            ->addMinutes($this->duration_minutes ?? 30)
            ->format('H:i');
    }
}
 