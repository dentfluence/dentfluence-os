<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Audit + reversibility record for a relationship merge (Phase 1).
 *
 * @property int      $surviving_relationship_id
 * @property int      $merged_relationship_id
 * @property array    $reassignments  { table: [row ids moved] }
 * @property array    $snapshot       merged relationship's attributes
 */
class RelationshipMerge extends Model
{
    protected $table = 'relationship_merges';

    protected $fillable = [
        'surviving_relationship_id',
        'merged_relationship_id',
        'reason',
        'reassignments',
        'snapshot',
        'merged_by',
        'undone_at',
    ];

    protected $casts = [
        'reassignments' => 'array',
        'snapshot'      => 'array',
        'undone_at'     => 'datetime',
    ];
}
