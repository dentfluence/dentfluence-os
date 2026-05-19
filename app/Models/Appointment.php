<?php

namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
 
class Appointment extends Model
{
    use SoftDeletes;
 
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
        'notes',
        'chief_complaint',
        // Phase 2 additions
        'is_walkin',
        'checked_in_at',
        'in_chair_at',
        'completed_at',
        'queue_position',
        'estimated_wait_minutes',
        'chair_number',
    ];
 
    protected $casts = [
        'appointment_date'  => 'date',
        'is_walkin'         => 'boolean',
        'checked_in_at'     => 'datetime',
        'in_chair_at'       => 'datetime',
        'completed_at'      => 'datetime',
    ];
 
    // ── Relationships ─────────────────────────────────────────────
 
    public function patient()         { return $this->belongsTo(Patient::class); }
    public function doctor()          { return $this->belongsTo(User::class, 'doctor_id'); }
    public function createdBy()       { return $this->belongsTo(User::class, 'created_by'); }
    public function treatment()       { return $this->belongsTo(Treatment::class); }
    public function treatmentCategory() { return $this->belongsTo(TreatmentCategory::class); }
 
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
 