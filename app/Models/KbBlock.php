<?php

namespace App\Models;

use App\Models\Concerns\GuardsKnowledgeBankPurity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * KbBlock — reusable Knowledge Bank content atom (frozen §5.1).
 * `body` may hold whitelisted {{tokens}} resolved at render (§6/§7). Media by
 * reference only. `depth` reserved — author `standard` in V1.
 */
class KbBlock extends Model
{
    use GuardsKnowledgeBankPurity;

    protected $fillable = [
        'kb_topic_id', 'block_type', 'title', 'body',
        'depth', 'locale', 'sort_order', 'version',
    ];

    public function topic(): BelongsTo
    {
        return $this->belongsTo(KbTopic::class, 'kb_topic_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(KbBlockMedia::class)->orderBy('sort_order');
    }

    public function scopeLocale($query, string $locale)
    {
        return $query->where('locale', $locale);
    }
}
