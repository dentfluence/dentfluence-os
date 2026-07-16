<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CaseSelection — the mutable "cart" until accept (frozen §5.5). Running
 * estimate is recomputed on the fly, never stored here.
 */
class CaseSelection extends Model
{
    protected $fillable = [
        'patient_journey_id', 'decision_tree_node_id', 'treatment_option_id', 'selected_at',
    ];

    protected $casts = [
        'selected_at' => 'datetime',
    ];

    public function journey(): BelongsTo
    {
        return $this->belongsTo(PatientJourney::class, 'patient_journey_id');
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(DecisionTreeNode::class, 'decision_tree_node_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(TreatmentOption::class, 'treatment_option_id');
    }
}
