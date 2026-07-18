<?php

namespace App\Models\Blog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Blog tag taxonomy (m:n with BlogPost). `wp_term_id` is the mapped
 * WordPress tag once synced by the publish adapter (null until then).
 */
class BlogTag extends Model
{
    protected $table = 'blog_tags';

    protected $fillable = [
        'clinic_id',
        'name',
        'slug',
        'wp_term_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(
            BlogPost::class,
            'blog_post_tag',
            'blog_tag_id',
            'blog_post_id'
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
