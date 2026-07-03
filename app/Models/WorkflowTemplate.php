<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * WorkflowTemplate — the DEFINITION of a linear multi-step sequence
 * (e.g. "RCT staging"). See docs/phase-5/workflow-engine-proposal.md for
 * the full design. Slice 1: dormant scaffolding, no callers yet.
 *
 * @property int    $id
 * @property string $key      machine key, e.g. "rct_staging"
 * @property string $name     human label, e.g. "RCT Staging"
 * @property int    $version
 * @property array  $steps    ordered [{key,label,min_gap_days_from_previous}]
 * @property bool   $active
 */
class WorkflowTemplate extends Model
{
    protected $fillable = [
        'key',
        'name',
        'version',
        'steps',
        'active',
    ];

    protected $casts = [
        'steps'   => 'array',
        'active'  => 'boolean',
        'version' => 'integer',
    ];

    public function instances(): HasMany
    {
        return $this->hasMany(WorkflowInstance::class, 'template_id');
    }

    /** Find a step definition by its key, or null if not on this template. */
    public function step(string $key): ?array
    {
        foreach ($this->steps as $step) {
            if (($step['key'] ?? null) === $key) {
                return $step;
            }
        }

        return null;
    }

    /** The first step's key — where every new instance of this template starts. */
    public function firstStepKey(): ?string
    {
        return $this->steps[0]['key'] ?? null;
    }

    /** The step key that comes after $key, or null if $key is the last step. */
    public function nextStepKey(string $key): ?string
    {
        $keys = array_column($this->steps, 'key');
        $pos  = array_search($key, $keys, true);

        if ($pos === false || !isset($keys[$pos + 1])) {
            return null;
        }

        return $keys[$pos + 1];
    }

    /** True if $key is the last step in the sequence. */
    public function isLastStep(string $key): bool
    {
        return $this->nextStepKey($key) === null && $this->step($key) !== null;
    }
}
