<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostMedia extends Model
{
    protected $table = 'mkt_post_media';

    protected $fillable = [
        'post_id',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'media_type',
        'alt_text',
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

    public function post(): BelongsTo
    {
        return $this->belongsTo(MarketingPost::class, 'post_id');
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    public function isImage(): bool
    {
        return $this->media_type === 'image';
    }

    public function isVideo(): bool
    {
        return $this->media_type === 'video';
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
