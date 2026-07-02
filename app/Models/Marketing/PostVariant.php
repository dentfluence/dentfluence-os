<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostVariant extends Model
{
    protected $table = 'mkt_post_variants';

    protected $fillable = [
        'post_id',
        'platform',
        'caption',
        'platform_specific_meta',
        'status',
        'external_id',
        'external_url',
        'publish_error',
        'published_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'platform_specific_meta' => 'array',
        'published_at'           => 'datetime',
        'created_at'             => 'datetime',
        'updated_at'             => 'datetime',
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

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
