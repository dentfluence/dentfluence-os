<?php

namespace App\Models\Blog;

use App\Models\Marketing\MarketingAsset;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * The dedicated Blog entity (Blog Marketing Hub, Wave 1).
 *
 * `body_json` is the canonical block-JSON content (source of truth):
 *   { "version": 1, "blocks": [ { "id": "...", "type": "...", "data": {...} } ] }
 * `body_html` is a generated cache produced by BlogBlockRenderer — never
 * hand-edited. See App\Services\Blog\BlogBlockSchema for the V1 block types.
 *
 * Identity: `uuid` is the PERMANENT, immutable, canonical reference. All
 * routes/URLs/edit links bind on it (getRouteKeyName), and future cross-module
 * references (PRE, analytics, comments) point at it too. The bigint `id`
 * remains the primary key; same-module child relations (seo/versions/tags/
 * publications) keep their `blog_post_id` FK on that id for join efficiency —
 * that FK is immutable and slug-independent, which is the whole point of the
 * "never key on the slug" rule. Slug is a human/SEO surface only.
 *
 * Loose coupling: the only Marketing dependency is the DAM (MarketingAsset)
 * for the featured image — deliberate, per the masterplan (§3, "no new media
 * table"). No links into mkt_posts / the social pipeline.
 */
class BlogPost extends Model
{
    use SoftDeletes;

    protected $table = 'blog_posts';

    public const STATUSES = ['draft', 'scheduled', 'published', 'archived'];

    protected $fillable = [
        'clinic_id',
        'title',
        'slug',
        'excerpt',
        'body_json',
        'body_html',
        'featured_asset_id',
        'category_id',
        'status',
        'author_id',
        'reading_time',
        'scheduled_at',
        'published_at',
        'created_by',
        'updated_by',
        // NOTE: uuid / slug_locked / first_published_at are deliberately NOT
        // fillable — they are set by the model/service, never mass-assigned
        // from client input.
    ];

    protected $casts = [
        'body_json'          => 'array',
        'reading_time'       => 'integer',
        'slug_locked'        => 'boolean',
        'scheduled_at'       => 'datetime',
        'published_at'       => 'datetime',
        'first_published_at' => 'datetime',
        'deleted_at'         => 'datetime',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

    // -----------------------------------------------------------------------
    // Boot: uuid generation + immutability
    // -----------------------------------------------------------------------

    protected static function booted(): void
    {
        // Generate the permanent uuid on create (keeps the bigint PK intact).
        static::creating(function (BlogPost $post) {
            if (empty($post->uuid)) {
                $post->uuid = (string) Str::uuid();
            }
        });

        // uuid is immutable: silently revert any attempt to change it.
        static::updating(function (BlogPost $post) {
            if ($post->isDirty('uuid')) {
                $post->uuid = $post->getOriginal('uuid');
            }
        });
    }

    /** Bind ALL route-model lookups on the permanent uuid, never id/slug. */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    public function seo(): HasOne
    {
        return $this->hasOne(BlogPostSeo::class, 'blog_post_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'category_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            BlogTag::class,
            'blog_post_tag',
            'blog_post_id',
            'blog_tag_id'
        )->withTimestamps();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(BlogPostVersion::class, 'blog_post_id')->latest('created_at');
    }

    public function publications(): HasMany
    {
        return $this->hasMany(BlogPublication::class, 'blog_post_id');
    }

    public function featuredAsset(): BelongsTo
    {
        return $this->belongsTo(MarketingAsset::class, 'featured_asset_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    /**
     * Has this post ever been live? True once it was published — tracked by
     * the `slug_locked` flag (set at first publish), with published_at as a
     * defensive secondary signal in case the flag was ever missed. A published
     * blog_publications row is the third signal but is checked lazily via the
     * relation to avoid an extra query on every save (see isSlugLocked()).
     */
    public function hasEverBeenPublished(): bool
    {
        return $this->slug_locked === true
            || $this->first_published_at !== null
            || $this->published_at !== null;
    }

    /**
     * Whether the slug is locked against edits. The `slug_locked` column is
     * authoritative; publish timestamps back it up. If the relation is already
     * loaded we also honour any published publication row (Slice 6 ledger).
     */
    public function isSlugLocked(): bool
    {
        if ($this->hasEverBeenPublished()) {
            return true;
        }

        if ($this->relationLoaded('publications')) {
            return $this->publications->contains(fn ($p) => $p->status === 'published');
        }

        return false;
    }

    // -----------------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------------

    public function scopeForClinic($query, int $clinicId)
    {
        return $query->where('clinic_id', $clinicId);
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
