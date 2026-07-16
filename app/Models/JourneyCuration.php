<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * JourneyCuration — per-node doctor curation (frozen §5.4). Relational (not
 * JSON) for analytics. Immutable once the journey is sent.
 */
class JourneyCuration extends Model
{
    protected $fillable = [
        'patient_journey_id', 'decision_tree_node_id',
        'visible', 'is_recommended', 'sort_order',
    ];

    protected $casts = [
        'visible'        => 'boolean',
        'is_recommended' => 'boolean',
        'sort_order'     => 'integer',
    ];

    public function journey(): BelongsTo
    {
        return $this->belongsTo(PatientJourney::class, 'patient_journey_id');
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(DecisionTreeNode::class, 'decision_tree_node_id');
    }
}
