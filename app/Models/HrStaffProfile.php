<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class HrStaffProfile extends Model
{
    protected $fillable = [
        'user_id',
        'department_id',
        'employee_code',
        'joining_date',
        'employment_type',
        'date_of_birth',
        'gender',
        'blood_group',
        'address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        'license_number',
        'license_expiry',
        'qualification',
        'specialization',
        // ABDM / HPR identity (added 2026-06-27) — see docs/abdm
        'hpr_id',
        'hpr_verification_status',
        'hpr_linked_at',
        'medical_council_name',
        'registration_year',
        'digital_signature_ref',
        'fhir_practitioner_id',
        'salary_type',
        'basic_salary',
        'qr_token',
        'notes',
        'bank_name',
        'account_holder_name',
        'account_number',
        'ifsc_code',
        'branch_name',
        'whatsapp_number',
        'alternate_phone',
        'alternate_email',
    ];

    protected $casts = [
        'joining_date'   => 'date',
        'date_of_birth'  => 'date',
        'license_expiry' => 'date',
        'hpr_linked_at'  => 'datetime',
        'basic_salary'   => 'decimal:2',
        // Encrypted staff bank details at rest (Phase A). Resilient casts — see
        // app/Casts/Encrypted.php. Not searched anywhere (verified).
        'account_number' => \App\Casts\Encrypted::class,
        'ifsc_code'      => \App\Casts\Encrypted::class,
    ];

    /* ── Boot: auto-generate QR token on create ── */

    protected static function booted(): void
    {
        static::creating(function (HrStaffProfile $profile) {
            if (empty($profile->qr_token)) {
                $profile->qr_token = Str::uuid()->toString();
            }
        });
    }

    /* ── Relationships ── */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(HrDepartment::class, 'department_id');
    }

    /* ── Computed Attributes ── */

    /**
     * Days until license expires. Null if no expiry set.
     */
    public function getLicenseDaysRemainingAttribute(): ?int
    {
        if (! $this->license_expiry) return null;
        return now()->diffInDays($this->license_expiry, false);
    }

    /**
     * License status: 'ok' | 'expiring_soon' (≤30 days) | 'expired'
     */
    public function getLicenseStatusAttribute(): string
    {
        $days = $this->license_days_remaining;
        if ($days === null) return 'none';
        if ($days < 0)  return 'expired';
        if ($days <= 30) return 'expiring_soon';
        return 'ok';
    }

    /**
     * Age from date_of_birth.
     */
    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth ? $this->date_of_birth->age : null;
    }

    /**
     * Years of service since joining_date.
     */
    public function getYearsOfServiceAttribute(): ?string
    {
        if (! $this->joining_date) return null;
        $years  = $this->joining_date->diffInYears(now());
        $months = $this->joining_date->diffInMonths(now()) % 12;
        if ($years === 0) return "{$months}m";
        return $months > 0 ? "{$years}y {$months}m" : "{$years}y";
    }

    /* ── Scopes ── */

    public function scopeByDepartment($query, int $deptId)
    {
        return $query->where('department_id', $deptId);
    }
}
