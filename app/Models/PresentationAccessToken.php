<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresentationAccessToken extends Model
{
    protected $fillable = [
        'presentation_id',
        'token',
        'expires_at',
        'revoked_at',
        'last_viewed_at',
        'view_count',
    ];

    protected $casts = [
        'expires_at'     => 'datetime',
        'revoked_at'     => 'datetime',
        'last_viewed_at' => 'datetime',
        'view_count'     => 'integer',
    ];

    public function presentation(): BelongsTo
    {
        return $this->belongsTo(Presentation::class);
    }

    public function isValid(): bool
    {
        if ($this->revoked_at) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    public function recordView(): void
    {
        $this->increment('view_count');
        $this->update(['last_viewed_at' => now()]);
    }
}
