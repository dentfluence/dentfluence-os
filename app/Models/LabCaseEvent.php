<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LabCaseEvent — one entry in a case's append-only timeline / audit trail.
 *
 * IMMUTABLE: rows are created once and never updated or deleted.
 * The model enforces this — update() and delete() throw.
 */
class LabCaseEvent extends Model
{
    /** Append-only: no updated_at column */
    public const UPDATED_AT = null;

    protected $fillable = [
        'lab_case_id', 'event_type', 'from_status', 'to_status',
        'description', 'meta', 'user_id',
    ];

    protected $casts = [
        'meta'       => 'array',
        'created_at' => 'datetime',
    ];

    // ── Immutability guards ──────────────────────────────────────────────

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException('Lab case timeline events are immutable.');
        });

        static::deleting(function () {
            throw new \RuntimeException('Lab case timeline events cannot be deleted.');
        });
    }

    // ── Relationships ────────────────────────────────────────────────────

    public function labCase(): BelongsTo
    {
        return $this->belongsTo(LabCase::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Alias for user() — used in eager loads like events.createdBy */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // ── Presentation ─────────────────────────────────────────────────────

    /** Icon hint for the timeline UI */
    public function icon(): string
    {
        return match ($this->event_type) {
            'created'        => 'plus',
            'status_changed' => 'arrow-right',
            'attachment_added', 'attachment_removed' => 'paperclip',
            'expense_linked' => 'currency',
            'printed'        => 'printer',
            'whatsapp_sent'  => 'chat',
            'duplicated'     => 'copy',
            'archived'       => 'archive',
            'restored'       => 'refresh',
            default          => 'pencil',
        };
    }
}
