<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

/**
 * Relationship — the permanent master record for every person.
 *
 * A Relationship is never deleted. It is the single source of truth
 * that links a Lead, a Patient, all their journeys, and the full
 * activity timeline across the entire lifecycle with the clinic.
 *
 * @property int         $id
 * @property string      $name
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $source
 * @property string      $status    active|dormant|lost
 * @property int         $score
 * @property \Carbon\Carbon|null $relationship_since
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class Relationship extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'source',
        'status',
        'score',
        'relationship_since',
        // Phase 4 — CommunicationGuard Preference factor + hard opt-out.
        'preferred_channel',
        'do_not_contact',
    ];

    protected $casts = [
        'relationship_since' => 'date',
        'score'              => 'integer',
        'do_not_contact'     => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    /**
     * The Lead that first brought this person into the system.
     * A Relationship has at most one Lead (once, they're a Lead; then a Patient).
     */
    public function lead(): HasOne
    {
        return $this->hasOne(Lead::class);
    }

    /**
     * The Patient record created when this person converted.
     *
     * NOTE: kept as hasOne for backward-compat — most relationships map to a
     * single patient. For the ~18 "household" relationships that share a phone
     * (and therefore link several patients), use patients() to see them all.
     */
    public function patient(): HasOne
    {
        return $this->hasOne(Patient::class);
    }

    /**
     * ALL patients linked to this relationship (Phase 1 · Workstream D, slice 4).
     *
     * Households: several people sharing one phone are linked to a single
     * relationship. This surfaces every one of them, rather than just the
     * hasOne above.
     */
    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    /**
     * All journeys (lead, treatment, recall, opportunity, membership, referral).
     */
    public function journeys(): HasMany
    {
        return $this->hasMany(RelationshipJourney::class);
    }

    /**
     * All activity log entries for this relationship (unified timeline).
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->latest('occurred_at');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    /** Only active relationships. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /** Only dormant relationships. */
    public function scopeDormant(Builder $query): Builder
    {
        return $query->where('status', 'dormant');
    }

    /** Find by exact phone number. */
    public function scopeByPhone(Builder $query, string $phone): Builder
    {
        return $query->where('phone', $phone);
    }

    /** Find by exact email address. */
    public function scopeByEmail(Builder $query, string $email): Builder
    {
        return $query->where('email', $email);
    }
}
