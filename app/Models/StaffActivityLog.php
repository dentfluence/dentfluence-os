<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffActivityLog extends Model
{
    public $timestamps = false; // only created_at, managed manually

    protected $fillable = [
        'user_id',
        'performed_by',
        'action',
        'old_value',
        'new_value',
        'note',
        'ip_address',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    // ── Relations ────────────────────────────────────────────────────────────

    /** The staff member who was affected */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** The admin who performed the action */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Write a log entry.
     */
    public static function record(
        int    $userId,
        string $action,
        ?string $oldValue  = null,
        ?string $newValue  = null,
        ?string $note      = null,
    ): self {
        return self::create([
            'user_id'      => $userId,
            'performed_by' => auth()->id(),
            'action'       => $action,
            'old_value'    => $oldValue,
            'new_value'    => $newValue,
            'note'         => $note,
            'ip_address'   => request()->ip(),
        ]);
    }

    /** Human-readable label for the action */
    public function actionLabel(): string
    {
        return match ($this->action) {
            'activated'       => 'Activated',
            'deactivated'     => 'Deactivated',
            'role_changed'    => 'Role Changed',
            'profile_updated' => 'Profile Updated',
            default           => ucfirst($this->action),
        };
    }

    /** Badge colour for the action */
    public function actionColor(): string
    {
        return match ($this->action) {
            'activated'       => '#1a7a45',
            'deactivated'     => '#c0392b',
            'role_changed'    => '#1558b0',
            'profile_updated' => '#a05c00',
            default           => '#6a0f70',
        };
    }
}
