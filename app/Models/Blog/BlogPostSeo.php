<?php

namespace App\Models\Blog;

use App\Models\Marketing\MarketingAsset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 1:1 SEO workspace for a BlogPost (table enforces unique blog_post_id).
 * Clinic scoping is inherited through the parent post.
 */
class BlogPostSeo extends Model
{
    protected $table = 'blog_post_seo';

    protected $fillable = [
        'blog_post_id',
        'focus_keyword',
        'secondary_keywords',
        'meta_title',
        'meta_description',
        'canonical_url',
        'og_title',
        'og_description',
        'og_image_asset_id',
        'seo_score',
        'readability_score',
        'noindex',
    ];

    protected $casts = [
        'secondary_keywords' => 'array',
        'seo_score'          => 'integer',
        'readability_score'  => 'integer',
        'noindex'            => 'boolean',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }

    public function ogImageAsset(): BelongsTo
    {
        return $this->belongsTo(MarketingAsset::class, 'og_image_asset_id');
    }
}
