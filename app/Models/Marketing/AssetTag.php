<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AssetTag extends Model
{
    protected $table = 'mkt_asset_tags';

    protected $fillable = [
        'clinic_id',
        'name',
        'color',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(
            MarketingAsset::class,
            'mkt_asset_tag_map',
            'tag_id',
            'asset_id'
        )->withTimestamps();
    }

    // -----------------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------------

    public function scopeForClinic($query, int $clinicId)
    {
        return $query->where('clinic_id', $clinicId);
    }
}
