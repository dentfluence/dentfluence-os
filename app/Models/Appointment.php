<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
    ];

    protected $casts = [
        'appointment_date' => 'date',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function treatment()
    {
        return $this->belongsTo(Treatment::class);
    }

    public function treatmentCategory()
    {
        return $this->belongsTo(TreatmentCategory::class);
    }
}