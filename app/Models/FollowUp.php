<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class FollowUp extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'patient_id',
        'lead_id',
        // Visit → Next Action (2026-08-14): the visit whose doctor authored
        // this action. Also the idempotency key for re-saving that visit.
        'treatment_visit_id',
        'label',
        'trigger_type',
        'trigger_value',
        'due_date',
        'due_time',
        'channel',
        'priority',
        'status',
        'note',
        'appears_in',
        'auto_created',
        'assigned_to',
        'created_by',
        'completed_at',
        'completed_by',
        'completion_note',
    ];

    protected $casts = [
        'due_date'     => 'date',
        'appears_in'   => 'array',
        'auto_created' => 'boolean',
        'completed_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * The lead this follow-up belongs to (PRM Phase 2b lead-based reminders).
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Display name — patient if linked, else the lead, else 'Unknown'.
     */
    public function subjectName(): string
    {
        return $this->patient?->name ?? $this->lead?->name ?? 'Unknown';
    }

    /**
     * Display phone — patient if linked, else the lead.
     */
    public function subjectPhone(): ?string
    {
        return $this->patient?->phone ?? $this->lead?->phone;
    }

    /**
     * The visit this action was issued from (Visit → Next Action, 2026-08-14).
     * Null for every other follow-up source — leads, manual bookings, the
     * Follow-up Engine's rule-driven rows.
     */
    public function treatmentVisit(): BelongsTo
    {
        return $this->belongsTo(TreatmentVisit::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** The doctor who issued the instruction at the chair. */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(FollowUpNote::class)->latest();
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'pending')
                     ->where('due_date', '<', Carbon::today());
    }

    public function scopeDueToday($query)
    {
        return $query->where('status', 'pending')
                     ->whereDate('due_date', Carbon::today());
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', 'pending')
                     ->where('due_date', '>', Carbon::today());
    }

    /**
     * Only the actions a doctor issued from a Visit Log.
     *
     * Used by the Daily Huddle to show FUTURE-dated work as read-only
     * "upcoming" (so the manager can brief the call team days ahead) without
     * it becoming actionable in the Communication List before its due date.
     * Every existing due-today surface is unaffected — they filter on
     * `due_date <= today` and simply never see these until they mature.
     */
    public function scopeFromVisit($query)
    {
        return $query->whereNotNull('treatment_visit_id')
                     ->where('trigger_type', \App\Services\Clinical\VisitNextActionService::TRIGGER_TYPE);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Returns a channel color for use in the UI.
     */
    public function channelColor(): string
    {
        return match ($this->channel) {
            'whatsapp'     => '#22C55E',
            'clinic_visit' => '#F97316',
            default        => '#6B5BDF', // call
        };
    }

    /**
     * Returns an avatar string from the linked patient's name.
     */
    public function avatarInitials(): string
    {
        $name = $this->patient?->name ?? $this->lead?->name ?? 'UN';
        $parts = explode(' ', trim($name));
        $initials = strtoupper(substr($parts[0], 0, 1));
        if (isset($parts[1])) {
            $initials .= strtoupper(substr($parts[1], 0, 1));
        }
        return $initials;
    }
}
