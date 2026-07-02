<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * Activity — Universal Activity Engine log entry.
 *
 * Every meaningful thing that happens in the system writes a row here.
 * This model is the single source of truth for the unified Timeline (Phase 3).
 *
 * It does NOT replace LeadActivity in Phase 1 — both tables coexist until
 * the Timeline unification in Phase 3. New code writes here; old code still
 * writes to lead_activities for backward compatibility.
 *
 * Event naming convention: '{domain}.{action}' e.g. 'lead.created',
 * 'appointment.booked', 'recall.queued', 'payment.received'.
 *
 * @property int         $id
 * @property int|null    $relationship_id
 * @property string      $subject_type
 * @property int         $subject_id
 * @property string|null $actor_type
 * @property int|null    $actor_id
 * @property string      $event
 * @property string|null $description
 * @property array|null  $metadata
 * @property \Carbon\Carbon $occurred_at
 */
class Activity extends Model
{
    protected $fillable = [
        'relationship_id',
        'subject_type',
        'subject_id',
        'actor_type',
        'actor_id',
        'event',
        'description',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'metadata'    => 'array',
        'occurred_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    /**
     * The thing this event is about (Lead, Patient, Appointment, Invoice, etc.)
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Who caused the event (User model, or null for system/automated actions).
     */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The Relationship this activity belongs to (nullable for system events).
     */
    public function relationship(): BelongsTo
    {
        return $this->belongsTo(Relationship::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    /**
     * Filter activities for a specific relationship.
     */
    public function scopeForRelationship(Builder $query, int $id): Builder
    {
        return $query->where('relationship_id', $id);
    }

    /**
     * Filter activities by event key (exact match or prefix).
     * E.g. ofEvent('lead.created') or ofEvent('lead') for all lead events.
     */
    public function scopeOfEvent(Builder $query, string $event): Builder
    {
        // If the caller passes a domain prefix (no dot suffix), match all events under it
        if (! str_contains($event, '.')) {
            return $query->where('event', 'like', $event . '.%');
        }

        return $query->where('event', $event);
    }

    /**
     * Most recent activities first (by occurred_at).
     */
    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderBy('occurred_at', 'desc');
    }
}
