<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DeviceToken — a phone that can receive FCM pushes for a user (N-5).
 */
class DeviceToken extends Model
{
    protected $fillable = [
        'user_id', 'token', 'platform', 'device_name', 'app_version',
        'last_seen_at', 'invalidated_at',
    ];

    protected $casts = [
        'last_seen_at'   => 'datetime',
        'invalidated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeLive($query)
    {
        return $query->whereNull('invalidated_at');
    }

    /**
     * Register (or move) a token for a user. A token belongs to exactly one
     * user at a time — the last login on that phone wins.
     */
    public static function register(int $userId, string $token, array $meta = []): static
    {
        return static::updateOrCreate(
            ['token' => $token],
            [
                'user_id'        => $userId,
                'platform'       => $meta['platform'] ?? 'android',
                'device_name'    => $meta['device_name'] ?? null,
                'app_version'    => $meta['app_version'] ?? null,
                'last_seen_at'   => now(),
                'invalidated_at' => null,
            ]
        );
    }
}
