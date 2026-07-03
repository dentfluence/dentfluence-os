<?php

declare(strict_types=1);

namespace App\Modules\Huddle\Services;

use App\Modules\Huddle\Repositories\HuddleBoardRepository;
use App\Modules\Huddle\Repositories\HuddleCardRepository;
use App\Modules\Huddle\Models\HuddleBoard;
use App\Modules\Huddle\Transformers\AppointmentToCardTransformer;
use App\Modules\Huddle\Transformers\TaskToCardTransformer;
use App\Modules\Huddle\DTOs\HuddleBoardDTO;
use App\Modules\Huddle\DTOs\HuddleCardDTO;
use App\Modules\Huddle\DTOs\HuddleStatsDTO;
use App\Services\Relationship\TodayActionsEngine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HuddleAggregationService
{
    public function __construct(
        private readonly HuddleBoardRepository        $boardRepo,
        private readonly HuddleCardRepository         $cardRepo,
        private readonly AppointmentToCardTransformer $appointmentTransformer,
        private readonly TaskToCardTransformer        $taskTransformer,
        private readonly TodayActionsEngine           $todayActionsEngine,
    ) {}

    public function buildBoardForUser(int $branchId, string $role): HuddleBoardDTO
    {
        $board = $this->boardRepo->findOrCreateForToday($branchId, $role);

        $this->syncTodayAppointments($board, $branchId);
        $this->syncTodayTasks($board, $branchId, $role);

        $cards   = $this->cardRepo->getByBoard($board->id);
        $stats   = $this->computeStats($board->id, $branchId);
        $columns = $this->groupCardsByColumn($cards);

        $statsDTO = new HuddleStatsDTO(
            totalAppointments: $stats['appointments']['total'],
            confirmed:         $stats['appointments']['confirmed'],
            checkedIn:         $stats['appointments']['arrived'],
            inChair:           $stats['appointments']['in_chair'],  // FIX #4
            done:              $stats['appointments']['done'],       // FIX #4
            cancelled:         $stats['appointments']['cancelled'],
            noShow:            0,
            pendingTasks:      $stats['tasks']['open'],
            overdueTasks:      $stats['tasks']['overdue'],
            escalatedTasks:    $stats['tasks']['high_priority'],
        );

        return new HuddleBoardDTO(
            board:   $board,
            stats:   $statsDTO,
            columns: $columns,
            role:    $role,
            date:    now()->toDateString(),
        );
    }

    private function syncTodayAppointments(HuddleBoard $board, int $branchId): void
    {
        $appointments = $this->fetchTodayAppointments($branchId);

        foreach ($appointments as $appointment) {
            $dto      = $this->appointmentTransformer->transform($appointment);
            $snapshot = $dto->toArray(); // FIX #2: snapshot was never saved before

            $this->cardRepo->firstOrCreateFromSource(
                boardId:    $board->id,
                sourceType: 'appointment',
                sourceId:   $appointment->appointment_id,
                defaults:   [
                    'huddle_board_id' => $board->id,
                    'column_key'      => 'today_flow',
                    'card_type'       => 'patient_flow',
                    'position'        => 0,
                    'status'          => $dto->status,
                    'snapshot'        => $snapshot, // FIX #2
                ]
            );
        }
    }

    private function fetchTodayAppointments(int $branchId): Collection
    {
        return collect(
            DB::table('appointments')
                ->join('patients', 'patients.id', '=', 'appointments.patient_id')
                ->leftJoin('users as doctors', 'doctors.id', '=', 'appointments.doctor_id')
                ->leftJoin('treatment_types', 'treatment_types.id', '=', 'appointments.treatment_id')
                ->leftJoin('patient_alerts', function ($join) {
                    $join->on('patient_alerts.patient_id', '=', 'patients.id')
                         ->where('patient_alerts.is_active', '=', true);
                })
                ->where('appointments.branch_id', $branchId)
                ->whereDate('appointments.appointment_date', now()->toDateString())
                ->select([
                    'appointments.id as appointment_id',
                    'appointments.appointment_date',
                    'appointments.appointment_time',
                    'appointments.duration_minutes',
                    'appointments.type',
                    'appointments.status',
                    'appointments.chief_complaint',
                    'appointments.notes',
                    'appointments.treatment_id',
                    'patients.id as patient_id',
                    'patients.name as patient_name',
                    'patients.phone as patient_phone',
                    'doctors.name as doctor_name',
                    'treatment_types.name as treatment_name',
                    'patient_alerts.alert as patient_alert',
                ])
                ->orderBy('appointments.appointment_date')
                ->orderBy('appointments.appointment_time')
                ->get()
        );
    }

    private function syncTodayTasks(HuddleBoard $board, int $branchId, string $role): void
    {
        $tasks = $this->fetchTodayTasks($branchId, $role);

        foreach ($tasks as $task) {
            $dto      = $this->taskTransformer->transform($task);
            $snapshot = $dto->toArray(); // FIX #2

            $this->cardRepo->firstOrCreateFromSource(
                boardId:    $board->id,
                sourceType: 'task',
                sourceId:   $task->id,
                defaults:   [
                    'huddle_board_id' => $board->id,
                    'column_key'      => 'tasks',
                    'card_type'       => 'task',
                    'position'        => 0,
                    'status'          => $dto->status,
                    'snapshot'        => $snapshot, // FIX #2
                ]
            );
        }
    }

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
                'tasks.category',
                'tasks.status',
                'tasks.priority',
                'tasks.due_date',
                'tasks.due_time',
                'tasks.assigned_to',
                'tasks.created_by',
                'tasks.patient_id',
                'assignees.name as assignee_name',
                'creators.name as creator_name',
            ])
            ->orderBy('tasks.priority', 'desc')
            ->orderBy('tasks.due_date');

        if ($role === 'assistant') {
            $query->where('tasks.assigned_to', auth()->id());
        }

        return collect($query->get());
    }

    public function computeStats(int $boardId, int $branchId): array
    {
        $today = now()->toDateString();

        $appointmentStats = DB::table('appointments')
            ->where('branch_id', $branchId)
            ->whereDate('appointment_date', $today)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as confirmed,
                SUM(CASE WHEN status = 'checkin'   THEN 1 ELSE 0 END) as arrived,
                SUM(CASE WHEN status = 'in_chair'  THEN 1 ELSE 0 END) as in_chair,
                SUM(CASE WHEN status = 'done'      THEN 1 ELSE 0 END) as done,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
            ")
            ->first();

        $taskStats = DB::table('tasks')
            ->where('branch_id', $branchId)
            ->whereIn('status', ['pending', 'in_progress', 'overdue', 'blocked'])
            ->selectRaw("
                COUNT(*) as open_tasks,
                SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue_tasks,
                SUM(CASE WHEN priority = 'high'  THEN 1 ELSE 0 END) as high_tasks
            ")
            ->first();

        $alertCount = DB::table('patient_alerts')
            ->join('appointments', 'appointments.patient_id', '=', 'patient_alerts.patient_id')
            ->where('appointments.branch_id', $branchId)
            ->whereDate('appointments.appointment_date', $today)
            ->where('patient_alerts.is_active', true)
            ->count();

        return [
            'appointments' => [
                'total'     => (int) ($appointmentStats->total     ?? 0),
                'confirmed' => (int) ($appointmentStats->confirmed ?? 0),
                'pending'   => 0,
                'arrived'   => (int) ($appointmentStats->arrived   ?? 0),
                'in_chair'  => (int) ($appointmentStats->in_chair  ?? 0),
                'done'      => (int) ($appointmentStats->done      ?? 0),
                'cancelled' => (int) ($appointmentStats->cancelled ?? 0),
            ],
            'tasks' => [
                'open'          => (int) ($taskStats->open_tasks    ?? 0),
                'overdue'       => (int) ($taskStats->overdue_tasks ?? 0),
                'high_priority' => (int) ($taskStats->high_tasks    ?? 0),
            ],
            'alerts' => $alertCount,
        ];
    }

    // FIX #1: was reading $card->payload — actual DB column is 'snapshot'
    private function groupCardsByColumn(Collection $cards): array
    {
        return $cards
            ->groupBy('column_key')
            ->map(function (Collection $group) {
                return $group->map(function ($card) {
                    $snapshot = is_string($card->snapshot)
                        ? json_decode($card->snapshot, true) ?? []
                        : (array) ($card->snapshot ?? []);

                    return new HuddleCardDTO(
                        sourceType:      $card->source_type,
                        sourceId:        (int) $card->source_id,
                        patientId:       isset($snapshot['patient_id']) ? (int) $snapshot['patient_id'] : null,
                        patientName:     $snapshot['patient_name']     ?? null,
                        doctorName:      $snapshot['doctor_name']      ?? null,
                        time:            $snapshot['time']             ?? null,
                        date:            $snapshot['date']             ?? now()->toDateString(),
                        duration:        isset($snapshot['duration']) ? (int) $snapshot['duration'] : null,
                        appointmentType: $snapshot['appointment_type'] ?? null,
                        treatmentName:   $snapshot['treatment_name']   ?? null,
                        categoryName:    $snapshot['category_name']    ?? null,
                        status:          $snapshot['status']           ?? $card->status ?? 'pending',
                        chiefComplaint:  $snapshot['chief_complaint']  ?? null,
                        notes:           $snapshot['notes']            ?? null,
                        patientAlert:    $snapshot['patient_alert']    ?? null,
                        meta:            $snapshot['meta']             ?? [],
                    );
                })->values();
            })
            ->toArray();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Phase 7 — Relationship Engine Integration
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Pull today's relationship actions from TodayActionsEngine and map them
     * to HuddleCardDTO objects grouped by category.
     *
     * Categories surfaced in huddle:
     *   - recall_calls            (patients due for recall)
     *   - missed_appointments_yesterday (no-shows from yesterday)
     *   - lead_followups          (overdue lead follow-ups)
     *   - membership_renewals     (memberships expiring soon)
     *
     * Returns a flat array of HuddleCardDTO objects (not grouped — the view
     * handles grouping by categoryName if needed).
     *
     * This is ADDITIVE: it does not touch or replace any existing huddle logic.
     *
     * @return HuddleCardDTO[]
     */
    public function getRelationshipItems(): array
    {
        // Categories we surface in the huddle — a focused subset of the 12.
        $huddleCategories = [
            'recall_calls',
            'missed_appointments_yesterday',
            'lead_followups',
            'membership_renewals',
        ];

        try {
            $all = $this->todayActionsEngine->generate();
        } catch (\Throwable $e) {
            Log::warning('HuddleAggregationService: TodayActionsEngine failed', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }

        $cards = [];

        foreach ($huddleCategories as $category) {
            $items = $all[$category] ?? [];

            foreach ($items as $item) {
                $cards[] = new HuddleCardDTO(
                    sourceType:      'relationship_action',
                    sourceId:        $item['patient_id'] ?? $item['lead_id'] ?? 0,
                    patientId:       $item['patient_id'] ?? null,
                    patientName:     $item['patient_name'] ?? null,
                    doctorName:      null,
                    time:            null,
                    date:            now()->toDateString(),
                    duration:        null,
                    appointmentType: null,
                    treatmentName:   null,
                    categoryName:    $category,
                    status:          $item['priority'] ?? 'medium',
                    chiefComplaint:  $item['reason'] ?? null,
                    notes:           $item['suggested_action'] ?? null,
                    patientAlert:    null,
                    meta:            array_merge($item['meta'] ?? [], [
                        'link'            => $item['link'] ?? '#',
                        'lead_id'         => $item['lead_id'] ?? null,
                        'relationship_id' => $item['relationship_id'] ?? null,
                        'category_label'  => ucwords(str_replace('_', ' ', $category)),
                        // Carried through so the Huddle Comms List can show/call it —
                        // the raw TodayActionsEngine item already has this, it just
                        // wasn't being forwarded onto the card before.
                        'phone'           => $item['phone'] ?? null,
                    ]),
                );
            }
        }

        return $cards;
    }

    // FIX #3: refreshCard was passing DTO object to updatePayload — must call toArray()
    public function refreshCard(int $cardId): void
    {
        $card = $this->cardRepo->findById($cardId);
        if ($card === null) return;

        if ($card->source_type === 'appointment') {
            $raw = DB::table('appointments')
                ->join('patients', 'patients.id', '=', 'appointments.patient_id')
                ->leftJoin('users as doctors', 'doctors.id', '=', 'appointments.doctor_id')
                ->leftJoin('treatment_types', 'treatment_types.id', '=', 'appointments.treatment_id')
                ->leftJoin('patient_alerts', function ($join) {
                    $join->on('patient_alerts.patient_id', '=', 'patients.id')
                         ->where('patient_alerts.is_active', '=', true);
                })
                ->where('appointments.id', $card->source_id)
                ->select([
                    'appointments.id as appointment_id',
                    'appointments.appointment_date',
                    'appointments.appointment_time',
                    'appointments.duration_minutes',
                    'appointments.type',
                    'appointments.status',
                    'appointments.chief_complaint',
                    'appointments.notes',
                    'appointments.treatment_id',
                    'patients.id as patient_id',
                    'patients.name as patient_name',
                    'patients.phone as patient_phone',
                    'doctors.name as doctor_name',
                    'treatment_types.name as treatment_name',
                    'patient_alerts.alert as patient_alert',
                ])
                ->first();

            if ($raw) {
                $snapshot = $this->appointmentTransformer->transform($raw)->toArray(); // FIX #3
                $this->cardRepo->updateSnapshot($cardId, $snapshot);
            }
        }

        if ($card->source_type === 'task') {
            $raw = DB::table('tasks')
                ->leftJoin('users as assignees', 'assignees.id', '=', 'tasks.assigned_to')
                ->leftJoin('users as creators', 'creators.id', '=', 'tasks.created_by')
                ->where('tasks.id', $card->source_id)
                ->select([
                    'tasks.id', 'tasks.title', 'tasks.description', 'tasks.category',
                    'tasks.status', 'tasks.priority', 'tasks.due_date', 'tasks.due_time',
                    'tasks.assigned_to', 'tasks.created_by', 'tasks.patient_id',
                    'assignees.name as assignee_name', 'creators.name as creator_name',
                ])
                ->first();

            if ($raw) {
                $snapshot = $this->taskTransformer->transform($raw)->toArray(); // FIX #3
                $this->cardRepo->updateSnapshot($cardId, $snapshot);
            }
        }
    }
}
