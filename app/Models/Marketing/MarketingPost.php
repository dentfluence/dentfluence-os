<?php

namespace App\Models\Marketing;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingPost extends Model
{
    use SoftDeletes;

    protected $table = 'mkt_posts';

    protected $fillable = [
        'clinic_id',
        'campaign_id',
        'title',
        'caption',
        'content_type',
        'platforms',
        'hashtags',
        'cta_type',
        'cta_text',
        'cta_url',
        'ai_score',
        'ai_score_notes',
        'status',
        'rejection_reason',
        'assignee_id',
        'festival_date_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'platforms'      => 'array',
        'hashtags'       => 'array',
        'ai_score_notes' => 'array',
        'ai_score'       => 'integer',
        'deleted_at'     => 'datetime',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function festivalDate(): BelongsTo
    {
        return $this->belongsTo(FestivalDate::class, 'festival_date_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(PostVariant::class, 'post_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(PostMedia::class, 'post_id')->orderBy('sort_order');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(PostSchedule::class, 'post_id');
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    // -----------------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------------

    public function scopeForClinic($query, int $clinicId)
    {
        return $query->where('clinic_id', $clinicId);
    }
}
