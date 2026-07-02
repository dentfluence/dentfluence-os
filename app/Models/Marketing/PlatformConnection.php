<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class PlatformConnection extends Model
{
    protected $table = 'mkt_platform_connections';

    protected $fillable = [
        'clinic_id',
        'platform',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'external_account_id',
        'external_account_name',
        'external_account_avatar',
        'meta',
        'status',
        'error_message',
        'last_checked_at',
        'connected_by',
        'created_by',
        'updated_by',
    ];

    /**
     * Hide raw token columns from toArray() / toJson() output.
     */
    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected $casts = [
        'meta'             => 'array',
        'token_expires_at' => 'datetime',
        'last_checked_at'  => 'datetime',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
    ];

    // -----------------------------------------------------------------------
    // Encrypted mutators
    // -----------------------------------------------------------------------

    /** Store access_token encrypted */
    public function setAccessTokenAttribute(?string $value): void
    {
        $this->attributes['access_token'] = $value ? Crypt::encryptString($value) : null;
    }

    /** Decrypt access_token on read */
    public function getAccessTokenAttribute(?string $value): ?string
    {
        if (! $value) return null;
        try {
            return Crypt::decryptString($value);
        } catch (\Exception) {
            return null;
        }
    }

    /** Store refresh_token encrypted */
    public function setRefreshTokenAttribute(?string $value): void
    {
        $this->attributes['refresh_token'] = $value ? Crypt::encryptString($value) : null;
    }

    /** Decrypt refresh_token on read */
    public function getRefreshTokenAttribute(?string $value): ?string
    {
        if (! $value) return null;
        try {
            return Crypt::decryptString($value);
        } catch (\Exception) {
            return null;
        }
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }

    public function isTokenExpired(): bool
    {
        return $this->token_expires_at && $this->token_expires_at->isPast();
    }

    // -----------------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------------

    public function scopeForClinic($query, int $clinicId)
    {
        return $query->where('clinic_id', $clinicId);
    }

    public function scopeConnected($query)
    {
        return $query->where('status', 'connected');
    }
}
