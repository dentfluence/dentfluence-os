<?php

declare(strict_types=1);

namespace App\Modules\PracticeProtocols\Models;

use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A standard, recurring duty defined once per role.
 *
 * The catalog layer only. Generating real tasks from active protocols
 * happens in Phase 2 — this model never creates tasks by itself.
 */
class PracticeProtocol extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'role_id',
        'branch_id',
        'category',
        'frequency',
        'weekday',
        'day_of_month',
        'default_due_time',
        'priority',
        'requires_evidence',
        'is_active',
        'sort_order',
        'created_by',
    ];

    protected $casts = [
        'requires_evidence' => 'boolean',
        'is_active'         => 'boolean',
        'weekday'           => 'integer',
        'day_of_month'      => 'integer',
        'sort_order'        => 'integer',
    ];

    // ── Label maps (for dropdowns / display) ──────────────────────────

    public const CATEGORIES = [
        'clinical'    => 'Clinical',
        'admin'       => 'Admin',
        'lab'         => 'Lab',
        'decon'       => 'Decontamination',
        'reception'   => 'Reception',
        'maintenance' => 'Maintenance',
        'other'       => 'Other',
    ];

    public const FREQUENCIES = [
        'once'    => 'One-off',
        'daily'   => 'Daily',
        'weekly'  => 'Weekly',
        'monthly' => 'Monthly',
    ];

    public const PRIORITIES = [
        'urgent' => 'Urgent',
        'high'   => 'High',
        'medium' => 'Medium',
        'low'    => 'Low',
    ];

    // ── Relationships ─────────────────────────────────────────────────

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(PracticeProtocolMaterial::class)
                    ->orderBy('sort_order');
    }

    // ── Scopes ────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Protocols for a specific branch, including practice-wide ones
     * (branch_id = null means "all branches").
     */
    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where(function (Builder $q) use ($branchId) {
            $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
        });
    }

    /**
     * Protocols whose schedule lands on the given date.
     * Used by the Phase 2 generator. ('once' is always returned here;
     * the generator de-duplicates so it only ever fires a single task.)
     */
    public function scopeDueOn(Builder $query, Carbon $date): Builder
    {
        return $query->where(function (Builder $q) use ($date) {
            $q->where('frequency', 'daily')
              ->orWhere('frequency', 'once')
              ->orWhere(fn (Builder $w) => $w->where('frequency', 'weekly')
                                             ->where('weekday', $date->dayOfWeek))
              ->orWhere(fn (Builder $w) => $w->where('frequency', 'monthly')
                                             ->where('day_of_month', $date->day));
        });
    }

    // ── Helpers ───────────────────────────────────────────────────────

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst((string) $this->category);
    }

    public function frequencyLabel(): string
    {
        return self::FREQUENCIES[$this->frequency] ?? ucfirst((string) $this->frequency);
    }

    public function priorityLabel(): string
    {
        return self::PRIORITIES[$this->priority] ?? ucfirst((string) $this->priority);
    }
}
