<?php

declare(strict_types=1);

namespace App\Modules\Huddle\Services;

use App\Modules\Huddle\Repositories\HuddleBoardRepository;
use App\Modules\Huddle\Repositories\HuddleCardRepository;
use App\Modules\Huddle\Models\HuddleBoard;
use App\Modules\Huddle\Transformers\AppointmentToCardTransformer;
use App\Modules\Huddle\Transformers\TaskToCardTransformer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HuddleAggregationService
{
    public function __construct(
        private readonly HuddleBoardRepository     $boardRepo,
        private readonly HuddleCardRepository      $cardRepo,
        private readonly AppointmentToCardTransformer $appointmentTransformer,
        private readonly TaskToCardTransformer        $taskTransformer,
    ) {}

    // -------------------------------------------------------------------------
    // MAIN ENTRY POINT
    // Called by HuddleController@index (and later by the realtime sync job).
    // Returns the fully assembled board payload for the frontend.
    // -------------------------------------------------------------------------

    /**
     * Build (or refresh) the huddle board for the authenticated user.
     *
     * @param  int    $branchId  from auth()->user()->branch_id
     * @param  string $role      from auth()->user()->role
     * @return array             shape consumed by HuddleBoardResource
     */
    public function buildBoardForUser(int $branchId, string $role): array
    {
        // 1. Get or create today's board record
        $board = $this->boardRepo->findOrCreateForToday($branchId, $role);

        // 2. Sync cards from live source data (idempotent)
        $this->syncTodayAppointments($board, $branchId);
        $this->syncTodayTasks($board, $branchId, $role);

        // 3. Load all cards grouped by column for the response
        $cards = $this->cardRepo->getByBoard($board->id);

        return [
            'board' => $board,
            'stats' => $this->computeStats($board->id, $branchId),
            'columns' => $this->groupCardsByColumn($cards),
        ];
    }

    // -------------------------------------------------------------------------
    // APPOINTMENT SYNC
    // Reads from appointments + patients + treatment_types + patient_alerts.
    // Never writes to those tables. Creates/updates huddle_cards only.
    // -------------------------------------------------------------------------

    /**
     * Pull today's appointments for the branch and ensure each has a card.
     */
    private function syncTodayAppointments(HuddleBoard $board, int $branchId): void
    {
        $appointments = $this->fetchTodayAppointments($branchId);

        foreach ($appointments as $appointment) {
            $cardData = $this->appointmentTransformer->transform($appointment);

            $this->cardRepo->firstOrCreateFromSource(
                boardId:    $board->id,
                sourceType: 'appointment',
                sourceId:   $appointment->id,
                defaults:   array_merge($cardData, [
                    'huddle_board_id' => $board->id,
                    'column'          => 'today_flow',
                    'position'        => $appointment->position ?? 0,
                ])
            );
        }
    }

    /**
     * Raw query for today's appointments.
     * Joins patients, treatment_types, and patient_alerts so the transformer
     * gets everything it needs in one pass (no N+1).
     *
     * NOTE: We only READ here. branch_id scoping is applied.
     */
    private function fetchTodayAppointments(int $branchId): Collection
    {
        return collect(
            DB::table('appointments')
                ->join('patients', 'patients.id', '=', 'appointments.patient_id')
                ->leftJoin('users as doctors', 'doctors.id', '=', 'appointments.doctor_id')
                ->leftJoin('users as assistants', 'assistants.id', '=', 'appointments.assistant_id')
                ->leftJoin('treatment_types', 'treatment_types.id', '=', 'appointments.treatment_type_id')
                ->leftJoin('patient_alerts', function ($join) {
                    $join->on('patient_alerts.patient_id', '=', 'patients.id')
                         ->where('patient_alerts.is_active', '=', true);
                })
                ->where('appointments.branch_id', $branchId)
                ->whereDate('appointments.scheduled_at', now()->toDateString())
                ->select([
                    'appointments.id',
                    'appointments.scheduled_at',
                    'appointments.status',
                    'appointments.arrival_status',
                    'appointments.chair',
                    'appointments.doctor_id',
                    'appointments.assistant_id',
                    'appointments.treatment_type_id',
                    'appointments.notes as appointment_notes',
                    'patients.id as patient_id',
                    'patients.name as patient_name',
                    'patients.phone as patient_phone',
                    'doctors.name as doctor_name',
                    'assistants.name as assistant_name',
                    'treatment_types.name as treatment_name',
                    'patient_alerts.message as alert_message',
                    'patient_alerts.severity as alert_severity',
                ])
                ->orderBy('appointments.scheduled_at')
                ->get()
        );
    }

    // -------------------------------------------------------------------------
    // TASK SYNC
    // Reads from tasks table. Role scoping: assistants only see assigned tasks.
    // -------------------------------------------------------------------------

    /**
     * Pull relevant tasks for today's board and ensure each has a card.
     */
    private function syncTodayTasks(HuddleBoard $board, int $branchId, string $role): void
    {
        $tasks = $this->fetchTodayTasks($branchId, $role);

        foreach ($tasks as $task) {
            $cardData = $this->taskTransformer->transform($task);

            $this->cardRepo->firstOrCreateFromSource(
                boardId:    $board->id,
                sourceType: 'task',
                sourceId:   $task->id,
                defaults:   array_merge($cardData, [
                    'huddle_board_id' => $board->id,
                    'column'          => 'tasks',
                    'position'        => 0,
                ])
            );
        }
    }

    /**
     * Fetch tasks scoped to branch and role.
     * Assistants only see tasks assigned to them.
     * Admins/doctors/front_desk see all branch tasks.
     */
    private function fetchTodayTasks(int $branchId, string $role): Collection
    {
        $query = DB::table('tasks')
            ->leftJoin('users as assignees', 'assignees.id', '=', 'tasks.assigned_to')
            ->leftJoin('users as creators', 'creators.id', '=', 'tasks.created_by')
            ->where('tasks.branch_id', $branchId)
            ->whereIn('tasks.status', ['pending', 'in_progress', 'overdue', 'blocked'])
            ->select([
                'tasks.id',
                'tasks.title',
                'tasks.description',
                'tasks.type',
                'tasks.status',
                'tasks.priority',
                'tasks.due_date',
                'tasks.assigned_to',
                'tasks.created_by',
                'tasks.proof_required',
                'tasks.proof_url',
                'tasks.meta',
                'assignees.name as assignee_name',
                'creators.name as creator_name',
            ])
            ->orderBy('tasks.priority', 'desc')
            ->orderBy('tasks.due_date');

        // Scope assistants to only their assigned tasks
        if ($role === 'assistant') {
            $query->where('tasks.assigned_to', auth()->id());
        }

        return collect($query->get());
    }

    // -------------------------------------------------------------------------
    // STATS COMPUTATION
    // Aggregates numbers for the TopStatsBar component.
    // All reads — no writes.
    // -------------------------------------------------------------------------

    /**
     * Compute the top-strip stat counters for today.
     */
    public function computeStats(int $boardId, int $branchId): array
    {
        $today = now()->toDateString();

        $appointmentStats = DB::table('appointments')
            ->where('branch_id', $branchId)
            ->whereDate('scheduled_at', $today)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN arrival_status = 'arrived' THEN 1 ELSE 0 END) as arrived,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
            ")
            ->first();

        $taskStats = DB::table('tasks')
            ->where('branch_id', $branchId)
            ->whereIn('status', ['pending', 'in_progress', 'overdue', 'blocked'])
            ->selectRaw("
                COUNT(*) as open_tasks,
                SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue_tasks,
                SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high_priority
            ")
            ->first();

        $alertCount = DB::table('patient_alerts')
            ->join('appointments', 'appointments.patient_id', '=', 'patient_alerts.patient_id')
            ->where('appointments.branch_id', $branchId)
            ->whereDate('appointments.scheduled_at', $today)
            ->where('patient_alerts.is_active', true)
            ->count();

        return [
            'appointments' => [
                'total'     => (int) ($appointmentStats->total ?? 0),
                'confirmed' => (int) ($appointmentStats->confirmed ?? 0),
                'pending'   => (int) ($appointmentStats->pending ?? 0),
                'arrived'   => (int) ($appointmentStats->arrived ?? 0),
                'cancelled' => (int) ($appointmentStats->cancelled ?? 0),
            ],
            'tasks' => [
                'open'         => (int) ($taskStats->open_tasks ?? 0),
                'overdue'      => (int) ($taskStats->overdue_tasks ?? 0),
                'high_priority'=> (int) ($taskStats->high_priority ?? 0),
            ],
            'alerts' => $alertCount,
        ];
    }

    // -------------------------------------------------------------------------
    // HELPERS
    // -------------------------------------------------------------------------

    /**
     * Group a flat card collection into column-keyed arrays.
     * This is the shape the frontend KanbanBoard expects.
     */
    private function groupCardsByColumn(Collection $cards): array
    {
        return $cards
            ->groupBy('column')
            ->map(fn ($group) => $group->values())
            ->toArray();
    }

    /**
     * Force-refresh a single card's payload from its source record.
     * Called by UpdateHuddleCard listener (Phase 3) when a source record changes.
     */
    public function refreshCard(int $cardId): void
    {
        $card = $this->cardRepo->findById($cardId);

        if ($card === null) {
            return;
        }

        if ($card->source_type === 'appointment') {
            $raw = DB::table('appointments')
                ->join('patients', 'patients.id', '=', 'appointments.patient_id')
                ->leftJoin('treatment_types', 'treatment_types.id', '=', 'appointments.treatment_type_id')
                ->where('appointments.id', $card->source_id)
                ->select([
                    'appointments.*',
                    'patients.name as patient_name',
                    'treatment_types.name as treatment_name',
                ])
                ->first();

            if ($raw) {
                $payload = $this->appointmentTransformer->transform($raw);
                $this->cardRepo->updatePayload($cardId, $payload);
            }
        }

        if ($card->source_type === 'task') {
            $raw = DB::table('tasks')
                ->leftJoin('users', 'users.id', '=', 'tasks.assigned_to')
                ->where('tasks.id', $card->source_id)
                ->select(['tasks.*', 'users.name as assignee_name'])
                ->first();

            if ($raw) {
                $payload = $this->taskTransformer->transform($raw);
                $this->cardRepo->updatePayload($cardId, $payload);
            }
        }
    }
}