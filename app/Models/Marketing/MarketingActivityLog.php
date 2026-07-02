<?php

namespace App\Models\Marketing;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MarketingActivityLog extends Model
{
    protected $table = 'mkt_activity_log';

    protected $fillable = [
        'clinic_id',
        'user_id',
        'event',
        'subject_type',
        'subject_id',
        'description',
        'properties',
        'occurred_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'properties'  => 'array',
        'occurred_at' => 'datetime',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Polymorphic subject (Campaign, MarketingPost, Idea, etc.) */
    public function subject(): MorphTo
    {
        return $this->morphTo('subject');
    }

    // -----------------------------------------------------------------------
    // Static helper
    // -----------------------------------------------------------------------

    /**
     * Quick log an event for a clinic.
     *
     * Usage:
     *   MarketingActivityLog::log($clinicId, 'post_published', $post, 'Post "X" published on Instagram', [...]);
     */
    public static function log(
        int $clinicId,
        string $event,
        ?Model $subject,
        string $description,
        array $properties = [],
        ?int $userId = null
    ): static {
        return static::create([
            'clinic_id'    => $clinicId,
            'user_id'      => $userId ?? auth()->id(),
            'event'        => $event,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id'   => $subject?->getKey(),
            'description'  => $description,
            'properties'   => $properties,
            'occurred_at'  => now(),
        ]);
    }

    // -----------------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------------

    public function scopeForClinic($query, int $clinicId)
    {
        return $query->where('clinic_id', $clinicId);
    }

    public function scopeRecent($query, int $limit = 20)
    {
        return $query->orderByDesc('occurred_at')->limit($limit);
    }
}
