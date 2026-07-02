<?php

namespace App\Services\Assistant;

use App\Services\Assistant\Tools\AddPatientNoteTool;
use App\Services\Assistant\Tools\AssistantTool;
use App\Services\Assistant\Tools\BookAppointmentTool;
use App\Services\Assistant\Tools\CreateTaskTool;
use App\Services\Assistant\Tools\DailyHuddleTool;
use App\Services\Assistant\Tools\FindPatientTool;
use App\Services\Assistant\Tools\KpiReportTool;
use App\Services\Assistant\Tools\ListTasksTool;
use App\Services\Assistant\Tools\MembershipTool;
use App\Services\Assistant\Tools\PatientBalanceTool;
use App\Services\Assistant\Tools\PatientSummaryTool;
use App\Services\Assistant\Tools\PendingTreatmentsTool;
use App\Services\Assistant\Tools\TodayScheduleTool;
use App\Services\Assistant\Tools\UpdatePatientContactTool;
use App\Services\Assistant\Tools\VisitHistoryTool;

/**
 * ToolRegistry — the catalogue of capabilities the assistant is allowed to use.
 * ----------------------------------------------------------------------------
 * Add a new tool here and it's instantly available to the model. Each phase
 * registers more (read tools now; write/agentic tools in Phase D).
 */
class ToolRegistry
{
    /** @var array<string, AssistantTool> */
    protected array $tools = [];

    public function __construct()
    {
        // ── Phase A2: starter read-only tools ────────────────────────────────
        $this->register(new FindPatientTool());
        $this->register(new TodayScheduleTool());

        // ── Phase B: deeper read tools ───────────────────────────────────────
        $this->register(new PatientSummaryTool());
        $this->register(new PendingTreatmentsTool());
        $this->register(new PatientBalanceTool());
        $this->register(new VisitHistoryTool());
        $this->register(new ListTasksTool());
        $this->register(new KpiReportTool());
        $this->register(new MembershipTool());

        // ── Daily huddle (morning briefing) ──────────────────────────────────
        $this->register(new DailyHuddleTool());

        // ── Phase D: write actions (low-risk, auto-execute) ──────────────────
        $this->register(new CreateTaskTool());

        // ── Phase D: confirm-required write actions (clinical/financial) ─────
        $this->register(new AddPatientNoteTool());

        // ── Phase D3: booking (confirm-required) ─────────────────────────────
        $this->register(new BookAppointmentTool());

        // ── Safe confirmed data-entry ────────────────────────────────────────
        $this->register(new UpdatePatientContactTool());
    }

    public function register(AssistantTool $tool): void
    {
        $this->tools[$tool->name()] = $tool;
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    public function get(string $name): ?AssistantTool
    {
        return $this->tools[$name] ?? null;
    }

    /** @return array<string, AssistantTool> */
    public function all(): array
    {
        return $this->tools;
    }

    /**
     * Tool definitions in the shape Ollama expects in the "tools" field.
     */
    public function definitions(): array
    {
        return array_values(array_map(fn (AssistantTool $t) => [
            'type'     => 'function',
            'function' => [
                'name'        => $t->name(),
                'description' => $t->description(),
                'parameters'  => $t->parameters(),
            ],
        ], $this->tools));
    }
}
