<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\AppSetting;
use App\Traits\Auditable;
use App\Traits\BelongsToBranch;

class Patient extends Model
{
    use HasFactory, SoftDeletes, Auditable, BelongsToBranch;

    /** Tag audit-log entries for this model with the "patients" module name. */
    protected $auditModule = 'patients';

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
        // ABDM / ABHA identity (added 2026-06-27) — see docs/abdm
        'abha_number',
        'abha_address',
        'abha_verification_status',
        'abha_linked_at',
        'preferred_language',
        'fhir_resource_id',
        'gov_id_type',
        'gov_id_last4',
        'abdm_care_contexts_count',
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
        // Structured referral
        'referral_type',
        'referred_patient_id',
        'referrer_name',
        'referrer_mobile',
        'referrer_type',
        'referrer_notes',
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
        'recall_no_visit_queued_at',   // recall-engine cooldown stamp
        'recall_birthday_queued_at',   // recall-engine cooldown stamp
        // PRE call-outcome automation flags (2026-07-05)
        'contact_invalid_at',
        'contact_invalid_reason',
        'automations_disabled_at',
        'automations_disabled_reason',
        // Relations
        'branch_id',
        'created_by',
        // Deactivation
        'is_active',
        'deactivation_reason',
        'deleted_reason',
        'deactivated_by',
    ];

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected $casts = [
        'date_of_birth'         => 'date',
        'membership_expires_at' => 'date',
        'next_recall_date'      => 'date',
        'last_visit_date'       => 'date',
        'follow_up_date'        => 'date',
        'dob_unknown'           => 'boolean',
        'abha_linked_at'        => 'datetime',
        'is_active'             => 'boolean',
        'contact_invalid_at'      => 'datetime',
        'automations_disabled_at' => 'datetime',
        'habits'                => 'array',
        'habit_frequency'       => 'array',
        'total_billed'          => 'decimal:2',
        'total_received'        => 'decimal:2',
        'outstanding_balance'   => 'decimal:2',

        // ── Encrypted PHI at rest (Phase A) ──────────────────────────────────
        // These use resilient casts (app/Casts) that encrypt on write and fall
        // back to plaintext on read until `php artisan patients:encrypt-phi`
        // has encrypted existing rows. phone/email are intentionally NOT here —
        // they're partial-searched and rely on DB-level encryption at rest.
        'address'                  => \App\Casts\Encrypted::class,
        'chief_complaint'          => \App\Casts\Encrypted::class,
        'medical_alert'            => \App\Casts\Encrypted::class,
        'current_medications'      => \App\Casts\Encrypted::class,
        'alternate_phone'          => \App\Casts\Encrypted::class,
        'emergency_contact_number' => \App\Casts\Encrypted::class,
        'abha_number'              => \App\Casts\Encrypted::class,
        'abha_address'             => \App\Casts\Encrypted::class,
        'allergies'                => \App\Casts\EncryptedArray::class,
        'medical_conditions'       => \App\Casts\EncryptedArray::class,
        'dental_conditions'        => \App\Casts\EncryptedArray::class,
    ];

    // ── Boot — auto-generate patient_id ──────────────────────────────────────

    protected static function booted(): void
    {
        static::created(function (Patient $patient) {
            // Don't overwrite if already set (e.g. preserved from import)
            if (!empty($patient->patient_id)) return;

            $auto = AppSetting::get('patient_id_auto', '1');
            if ($auto !== '1') return; // manual ID mode

            $prefix = AppSetting::get('patient_id_prefix', 'DF');
            $digits = (int) AppSetting::get('patient_id_digits', 5);
            $seq    = (int) AppSetting::get('patient_id_start', 1);

            // If the stored counter is behind the actual max in DB, fast-forward it
            // This handles cases where the counter got out of sync (e.g. bulk import)
            $maxExisting = static::whereNotNull('patient_id')
                ->where('patient_id', 'like', $prefix . '-%')
                ->selectRaw('MAX(CAST(SUBSTRING_INDEX(patient_id, "-", -1) AS UNSIGNED)) as max_seq')
                ->value('max_seq');

            if ($maxExisting !== null && $maxExisting >= $seq) {
                $seq = $maxExisting + 1;
            }

            $patient->updateQuietly([
                'patient_id' => $prefix . '-' . str_pad($seq, $digits, '0', STR_PAD_LEFT),
            ]);

            // Sync the counter to one ahead of what we just used
            AppSetting::set('patient_id_start', $seq + 1, 'patients');
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

    /** The existing patient who referred this patient. */
    public function referredPatient()
    {
        return $this->belongsTo(Patient::class, 'referred_patient_id');
    }

    /** Patients this patient has referred. */
    public function referrals()
    {
        return $this->hasMany(Patient::class, 'referred_patient_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /** All ABDM/identity records for this patient (internal id, ABHA, gov id...). */
    public function identifiers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\PatientIdentifier::class);
    }

    /** True once the patient's ABHA has been verified against ABDM. */
    public function isAbhaVerified(): bool
    {
        return $this->abha_verification_status === 'verified';
    }

    /**
     * First-class allergy rows (FHIR AllergyIntolerance source).
     * Named allergyRecords() to avoid clashing with the existing `allergies`
     * JSON attribute, which stays as-is for the CDSS.
     */
    public function allergyRecords(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\PatientAllergy::class);
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

    /**
     * Phase 8F — documents() removed. clinicalFiles() below is the only file relationship.
     * Phase 7D — Clinical Files (unified file store, replaces documents() in Phase 8).
     * Ordered by capture date descending so newest files appear first.
     */
    public function clinicalFiles(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\ClinicalFile::class)->latest('captured_at');
    }

    public function communications(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\PatientCommunication::class)->latest();
    }

    /** Current DPDP consent state, one row per purpose. */
    public function consents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\PatientConsent::class);
    }

    /** Append-only, tamper-evident consent history (DPDP 5.6). */
    public function consentLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\ConsentLog::class)->latest('id');
    }

    /** Best-effort age in years from DOB, else the stored age_years. */
    public function ageInYears(): ?int
    {
        if (! empty($this->date_of_birth)) {
            try { return \Illuminate\Support\Carbon::parse($this->date_of_birth)->age; } catch (\Throwable $e) {}
        }
        return is_null($this->age_years) ? null : (int) $this->age_years;
    }

    /** True if the patient is under 18 (DPDP minor — needs guardian consent). */
    public function isMinor(): bool
    {
        $age = $this->ageInYears();
        return ! is_null($age) && $age < 18;
    }

    /** DPDP data-rights requests (access / correction / erasure / grievance / nominee). */
    public function dataRequests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\DataRequest::class)->latest('requested_at');
    }

    /** General voice notes attached directly to the patient (polymorphic). */
    public function voiceNotes(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(\App\Models\VoiceNote::class, 'noteable')->latest();
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
        // No DOB on record (imported data often has only age_years, without
        // the dob_unknown flag set) — fall back to the stored age.
        if ($this->dob_unknown || !$this->date_of_birth) {
            return $this->age_years ? $this->age_years . ' yrs' : null;
        }
        return $this->date_of_birth->age . ' yrs';
    }

    /**
     * Numeric age for sorting / filtering.
     */
    public function getAgeNumericAttribute(): ?int
    {
        if ($this->dob_unknown || !$this->date_of_birth) return $this->age_years;
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

    // ── PRE call-outcome automation flags (2026-07-05) ──────────────────────

    /** Excludes patients flagged deceased/opted-out from ANY automation query. */
    public function scopeAutomationsEnabled($query)
    {
        return $query->whereNull('automations_disabled_at');
    }

    /**
     * "Wrong Number" / "Invalid Number" outcome — stop future recall attempts
     * without touching the phone field itself (front desk can still see/fix it).
     */
    public function markContactInvalid(string $reason = 'Marked invalid from a recall call outcome'): void
    {
        $this->contact_invalid_at     = now();
        $this->contact_invalid_reason = $reason;
        $this->save();
    }

    /**
     * "Deceased" outcome — permanent, global stop for every automated trigger
     * (recall, birthday). Distinct from the per-trigger cooldown
     * stamps, which are temporary and only pause one specific trigger.
     */
    public function disableAutomations(string $reason = 'Marked deceased from a recall call outcome'): void
    {
        $this->automations_disabled_at     = now();
        $this->automations_disabled_reason = $reason;
        $this->save();
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
