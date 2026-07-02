<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppNotification extends Model
{
    protected $table = 'app_notifications';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'action_url',
        'action_label',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /**
     * Notifications visible to a given user (their own + broadcasts).
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)->orWhereNull('user_id');
        });
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Create a notification for a specific user (or broadcast if $userId = null).
     */
    public static function notify(
        ?int   $userId,
        string $type,
        string $title,
        string $message   = '',
        string $actionUrl = '',
        string $actionLabel = ''
    ): static {
        return static::create([
            'user_id'      => $userId,
            'type'         => $type,
            'title'        => $title,
            'message'      => $message ?: null,
            'action_url'   => $actionUrl ?: null,
            'action_label' => $actionLabel ?: null,
            'is_read'      => false,
        ]);
    }

    /**
     * Mark this notification as read.
     */
    public function markRead(): void
    {
        if (!$this->is_read) {
            $this->update(['is_read' => true, 'read_at' => now()]);
        }
    }

    /**
     * Icon SVG path(s) by type — used in the blade view.
     */
    public function getIconAttribute(): string
    {
        return match ($this->type) {
            'appointment'    => '<rect x="3" y="4" width="18" height="18" rx="0"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
            'lab'            => '<path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2v-4M9 21H5a2 2 0 0 1-2-2v-4m0 0h18"/>',
            'inventory'      => '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>',
            'payment'        => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
            'task_assigned'  => '<polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
            'task_reminder'  => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
            'shift_start'    => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
            'shift_end'      => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
            default          => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
        };
    }

    /**
     * Dot color by type.
     */
    public function getColorAttribute(): string
    {
        return match ($this->type) {
            'appointment'   => '#6a0f70',
            'lab'           => '#0070b0',
            'inventory'     => '#a05c00',
            'payment'       => '#b52020',
            'task_assigned' => '#6a0f70',
            'task_reminder' => '#a05c00',
            'shift_start'   => '#1a7a45',
            'shift_end'     => '#1a5ea8',
            default         => '#5a5a7a',
        };
    }
}
