<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WorkflowStepLog — audit trail row for one step a WorkflowInstance passed
 * through. `exited_at` is null while the step is current; advance() stamps
 * it the moment the run moves past it. Slice 1: dormant scaffolding, no
 * callers yet.
 *
 * @property int    $id
 * @property int    $workflow_instance_id
 * @property string $step
 * @property \Carbon\Carbon $entered_at
 * @property \Carbon\Carbon|null $exited_at
 * @property int|null $actor_id
 * @property string|null $notes
 */
class WorkflowStepLog extends Model
{
    // Explicit — Eloquent's default pluralization would guess
    // "workflow_step_logs" (pluralizing "log"), but the migration created
    // "workflow_step_log" (singular, matching this codebase's existing
    // "automation_shadow_log" naming convention).
    protected $table = 'workflow_step_log';

    protected $fillable = [
        'workflow_instance_id',
        'step',
        'entered_at',
        'exited_at',
        'actor_id',
        'notes',
    ];

    protected $casts = [
        'entered_at' => 'datetime',
        'exited_at'  => 'datetime',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
