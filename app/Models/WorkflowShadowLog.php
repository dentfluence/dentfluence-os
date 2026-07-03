<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WorkflowShadowLog — one parity-check row from the Slice 2 shadow-run.
 * Purely observational, read by the Slice 4 parity report only. See
 * App\Services\Workflow\WorkflowShadowRunner.
 *
 * @property int         $id
 * @property int|null    $workflow_instance_id
 * @property int|null    $treatment_visit_id
 * @property int|null    $patient_id
 * @property string      $template_key
 * @property string|null $doctor_stage
 * @property string      $action   started|noop|advanced|resynced|diverged|error
 * @property bool        $agreed
 * @property string|null $notes
 */
class WorkflowShadowLog extends Model
{
    // Explicit — Eloquent's default pluralization would guess
    // "workflow_shadow_logs" (pluralizing "log"), but the migration created
    // "workflow_shadow_log" (singular, matching this codebase's existing
    // "automation_shadow_log" naming convention).
    protected $table = 'workflow_shadow_log';

    protected $fillable = [
        'workflow_instance_id',
        'treatment_visit_id',
        'patient_id',
        'template_key',
        'doctor_stage',
        'action',
        'agreed',
        'notes',
    ];

    protected $casts = [
        'agreed' => 'boolean',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }
}
