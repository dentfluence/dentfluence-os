<?php

namespace App\Services\ClinicalLibrary;

use App\Models\DocumentationProtocol;
use App\Models\DocumentationProtocolStep;
use App\Models\ClinicalFile;
use Illuminate\Support\Collection;

/**
 * ProtocolService — Phase 11
 *
 * Given a procedure name (e.g. "Root Canal"), returns the matching
 * DocumentationProtocol and its steps, and can compute completion
 * status for a given visit.
 *
 * Usage:
 *   $service = app(ProtocolService::class);
 *   $steps   = $service->getStepsForProcedure('Root Canal');
 *   $status  = $service->completionForVisit($visitId, 'Root Canal');
 */
class ProtocolService
{
    /**
     * Find the best-matching active protocol for a procedure string.
     * Matching is case-insensitive and partial (LIKE %procedure%).
     */
    public function getProtocolForProcedure(string $procedure): ?DocumentationProtocol
    {
        if (blank($procedure)) {
            return null;
        }

        // Exact match first (case-insensitive)
        $protocol = DocumentationProtocol::active()
            ->whereRaw('LOWER(procedure_type) = ?', [strtolower(trim($procedure))])
            ->with('steps')
            ->first();

        if ($protocol) {
            return $protocol;
        }

        // Partial match — e.g. "Root Canal Treatment" matches "Root Canal"
        return DocumentationProtocol::active()
            ->where(function ($q) use ($procedure) {
                $q->where('procedure_type', 'like', '%' . $procedure . '%')
                  ->orWhere('name', 'like', '%' . $procedure . '%');
            })
            ->with('steps')
            ->orderBy('sort_order')
            ->first();
    }

    /**
     * Returns steps as a plain array ready for JSON encoding.
     * Each step includes: id, name, description, file_type, stage,
     * file_type_label, stage_label, is_required, sort_order.
     */
    public function getStepsForProcedure(string $procedure): array
    {
        $protocol = $this->getProtocolForProcedure($procedure);

        if (!$protocol) {
            return [];
        }

        return $protocol->steps->map(fn(DocumentationProtocolStep $step) => [
            'id'              => $step->id,
            'name'            => $step->name,
            'description'     => $step->description,
            'file_type'       => $step->file_type,
            'file_type_label' => $step->file_type_label,
            'stage'           => $step->stage,
            'stage_label'     => $step->stage_label,
            'is_required'     => $step->is_required,
            'sort_order'      => $step->sort_order,
        ])->values()->all();
    }

    /**
     * Compute completion status for a visit against its procedure's protocol.
     *
     * Returns an array:
     *   total      — total steps in protocol
     *   completed  — steps fulfilled (clinical_files rows referencing protocol_step_id)
     *   required   — required steps only count
     *   req_done   — required steps completed
     *   percent    — completion percentage (0–100)
     *   steps      — step-level detail with 'fulfilled' bool
     */
    public function completionForVisit(int $visitId, string $procedure): array
    {
        $protocol = $this->getProtocolForProcedure($procedure);

        if (!$protocol) {
            return ['total' => 0, 'completed' => 0, 'required' => 0, 'req_done' => 0, 'percent' => 0, 'steps' => []];
        }

        // IDs of protocol steps fulfilled for this visit
        $fulfilledStepIds = ClinicalFile::where('visit_id', $visitId)
            ->whereNotNull('protocol_step_id')
            ->pluck('protocol_step_id')
            ->unique()
            ->all();

        $steps     = $protocol->steps;
        $total     = $steps->count();
        $completed = $steps->filter(fn($s) => in_array($s->id, $fulfilledStepIds))->count();
        $required  = $steps->where('is_required', true)->count();
        $reqDone   = $steps->where('is_required', true)
                           ->filter(fn($s) => in_array($s->id, $fulfilledStepIds))
                           ->count();

        return [
            'protocol_id'   => $protocol->id,
            'protocol_name' => $protocol->name,
            'total'         => $total,
            'completed'     => $completed,
            'required'      => $required,
            'req_done'      => $reqDone,
            'percent'       => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
            'steps'         => $steps->map(fn($s) => [
                'id'        => $s->id,
                'name'      => $s->name,
                'file_type' => $s->file_type,
                'stage'     => $s->stage,
                'is_required' => $s->is_required,
                'fulfilled' => in_array($s->id, $fulfilledStepIds),
            ])->values()->all(),
        ];
    }
}
