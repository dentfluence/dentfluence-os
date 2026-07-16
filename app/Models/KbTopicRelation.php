<?php

namespace App\Models;

use App\Models\Concerns\GuardsKnowledgeBankPurity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * KbTopicRelation — typed KB topic graph (frozen §5.1, decision #9).
 * For FUTURE AI retrieval only — NOT navigation, NOT the assembly path.
 * Populated lazily; no traversal engine in V1.
 */
class KbTopicRelation extends Model
{
    use GuardsKnowledgeBankPurity;

    protected $fillable = [
        'from_topic_id', 'to_topic_id', 'relation_type', 'weight',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
    ];

    public function fromTopic(): BelongsTo
    {
        return $this->belongsTo(KbTopic::class, 'from_topic_id');
    }

    public function toTopic(): BelongsTo
    {
        return $this->belongsTo(KbTopic::class, 'to_topic_id');
    }
}
