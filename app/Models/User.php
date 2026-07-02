<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\Auditable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, Auditable;

    /** Tags this model's audit-log entries with a module name. */
    protected $auditModule = 'users';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',        // legacy string kept for backward compat during transition
        'role_id',     // FK to roles table (new system)
        'branch_id',
        'is_active',
        'last_login_at',
        'phone',       // added in profile migration
        'designation', // e.g. "Senior Dentist"
        'avatar',      // path to uploaded photo
        'color',       // hex color for calendar display e.g. #3b82f6
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
            'last_login_at'     => 'datetime',
            // 2FA (Phase A) — encrypted at rest.
            'two_factor_secret'         => \App\Casts\Encrypted::class,
            'two_factor_recovery_codes' => \App\Casts\EncryptedArray::class,
            'two_factor_confirmed_at'   => 'datetime',
        ];
    }

    /* =========================================================
       TWO-FACTOR AUTHENTICATION (MFA) helpers
    ========================================================= */

    /** True only when the user has set up AND confirmed an authenticator app. */
    public function hasTwoFactorEnabled(): bool
    {
        return ! empty($this->two_factor_secret) && ! is_null($this->two_factor_confirmed_at);
    }

    /** Generate a fresh set of one-time recovery codes. */
    public static function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(5)) . '-'
                     . \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(5));
        }
        return $codes;
    }

    /**
     * If $code matches an unused recovery code, consume it (remove + save) and
     * return true. Otherwise false.
     */
    public function useRecoveryCode(string $code): bool
    {
        $codes = $this->two_factor_recovery_codes ?? [];
        $code = trim(strtoupper($code));

        if (! in_array($code, $codes, true)) {
            return false;
        }

        $this->two_factor_recovery_codes = array_values(array_diff($codes, [$code]));
        $this->save();

        return true;
    }

    /* =========================================================
       RELATIONSHIPS
    ========================================================= */

    /**
     * The role this user belongs to (new system).
     */
    public function roleModel(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /* ── HR Relationships ── */

    public function hrProfile(): HasOne
    {
        return $this->hasOne(HrStaffProfile::class, 'user_id');
    }

    /* ── ABDM / Practitioner identity (added 2026-06-27) ── */

    /** Identity records for this practitioner (internal id, HPR id, council reg). */
    public function practitionerIdentifiers(): HasMany
    {
        return $this->hasMany(PractitionerIdentifier::class, 'user_id');
    }

    /** Qualifications/registrations → FHIR Practitioner.qualification[]. */
    public function qualifications(): HasMany
    {
        return $this->hasMany(PractitionerQualification::class, 'user_id');
    }

    public function hrAttendance(): HasMany
    {
        return $this->hasMany(HrAttendance::class, 'user_id');
    }

    public function hrShifts(): HasMany
    {
        return $this->hasMany(HrStaffShift::class, 'user_id');
    }

    public function hrDocuments(): HasMany
    {
        return $this->hasMany(HrStaffDocument::class, 'user_id')->latest();
    }

    public function hrSalary(): HasOne
    {
        return $this->hasOne(HrSalaryComponent::class, 'user_id');
    }

    public function hrIncentiveRule(): HasOne
    {
        return $this->hasOne(HrIncentiveRule::class, 'user_id');
    }

    public function hrAdvances(): HasMany
    {
        return $this->hasMany(HrStaffAdvance::class, 'user_id')->latest();
    }

    public function hrBonuses(): HasMany
    {
        return $this->hasMany(HrBonus::class, 'user_id')->latest();
    }

    public function hrEntryExitLogs(): HasMany
    {
        return $this->hasMany(HrEntryExitLog::class, 'user_id')->latest('logged_at');
    }

    /**
     * Currently active shift assignment.
     */
    public function currentShift(): HasOne
    {
        return $this->hasOne(HrStaffShift::class, 'user_id')
                    ->where('effective_from', '<=', now())
                    ->where(function ($q) {
                        $q->whereNull('effective_to')
                          ->orWhere('effective_to', '>=', now());
                    });
    }

    /* =========================================================
       PERMISSION HELPERS
       Check module access via the roles system.
    ========================================================= */

    /**
     * Check if user can perform action on a module.
     * Action: 'view' | 'edit' | 'delete'
     */
    public function canAccess(string $module, string $action = 'view'): bool
    {
        // Admin (by old role string) always gets full access during transition
        if ($this->role === 'admin' && ! $this->role_id) {
            return true;
        }

        $role = $this->relationLoaded('roleModel')
            ? $this->roleModel
            : $this->roleModel()->with('permissions.module')->first();

        if (! $role) return false;

        return $role->can($module, $action);
    }

    /**
     * Check if user's role is admin (either system).
     */
    public function isAdminRole(): bool
    {
        return ($this->role === 'admin')
            || ($this->roleModel && $this->roleModel->slug === Role::ADMIN);
    }

    /* =========================================================
       ROLE CONSTANTS
       Single login phase: only 'admin' is used.
       All role values are defined now so Phase 12 (multi-login)
       requires zero model changes — just activate middleware.
    ========================================================= */

    const ROLE_ADMIN                = 'admin';
    const ROLE_DOCTOR               = 'doctor';             // legacy
    const ROLE_RESIDENT_DENTIST     = 'resident_dentist';
    const ROLE_ASSOCIATE_DENTIST    = 'associate_dentist';
    const ROLE_VISITING_CONSULTANT  = 'visiting_consultant';
    const ROLE_FRONT_DESK           = 'front_desk';
    const ROLE_ASSISTANT            = 'assistant';
    const ROLE_ACCOUNTS             = 'accounts';

    /** All role values treated as "doctor" for access/display purposes */
    const DOCTOR_ROLES = [
        'doctor', 'resident_dentist', 'associate_dentist', 'visiting_consultant',
    ];

    /* =========================================================
       ROLE HELPER METHODS
       Currently: single login — these always return true for
       admin. When roles activated in Phase 12, they work
       automatically with zero code changes.
    ========================================================= */

    /**
     * Check if user has a specific role.
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if user is admin / clinic owner.
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Check if user is a doctor.
     */
    public function isDoctor(): bool
    {
        return in_array($this->role, self::DOCTOR_ROLES)
            || str_starts_with(trim($this->name), 'Dr.');
    }

    /**
     * Check if user is front desk staff.
     */
    public function isFrontDesk(): bool
    {
        return $this->role === self::ROLE_FRONT_DESK;
    }

    /**
     * Check if user is dental assistant.
     */
    public function isAssistant(): bool
    {
        return $this->role === self::ROLE_ASSISTANT;
    }

    /**
     * Check if user is accounts staff.
     */
    public function isAccounts(): bool
    {
        return $this->role === self::ROLE_ACCOUNTS;
    }

    /**
     * Check if user can access clinical screens.
     * Doctors and admins only.
     */
    public function isClinical(): bool
    {
        return in_array($this->role, [
            self::ROLE_ADMIN,
            self::ROLE_DOCTOR,
        ]);
    }

    /**
     * Get display name for role.
     */
    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            'admin'                => 'Admin',
            'doctor'               => 'Doctor',
            'resident_dentist'     => 'Resident Dentist',
            'associate_dentist'    => 'Associate Dentist',
            'visiting_consultant'  => 'Visiting Consultant',
            'front_desk'           => 'Front Desk',
            'assistant'            => 'Assistant',
            'accounts'             => 'Accounts',
            default                => ucfirst(str_replace('_', ' ', $this->role)),
        };
    }

    /**
     * Get user initials for avatar display.
     */
    public function getInitialsAttribute(): string
    {
        $words = explode(' ', trim($this->name));

        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
        }

        return strtoupper(substr($this->name, 0, 2));
    }

    /**
     * Doctor display name with a single "Dr." prefix.
     * If the stored name already begins with "Dr"/"Dr." we return it as-is,
     * otherwise we prepend "Dr. ". This prevents "Dr. Dr. Name" on documents.
     */
    public function getDoctorNameAttribute(): string
    {
        $n = trim($this->name ?? '');
        if ($n === '') {
            return '';
        }
        return preg_match('/^dr\.?\s/i', $n) ? $n : 'Dr. ' . $n;
    }

    /**
     * Doctor's professional registration / dental-council number.
     * The number is stored on the HR staff profile as `license_number`;
     * this accessor surfaces it as $user->registration_number so all the
     * print templates (case paper, prescription, treatment plan) can show
     * it consistently. Returns null if no HR profile / number is set.
     */
    public function getRegistrationNumberAttribute(): ?string
    {
        return $this->hrProfile?->license_number;
    }

    /* =========================================================
       QUERY SCOPES
    ========================================================= */

    /**
     * Scope: only active users.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: filter by role.
     */
    public function scopeWithRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Scope: filter by branch.
     */
    public function scopeForBranch($query, int $branchId = 1)
    {
        return $query->where('branch_id', $branchId);
    }

    /* =========================================================
       METHODS
    ========================================================= */

    /**
     * Update last login timestamp.
     * Called in AuthController after successful login.
     */
    public function recordLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }
}