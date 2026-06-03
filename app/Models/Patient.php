<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Patient extends Model
{
    use HasFactory, SoftDeletes;

    // ── Fillable ──────────────────────────────────────────────────────────────

    protected $fillable = [
        // Identity
        'patient_id',
        'title',
        'name',
        'first_name',
        'middle_name',
        'last_name',
        'gender',
        'date_of_birth',
        'dob_unknown',
        'age_years',
        // Contact
        'phone',
        'alternate_phone',
        'email',
        'emergency_contact_name',
        'emergency_contact_relationship',
        'emergency_contact_number',
        // Address
        'address',
        'area',
        'city',
        'state',
        'pincode',
        // Clinical
        'chief_complaint',
        'medical_alert',
        'medical_conditions',
        'current_medications',
        'dental_conditions',
        // Habits
        'habits',
        'habit_frequency',
        // Source
        'source',
        'referred_by',
        'source_referral_name',
        'source_camp_name',
        'source_campaign',
        // Membership & follow-up
        'membership_status',
        'membership_expires_at',
        'follow_up_status',
        'follow_up_date',
        // Financial (denormalized)
        'total_billed',
        'total_received',
        'outstanding_balance',
        'lifetime_value',
        // Misc
        'photo',
        'occupation',
        'family_notes',
        'allergies',
        'recall_status',
        'next_recall_date',
        'last_visit_date',
        // Relations
        'branch_id',
        'created_by',
    ];

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected $casts = [
        'date_of_birth'         => 'date',
        'membership_expires_at' => 'date',
        'next_recall_date'      => 'date',
        'last_visit_date'       => 'date',
        'follow_up_date'        => 'date',
        'dob_unknown'           => 'boolean',
        'habits'                => 'array',
        'habit_frequency'       => 'array',
        'allergies'             => 'array',
        'medical_conditions'    => 'array',
        'dental_conditions'     => 'array',
        'total_billed'          => 'decimal:2',
        'total_received'        => 'decimal:2',
        'outstanding_balance'   => 'decimal:2',
    ];

    // ── Boot — auto-generate patient_id ──────────────────────────────────────

    protected static function booted(): void
    {
        static::created(function (Patient $patient) {
            if (empty($patient->patient_id)) {
                $patient->updateQuietly([
                    'patient_id' => 'DF-' . str_pad($patient->id, 5, '0', STR_PAD_LEFT),
                ]);
            }
        });
    }

    // ── Relationships ─────────────────────────────────────────────────────────

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

    public function treatmentVisits(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\TreatmentVisit::class)->latest('visit_date');
    }

    public function treatmentPlans(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\TreatmentPlan::class)->latest();
    }

    public function consultations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Consultation::class)->latest();
    }

    public function labCases(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\LabCase::class)->latest('sent_date');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'patient_tag')
            ->withPivot('added_by')
            ->withTimestamps();
    }

    /**
     * Family / linked patients (outgoing links from this patient).
     */
    public function linkedPatients()
    {
        return $this->belongsToMany(
            Patient::class,
            'patient_links',
            'patient_id',
            'linked_patient_id'
        )->withPivot('relationship', 'added_by')->withTimestamps();
    }

    /**
     * Reverse links — patients that link TO this patient.
     */
    public function linkedByPatients()
    {
        return $this->belongsToMany(
            Patient::class,
            'patient_links',
            'linked_patient_id',
            'patient_id'
        )->withPivot('relationship', 'added_by')->withTimestamps();
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    /**
     * Age string — from DOB if known, else from age_years field.
     */
    public function getAgeAttribute(): ?string
    {
        if ($this->dob_unknown) {
            return $this->age_years ? $this->age_years . ' yrs' : null;
        }
        if (!$this->date_of_birth) return null;
        return $this->date_of_birth->age . ' yrs';
    }

    /**
     * Numeric age for sorting / filtering.
     */
    public function getAgeNumericAttribute(): ?int
    {
        if ($this->dob_unknown) return $this->age_years;
        if (!$this->date_of_birth) return null;
        return $this->date_of_birth->age;
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
        return match ($this->recall_status) {
            'active'   => 'bg-green-50 text-green-700 border-green-200',
            'due'      => 'bg-amber-50 text-amber-700 border-amber-200',
            'overdue'  => 'bg-red-50 text-red-600 border-red-200',
            'inactive' => 'bg-gray-100 text-gray-500 border-gray-200',
            default    => 'bg-gray-100 text-gray-500 border-gray-200',
        };
    }

    /**
     * Whether AOCP membership is currently active (checks expiry date too).
     */
    public function getIsAocpActiveAttribute(): bool
    {
        if ($this->membership_status !== 'active') return false;
        if ($this->membership_expires_at && $this->membership_expires_at->isPast()) return false;
        return true;
    }

    /**
     * Effective membership status — auto-expires if past date.
     */
    public function getEffectiveMembershipStatusAttribute(): string
    {
        if ($this->membership_status === 'active'
            && $this->membership_expires_at
            && $this->membership_expires_at->isPast()
        ) {
            return 'expired';
        }
        return $this->membership_status ?? 'not_enrolled';
    }

    // ── Legacy aliases ────────────────────────────────────────────────────────

    public function getDobAttribute()
    {
        return $this->date_of_birth;
    }

    public function setDobAttribute($v)
    {
        $this->attributes['date_of_birth'] = $v;
    }
}
