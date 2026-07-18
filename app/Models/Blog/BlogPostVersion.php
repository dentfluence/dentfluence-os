<?php

namespace App\Models\Blog;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only history row (autosave / manual / publish snapshots).
 *
 * `snapshot` is the full state at that moment — body_json + seo fields +
 * post meta — so restoring a version never reassembles from other tables.
 * The table has created_at only; UPDATED_AT is disabled below so Eloquent
 * doesn't try to write a column that doesn't exist.
 */
class BlogPostVersion extends Model
{
    protected $table = 'blog_post_versions';

    /** Append-only: no updated_at column. */
    public const UPDATED_AT = null;

    public const LABEL_AUTOSAVE = 'autosave';
    public const LABEL_MANUAL   = 'manual';
    public const LABEL_PUBLISH  = 'publish';

    protected $fillable = [
        'blog_post_id',
        'snapshot',
        'editor_id',
        'label',
    ];

    protected $casts = [
        'snapshot'   => 'array',
        'created_at' => 'datetime',
    ];

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'editor_id');
    }
}
