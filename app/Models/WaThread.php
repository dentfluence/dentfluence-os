<?php

namespace App\Models;

use App\Casts\Encrypted;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * WaThread — one WhatsApp conversation with one contact (Phase B 1.2).
 * ----------------------------------------------------------------------------
 * Owns many WaMessage rows. Optionally linked to a Patient and/or a Lead so the
 * unified inbox knows who you're talking to. `last_preview` is encrypted because
 * it mirrors message text (possible PHI).
 */
class WaThread extends Model
{
    protected $fillable = [
        'channel', 'contact_phone', 'contact_name',
        'patient_id', 'lead_id',
        'status', 'last_preview',
        'last_message_at', 'last_inbound_at', 'last_outbound_at', 'last_direction',
        'window_expires_at', 'unread_count', 'assigned_to_id',
    ];

    protected $casts = [
        'last_preview'      => Encrypted::class,
        'last_message_at'   => 'datetime',
        'last_inbound_at'   => 'datetime',
        'last_outbound_at'  => 'datetime',
        'window_expires_at' => 'datetime',
        'unread_count'      => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function messages(): HasMany
    {
        return $this->hasMany(WaMessage::class)->orderBy('id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Is Meta's 24-hour free-text reply window still open? Outside it, only a
     * pre-approved template may be sent (enforced in Chunk 4).
     */
    public function isWindowOpen(): bool
    {
        return $this->window_expires_at !== null && $this->window_expires_at->isFuture();
    }

    /** Best display name we have for the contact. */
    public function getDisplayNameAttribute(): string
    {
        return $this->patient?->name
            ?: $this->lead?->name
            ?: $this->contact_name
            ?: $this->contact_phone;
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeRecent($query)
    {
        return $query->orderByDesc('last_message_at');
    }
}
