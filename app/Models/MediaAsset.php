<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MediaAsset — Case Acceptance Engine Media Library (frozen §5.2).
 * Scope split: global stock = Dentfluence; clinic captures = PHI (require a
 * consent_ref). One row = one file in V1.
 */
class MediaAsset extends Model
{
    protected $fillable = [
        'scope', 'media_type', 'path', 'mime', 'locale',
        'variant_of', 'consent_ref', 'uploaded_by',
    ];

    public function variantParent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'variant_of');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function blockLinks(): HasMany
    {
        return $this->hasMany(KbBlockMedia::class);
    }

    public function scopeGlobal($query)
    {
        return $query->where('scope', 'global');
    }

    public function scopeClinic($query)
    {
        return $query->where('scope', 'clinic');
    }
}
