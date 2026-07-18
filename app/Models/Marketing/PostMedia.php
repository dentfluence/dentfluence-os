<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

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
    // Accessors
    // -----------------------------------------------------------------------

    /**
     * Public URL for this media file. ProcessScheduledPost reads
     * $media->url for Instagram/Facebook/Google Business attachments, but no
     * such column or accessor existed — it was always null, so images were
     * silently dropped from every publish. file_path may hold a disk-relative
     * path or a full URL; handle both.
     */
    public function getUrlAttribute(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        if (str_starts_with($this->file_path, 'http://') || str_starts_with($this->file_path, 'https://')) {
            return $this->file_path;
        }

        return Storage::disk('public')->url(ltrim($this->file_path, '/'));
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
