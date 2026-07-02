<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetFolder extends Model
{
    use SoftDeletes;

    protected $table = 'mkt_asset_folders';

    protected $fillable = [
        'clinic_id',
        'name',
        'description',
        'parent_id',
        'color',
        'icon',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'deleted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    /** Direct child folders */
    public function children(): HasMany
    {
        return $this->hasMany(AssetFolder::class, 'parent_id')
                    ->orderBy('sort_order')
                    ->orderBy('name');
    }

    /** Parent folder (null if root) */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(AssetFolder::class, 'parent_id');
    }

    /** Assets directly inside this folder */
    public function assets(): HasMany
    {
        return $this->hasMany(MarketingAsset::class, 'folder_id');
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    public function isRoot(): bool
    {
        return is_null($this->parent_id);
    }

    public function hasAssets(): bool
    {
        return $this->assets()->exists();
    }

    // -----------------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------------

    public function scopeForClinic($query, int $clinicId)
    {
        return $query->where('clinic_id', $clinicId);
    }

    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }
}
