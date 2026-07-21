<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit + reversibility record for a PATIENT merge (Phase 1).
 *
 * Orchestration record: the patient merge re-parents patient_id children and
 * runs the special money/ABHA/membership rules itself, then delegates the
 * relationship_id cascade to Relationship\MergeService — whose RelationshipMerge
 * record is referenced here via relationship_merge_id. Un-merge spans both.
 *
 * @property int         $surviving_patient_id
 * @property int         $merged_patient_id
 * @property int|null    $relationship_merge_id
 * @property array       $field_choices    winning value per conflicting field
 * @property array       $reassignments    { table: [row ids moved] }
 * @property array|null  $wallet_transfer  wallet sum/transfer detail
 * @property string|null $retired_patient_id
 * @property array       $snapshot         merged patient's attributes at merge time
 */
class PatientMerge extends Model
{
    protected $table = 'patient_merges';

    protected $fillable = [
        'surviving_patient_id',
        'merged_patient_id',
        'relationship_merge_id',
        'reason',
        'field_choices',
        'reassignments',
        'wallet_transfer',
        'retired_patient_id',
        'snapshot',
        'reversal',
        'merged_by',
        'undone_at',
    ];

    protected $casts = [
        'field_choices'   => 'array',
        'reassignments'   => 'array',
        'wallet_transfer' => 'array',
        'snapshot'        => 'array',
        'reversal'        => 'array',
        'undone_at'       => 'datetime',
    ];

    public function surviving(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'surviving_patient_id')->withTrashed();
    }

    public function merged(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'merged_patient_id')->withTrashed();
    }

    public function relationshipMerge(): BelongsTo
    {
        return $this->belongsTo(RelationshipMerge::class, 'relationship_merge_id');
    }

    public function mergedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merged_by');
    }
}
