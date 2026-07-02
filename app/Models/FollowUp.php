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

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
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
