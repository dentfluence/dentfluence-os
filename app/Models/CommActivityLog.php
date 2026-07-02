<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CommActivityLog — automatic activity log for Communication List
 *
 * Never written manually — always via CommActivityLog::log()
 * Actions: created | edited | assigned | moved | closed | reminder_created
 */
class CommActivityLog extends Model
{
    protected $table = 'comm_activity_logs';

    protected $fillable = [
        'comm_id',
        'action',
        'description',
        'meta',
        'user_id',
        'user_name',
        'logged_at',
    ];

    protected $casts = [
        'meta'      => 'array',
        'logged_at' => 'datetime',
    ];

    // ── Static Helper ──────────────────────────────────────────────────

    /**
     * Log an activity against a communication record.
     * Called automatically by CommunicationController on every state change.
     */
    public static function log(int $commId, string $action, string $description = '', array $meta = []): void
    {
        static::create([
            'comm_id'     => $commId,
            'action'      => $action,
            'description' => $description,
            'meta'        => $meta ?: null,
            'user_id'     => auth()->id(),
            'user_name'   => auth()->user()?->name ?? 'System',
            'logged_at'   => now(),
        ]);
    }

    // ── Accessors ──────────────────────────────────────────────────────

    public function getActionIconAttribute(): string
    {
        return match ($this->action) {
            'created'          => '✅',
            'edited'           => '✏️',
            'assigned'         => '👤',
            'moved'            => '➡️',
            'closed'           => '🔒',
            'reminder_created' => '🔔',
            'attempt'          => '📞',
            default            => '📋',
        };
    }

    // ── Relationships ──────────────────────────────────────────────────

    public function comm(): BelongsTo
    {
        return $this->belongsTo(CommunicationQueue::class, 'comm_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
