<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A queued "these two relationships might be the same person" pair, awaiting
 * human review (Phase 1). Never auto-merged.
 *
 * @property int    $relationship_id
 * @property int    $candidate_relationship_id
 * @property string $match_reason
 * @property string $status  pending | merged | dismissed
 */
class DedupCandidate extends Model
{
    protected $table = 'dedup_candidates';

    protected $fillable = [
        'relationship_id',
        'candidate_relationship_id',
        'match_reason',
        'confidence',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'confidence'  => 'integer',
        'reviewed_at' => 'datetime',
    ];
}
