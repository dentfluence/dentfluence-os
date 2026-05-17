<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Patient extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'phone', 'date_of_birth', 'gender', 'email',
        'address', 'city', 'state', 'pincode',
        'occupation', 'habits', 'allergies', 'family_notes',
        'chief_complaint', 'medical_alert',
        'source', 'referred_by',
        'recall_status', 'next_recall_date', 'last_visit_date',
        'outstanding_balance', 'lifetime_value',
        'branch_id', 'created_by',
    ];

    protected $casts = [
        'date_of_birth'    => 'date',
        'next_recall_date' => 'date',
        'last_visit_date'  => 'date',
        'habits'           => 'array',
        'allergies'        => 'array',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function appointments()
    {
        return $this->hasMany(Appointment::class)
                    ->orderBy('appointment_date', 'desc')
                    ->orderBy('appointment_time', 'desc');
    }

    public function notes()
    {
        return $this->hasMany(PatientNote::class);
    }

    public function relationshipNotes()
    {
        return $this->hasMany(PatientRelationshipNote::class)->latest();
    }

    public function opportunities()
    {
        return $this->hasMany(TreatmentOpportunity::class)->latest();
    }

    public function alerts()
    {
        return $this->hasMany(PatientAlert::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    // ── Accessors ────────────────────────────────────────────────────────────

    public function getAgeAttribute(): ?string
    {
        if (!$this->date_of_birth) return null;
        return $this->date_of_birth->age . ' yrs';
    }

    public function getInitialsAttribute(): string
    {
        $parts = explode(' ', trim($this->name));
        $initials = strtoupper(substr($parts[0], 0, 1));
        if (isset($parts[1])) $initials .= strtoupper(substr($parts[1], 0, 1));
        return $initials;
    }

    public function getRecallBadgeColorAttribute(): string
    {
        return match($this->recall_status) {
            'active'   => 'bg-green-50 text-green-700 border-green-200',
            'due'      => 'bg-amber-50 text-amber-700 border-amber-200',
            'overdue'  => 'bg-red-50 text-red-600 border-red-200',
            'inactive' => 'bg-gray-100 text-gray-500 border-gray-200',
            default    => 'bg-gray-100 text-gray-500 border-gray-200',
        };
    }

    // Legacy dob alias
    public function getDobAttribute() { return $this->date_of_birth; }
    public function setDobAttribute($v) { $this->attributes['date_of_birth'] = $v; }
    public function tags()
{
    return $this->belongsToMany(Tag::class, 'patient_tag')
                ->withPivot('added_by')
                ->withTimestamps();
}
}