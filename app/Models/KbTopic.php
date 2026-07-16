<?php

namespace App\Models;

use App\Models\Concerns\GuardsKnowledgeBankPurity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * KbTopic — Knowledge Bank topic (frozen §5.1). Global, versioned education.
 * Never carries prices/brands/clinic_id/patient data (GuardsKnowledgeBankPurity).
 */
class KbTopic extends Model
{
    use GuardsKnowledgeBankPurity;

    protected $fillable = [
        'content_uuid', 'slug', 'type', 'title', 'version', 'status', 'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $topic): void {
            if (empty($topic->content_uuid)) {
                $topic->content_uuid = (string) Str::uuid();
            }
        });
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(KbBlock::class)->orderBy('sort_order');
    }

    public function relationsFrom(): HasMany
    {
        return $this->hasMany(KbTopicRelation::class, 'from_topic_id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }
}
