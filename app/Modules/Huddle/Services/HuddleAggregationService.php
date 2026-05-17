<?php

declare(strict_types=1);

namespace App\Modules\Huddle\Services;

use App\Models\User;
use App\Modules\Huddle\DTOs\HuddleBoardDTO;
use App\Modules\Huddle\DTOs\HuddleCardDTO;
use App\Modules\Huddle\DTOs\HuddleStatsDTO;
use App\Modules\Huddle\Repositories\HuddleBoardRepository;
use App\Modules\Huddle\Transformers\AppointmentToCardTransformer;
use App\Modules\Huddle\Transformers\TaskToCardTransformer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HuddleAggregationService
{
    /**
     * Columns visible per role.
     * Keys must match what the frontend KanbanBoard expects.
     */
    private const ROLE_COLUMNS = [
        'admin'      => ['today_flow', 'yesterday_flow', 'tasks', 'comments'],
        'doctor'     => ['today_flow', 'yesterday_flow', 'tasks', 'comments'],
        'front_desk' => ['today_flow', 'tasks', 'comments'],
        'assistant'  => ['today_flow', 'tasks', 'comments'],
    ];

    public function __construct(
        private readonly HuddleBoardRepository       $boardRepo,
        private readonly AppointmentToCardTransformer $appointmentTransformer,
        private readonly TaskToCardTransformer         $taskTransformer,
    ) {}

    /**
     * Build the full board payload for a given user and date.
     */
    public function buildBoardForUser(User $user, Carbon $date): HuddleBoardDTO
    {
        $branchId  = $user->branch_id;
        $role      = $user->role ?? 'front_desk';
        $dateStr   = $date->toDateString();
        $yesterStr = $date->copy()->subDay()->toDateString();

        // 1. Find or create today's board
        $board = $this->boardRepo->findOrCreateForDate($branchId, $dateStr);

        // 2. Fetch raw data
        $todayAppointments     = $this->fetchAppointments($branchId, $dateStr);
        $yesterdayAppointments = $this->fetchAppointments($branchId, $yesterStr);
        $tasks                 = $this->fetchTasks($branchId, $dateStr);

        // 3. Transform to DTOs
        $todayCards     = $todayAppointments->map(fn ($r) => $this->appointmentTransformer->transform($r));
        $yesterdayCards = $yesterdayAppointments->map(fn ($r) => $this->appointmentTransformer->transform($r));
        $taskCards      = $tasks->map(fn ($r) => $this->taskTransformer->transform($r));

        // 4. Build stats from today's appointments + tasks
        $stats = $this->buildStats($todayAppointments, $tasks);

        // 5. Build role-filtered columns
        $allColumns = [
            'today_flow'     => $todayCards->all(),
            'yesterday_flow' => $yesterdayCards->all(),
            'tasks'          => $taskCards->all(),
            'comments'       => [],   // populated in Phase 2
        ];

        $visibleSlugs = self::ROLE_COLUMNS[$role] ?? self::ROLE_COLUMNS['front_desk'];
        $columns = array_intersect_key($allColumns, array_flip($visibleSlugs));

        return new HuddleBoardDTO(
            board:   $board,
            stats:   $stats,
            columns: $columns,
            role:    $role,
            date:    $dateStr,
        );
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Fetch appointments for a branch + date, joined with patient/doctor/treatment.
     */
    private function fetchAppointments(int $branchId, string $date)
    {
        return DB::table('appointments as a')
            ->join('patients as p', 'p.id', '=', 'a.patient_id')
            ->join('users as u', 'u.id', '=', 'a.doctor_id')
            ->leftJoin('treatments as t', 't.id', '=', 'a.treatment_id')
            ->leftJoin('treatment_categories as tc', 'tc.id', '=', 'a.treatment_category_id')
            ->leftJoin(
                DB::raw('(SELECT patient_id, MIN(alert) as patient_alert FROM patient_alerts GROUP BY patient_id) pa'),
                'pa.patient_id',
                '=',
                'a.patient_id'
            )
            ->where('a.branch_id', $branchId)
            ->whereDate('a.appointment_date', $date)
            ->whereNull('a.deleted_at')
            ->select([
                'a.id as appointment_id',
                'a.patient_id',
                'a.doctor_id',
                'a.branch_id',
                'a.appointment_date',
                'a.appointment_time',
                'a.duration_minutes',
                'a.type',
                'a.treatment_category_id',
                'a.treatment_id',
                'a.status',
                'a.chief_complaint',
                'a.notes',
                'p.name as patient_name',
                'u.name as doctor_name',
                't.name as treatment_name',
                'tc.name as category_name',
                'pa.patient_alert',
            ])
            ->orderBy('a.appointment_time')
            ->get();
    }

    /**
     * Fetch today's pending/escalated tasks for the branch,
     * including tasks due today or overdue.
     */
    private function fetchTasks(int $branchId, string $date)
    {
        return DB::table('tasks as t')
            ->leftJoin('users as u', 'u.id', '=', 't.assigned_to')
            ->leftJoin('patients as p', 'p.id', '=', 't.patient_id')
            ->where('t.branch_id', $branchId)
            ->where(function ($q) use ($date) {
                $q->whereDate('t.due_date', '<=', $date)
                  ->where('t.status', '!=', 'done');
            })
            ->whereNull('t.deleted_at')
            ->select([
                't.id',
                't.title',
                't.description',
                't.assigned_to',
                't.created_by',
                't.branch_id',
                't.patient_id',
                't.due_date',
                't.due_time',
                't.priority',
                't.category',
                't.status',
                't.done_at',
                't.escalated_at',
                't.escalation_note',
                'u.name as assignee_name',
                'p.name as patient_name',
            ])
            ->orderByRaw("FIELD(t.priority, 'urgent', 'high', 'medium', 'low')")
            ->orderBy('t.due_date')
            ->get();
    }

    /**
     * Compute top-strip stats from raw appointment + task rows.
     */
    private function buildStats($appointments, $tasks): HuddleStatsDTO
    {
        $statusCounts = $appointments->groupBy('status')->map->count();

        $today    = Carbon::today()->toDateString();
        $overdue  = $tasks->filter(fn ($t) => $t->due_date < $today && $t->status === 'pending')->count();

        return new HuddleStatsDTO(
            totalAppointments: $appointments->count(),
            confirmed:         $statusCounts->get('scheduled', 0),
            checkedIn:         $statusCounts->get('checkin', 0),
            inChair:           $statusCounts->get('in_chair', 0),
            done:              $statusCounts->get('done', 0),
            cancelled:         $statusCounts->get('cancelled', 0),
            noShow:            $statusCounts->get('no_show', 0),
            pendingTasks:      $tasks->where('status', 'pending')->count(),
            overdueTasks:      $overdue,
            escalatedTasks:    $tasks->where('status', 'escalated')->count(),
        );
    }
}
