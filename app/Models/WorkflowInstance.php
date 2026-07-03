<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * WorkflowInstance — one RUN of a WorkflowTemplate for one
 * relationship/patient (e.g. "this patient's RCT on tooth 36, currently on
 * the obturation step"). Slice 1: dormant scaffolding, no callers yet — see
 * docs/phase-5/workflow-engine-proposal.md.
 *
 * @property int         $id
 * @property int         $template_id
 * @property int|null    $relationship_id  soft link, no FK (see migration)
 * @property string|null $subject_type     polymorphic — e.g. TreatmentPlan::class
 * @property int|null    $subject_id
 * @property string      $current_step
 * @property string      $status           active|completed|abandoned
 * @property \Carbon\Carbon $started_at
 * @property \Carbon\Carbon|null $completed_at
 * @property array|null  $context
 */
class WorkflowInstance extends Model
{
    protected $fillable = [
        'template_id',
        'relationship_id',
        'subject_type',
        'subject_id',
        'current_step',
        'status',
        'started_at',
        'completed_at',
        'context',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
        'context'      => 'array',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkflowTemplate::class, 'template_id');
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(Relationship::class, 'relationship_id');
    }

    public function stepLogs(): HasMany
    {
        return $this->hasMany(WorkflowStepLog::class)->orderBy('entered_at');
    }

    /** The subject this run tracks (whatever model subject_type points at). */
    public function subject(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
