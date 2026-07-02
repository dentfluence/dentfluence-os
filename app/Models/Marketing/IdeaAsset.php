<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdeaAsset extends Model
{
    protected $table = 'mkt_idea_assets';

    protected $fillable = [
        'idea_id',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'asset_type',
        'caption',
        'sort_order',
        'dam_asset_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'file_size'  => 'integer',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    public function idea(): BelongsTo
    {
        return $this->belongsTo(Idea::class, 'idea_id');
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    public function isImage(): bool
    {
        return $this->asset_type === 'image';
    }

    public function fileSizeForHumans(): string
    {
        if (! $this->file_size) return '—';
        $kb = $this->file_size / 1024;
        return $kb >= 1024
            ? round($kb / 1024, 1) . ' MB'
            : round($kb, 0) . ' KB';
    }
}
