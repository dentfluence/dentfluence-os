<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MarketingAsset extends Model
{
    use SoftDeletes;

    protected $table = 'mkt_assets';

    protected $fillable = [
        'clinic_id',
        'folder_id',
        'campaign_id',
        'name',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'asset_type',
        'alt_text',
        'description',
        'width',
        'height',
        'duration_seconds',
        'dam_asset_id',
        'is_favorite',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'file_size'        => 'integer',
        'width'            => 'integer',
        'height'           => 'integer',
        'duration_seconds' => 'integer',
        'is_favorite'      => 'boolean',
        'deleted_at'       => 'datetime',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
    ];

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    public function folder(): BelongsTo
    {
        return $this->belongsTo(AssetFolder::class, 'folder_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            AssetTag::class,
            'mkt_asset_tag_map',
            'asset_id',
            'tag_id'
        )->withTimestamps();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    public function isImage(): bool
    {
        return $this->asset_type === 'image';
    }

    public function isVideo(): bool
    {
        return $this->asset_type === 'video';
    }

    public function fileSizeForHumans(): string
    {
        if (! $this->file_size) return '—';
        $kb = $this->file_size / 1024;
        return $kb >= 1024
            ? round($kb / 1024, 1) . ' MB'
            : round($kb, 0) . ' KB';
    }

    // -----------------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------------

    public function scopeForClinic($query, int $clinicId)
    {
        return $query->where('clinic_id', $clinicId);
    }

    public function scopeInFolder($query, ?int $folderId)
    {
        return $folderId
            ? $query->where('folder_id', $folderId)
            : $query->whereNull('folder_id');
    }

    public function scopeFavorites($query)
    {
        return $query->where('is_favorite', true);
    }
}
