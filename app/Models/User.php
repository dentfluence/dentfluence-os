<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'branch_id',
        'is_active',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
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
        ];
    }

    /* =========================================================
       ROLE CONSTANTS
       Single login phase: only 'admin' is used.
       All role values are defined now so Phase 12 (multi-login)
       requires zero model changes — just activate middleware.
    ========================================================= */

    const ROLE_ADMIN       = 'admin';
    const ROLE_DOCTOR      = 'doctor';
    const ROLE_FRONT_DESK  = 'front_desk';
    const ROLE_ASSISTANT   = 'assistant';
    const ROLE_ACCOUNTS    = 'accounts';

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
        return $this->role === self::ROLE_DOCTOR;
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
            self::ROLE_ADMIN      => 'Admin',
            self::ROLE_DOCTOR     => 'Doctor',
            self::ROLE_FRONT_DESK => 'Front Desk',
            self::ROLE_ASSISTANT  => 'Assistant',
            self::ROLE_ACCOUNTS   => 'Accounts',
            default               => 'Staff',
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