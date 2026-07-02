<?php

namespace App\Models;

use App\Casts\Encrypted;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WaMessage — a single WhatsApp message, inbound or outbound (Phase B 1.2).
 * ----------------------------------------------------------------------------
 * The text `body` is encrypted at rest (patient messages may be PHI). `payload`
 * holds the raw provider JSON for audit/debugging.
 */
class WaMessage extends Model
{
    public const INBOUND  = 'inbound';
    public const OUTBOUND = 'outbound';

    // Outbound lifecycle statuses (Meta delivery callbacks update these).
    public const STATUS_QUEUED    = 'queued';
    public const STATUS_SENT      = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_READ      = 'read';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_RECEIVED  = 'received'; // inbound

    protected $fillable = [
        'wa_thread_id', 'channel', 'direction', 'wa_message_id',
        'from_phone', 'to_phone', 'type', 'body',
        'template_name', 'template_payload',
        'media_url', 'media_mime',
        'status', 'error', 'sent_by_id', 'payload',
    ];

    protected $casts = [
        'body'             => Encrypted::class,
        'template_payload' => 'array',
        'payload'          => 'array',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function thread(): BelongsTo
    {
        return $this->belongsTo(WaThread::class, 'wa_thread_id');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_id');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function isInbound(): bool
    {
        return $this->direction === self::INBOUND;
    }

    public function isOutbound(): bool
    {
        return $this->direction === self::OUTBOUND;
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeInbound($query)
    {
        return $query->where('direction', self::INBOUND);
    }

    public function scopeOutbound($query)
    {
        return $query->where('direction', self::OUTBOUND);
    }
}
