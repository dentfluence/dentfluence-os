<?php

namespace App\Models\Blog;

use App\Models\Marketing\PlatformConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-website publish ledger row: one per (post × target). Written by the
 * WebsitePublishAdapter layer (Wave 1 Slice 6) on every publish/update/
 * delete so status is always honest and retryable — never a fake
 * "published" like the legacy social pipeline.
 */
class BlogPublication extends Model
{
    protected $table = 'blog_publications';

    public const TARGET_DENTFLUENCE_STATIC = 'dentfluence_static';
    public const TARGET_WORDPRESS          = 'wordpress';
    public const TARGET_STANDALONE         = 'standalone';

    public const STATUSES = ['pending', 'publishing', 'published', 'failed', 'deleted'];

    protected $fillable = [
        'blog_post_id',
        'target_type',
        'platform_connection_id',
        'external_id',
        'external_url',
        'status',
        'last_synced_at',
        'error',
        'retry_count',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'retry_count'    => 'integer',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(PlatformConnection::class, 'platform_connection_id');
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }
}
