<?php

declare(strict_types=1);

namespace App\Services\Huddle;

use App\Models\Task;
use App\Models\User;
use App\Services\Relationship\TodayActionsEngine;
use App\Services\Relationship\TodayActionsProjector;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * HuddleBoardApiService
 * ---------------------------------------------------------------------------
 * The mobile-facing "brain" for the Daily Huddle screen.
 *
 * The web Huddle controller builds a huge Blade payload. The mobile app needs
 * the same facts but in a small, predictable JSON shape that is cheap to parse
 * on a phone. This service assembles that shape, branch-scoped, for one day.
 *
 * Every section is built in its own guarded method so a failure in one module
 * (e.g. labs/inventory not migrated) never breaks the whole board — it just
 * returns an empty section.
 *
 * Returned shape (all keys always present):
 *   [
 *     'date'              => '2026-06-26',
 *     'date_label'        => 'Friday, 26 June 2026',
 *     'kpis'              => [...],
 *     'schedule'          => [...],   // today's appointments
 *     'yesterday'         => [...],   // yesterday's flow + visit-logged flags
 *     'alerts'            => [...],   // critical patient + inventory alerts
 *     'notes'             => [...],   // today's huddle notes
 *     'tasks'             => [...],   // branch tasks due today / overdue
 *     'today_snapshot'    => [...],   // PRE Today's Actions projection summary
 *     'relationship_items'=> [...],   // recall/lead/missed-yesterday/membership items
 *   ]
 */
class HuddleBoardApiService
{
    public function __construct(
        private readonly TodayActionsEngine    $todayActionsEngine,
        private readonly TodayActionsProjector $todayActionsProjector,
    ) {}

    /**
     * Assemble the full mobile huddle board for a branch on a given date.
     */
    public function build(int $branchId, ?string $date = null): array
    {
        $today     = $date ? Carbon::parse($date)->startOfDay() : Carbon::today();
        $yesterday = $today->copy()->subDay();

        $schedule  = $this->safe(fn () => $this->todaySchedule($branchId, $today), collect());
        $yesterFlow = $this->safe(fn () => $this->yesterdayFlow($branchId, $yesterday), collect());
        $tasks     = $this->safe(fn () => $this->tasks($branchId, $today), collect());
        $alerts    = $this->safe(fn () => $this->alerts($branchId, $today), collect());
        $notes     = $this->safe(fn () => $this->notes($branchId, $today), collect());
        $labs      = $this->safe(fn () => $this->labs($branchId, $today), $this->emptyLabs());
        $comms     = $this->safe(fn () => $this->comms($branchId, $today, $yesterday), collect());
        $snapshot  = $this->safe(fn () => $this->todaySnapshot(), $this->emptySnapshot());
        $relItems  = $this->safe(fn () => $this->relationshipItems(), collect());

        return [
            'date'       => $today->toDateString(),
            'date_label' => $today->isoFormat('dddd, D MMMM YYYY'),
            'kpis'       => $this->kpis($branchId, $today, $schedule, $yesterFlow, $tasks, $labs, $comms),
            'schedule'   => $schedule->values()->all(),
            'yesterday'  => $yesterFlow->values()->all(),
            'alerts'     => $alerts->values()->all(),
            'notes'      => $notes->values()->all(),
            'tasks'      => $tasks->values()->all(),
            'labs'       => $labs,
            'comms'      => $comms->values()->all(),
            'today_snapshot'     => $snapshot,
            'relationship_items' => $relItems->values()->all(),
        ];
    }

    /* ===================================================================== */
    /*  PRE — Today's Actions snapshot + relationship items                  */
    /*  Same source the web huddle uses (Phase 7 / HuddleAggregationService, */
    /*  Modules/Huddle/Services/HuddleAggregationService.php), reshaped as   */
    /*  plain arrays for JSON instead of HuddleCardDTO objects.              */
    /* ===================================================================== */

    /** Empty snapshot shape so the key is always present even if the
     *  projection hasn't been built yet (today.projection flag / cron). */
    private function emptySnapshot(): array
    {
        return ['total' => 0, 'by_category' => [], 'by_priority' => [], 'generated_at' => null];
    }

    private function todaySnapshot(): array
    {
        return $this->todayActionsProjector->summary();
    }

    /**
     * The same focused subset of TodayActionsEngine categories the web
     * huddle surfaces (Modules/Huddle/Services/HuddleAggregationService.php
     * getRelationshipItems()), reshaped as plain arrays.
     *
     * @return Collection<int, array>
     */
    private function relationshipItems(): Collection
    {
        $categories = [
            'recall_calls',
            'missed_appointments_yesterday',
            'lead_followups',
            'membership_renewals',
        ];

        try {
            $all = $this->todayActionsEngine->generate();
        } catch (\Throwable $e) {
            Log::warning('HuddleBoardApiService: TodayActionsEngine failed', [
                'error' => $e->getMessage(),
            ]);
            return collect();
        }

        $items = collect();

        foreach ($categories as $category) {
            foreach (($all[$category] ?? []) as $item) {
                $items->push([
                    'category'        => $category,
                    'category_label'  => ucwords(str_replace('_', ' ', $category)),
                    'patient_id'      => $item['patient_id'] ?? null,
                    'patient_name'    => $item['patient_name'] ?? 'Unknown',
                    'phone'           => $item['meta']['phone'] ?? null,
                    'priority'        => $item['priority'] ?? 'medium',
                    'reason'          => $item['reason'] ?? null,
                    'suggested_action'=> $item['suggested_action'] ?? null,
                ]);
            }
        }

        return $items;
    }

    /* ===================================================================== */
    /*  KPI summary cards                                                    */
    /* ===================================================================== */

    private function kpis(int $branchId, Carbon $today, Collection $schedule, Collection $yesterday, Collection $tasks, array $labs = [], ?Collection $comms = null): array
    {
        $todayDone = $schedule->whereIn('status', ['done', 'checkout', 'completed'])->count();

        // Yesterday: how many treated, and how many are missing their visit log
        $yTreated = $yesterday->where('visit_logged', true)->count();
        $yMissing = $yesterday->where('visit_flag', true)->count();

        $comms ??= collect();

        // Money collected today (guarded — finance tables may differ by build)
        $collectedToday = 0.0;
        try {
            if (class_exists(\App\Models\Finance\FinanceTransaction::class)) {
                $collectedToday = (float) \App\Models\Finance\FinanceTransaction::query()
                    ->where('type', 'income')
                    ->where('status', 'active')
                    ->whereDate('transaction_date', $today->toDateString())
                    ->sum('amount');
            }
        } catch (\Throwable $e) {
            $collectedToday = 0.0;
        }

        // Collections (Today) target — front-desk figure from scheduled
        // appointments' "Amount to Collect" (Today's Patient Flow popup).
        // Walk-ins excluded on purpose: they aren't planned ahead of time, so
        // there's nothing to prep a collection figure against. Mirrors web's
        // HuddleController::index() $collectionsTarget/$collectionsLoggedCount.
        $collectionsScheduled   = $schedule->where('is_walkin', false);
        $collectionsTarget      = (float) $collectionsScheduled->sum('amount_to_collect');
        $collectionsLoggedCount = $collectionsScheduled->filter(fn ($r) => $r['amount_to_collect'] !== null)->count();
        $collectionsTotalCount  = $collectionsScheduled->count();

        return [
            'appointments_today'      => $schedule->count(),
            'appointments_done'       => $todayDone,
            'appointments_pending'    => max(0, $schedule->count() - $todayDone),
            'yesterday_treated'       => $yTreated,
            'yesterday_missing'       => $yMissing,
            'tasks_total'             => $tasks->count(),
            'tasks_overdue'           => $tasks->where('is_overdue', true)->count(),
            'collected_today'         => $collectedToday,
            'collections_target'          => $collectionsTarget,
            'collections_logged_count'    => $collectionsLoggedCount,
            'collections_total_count'     => $collectionsTotalCount,
            'labs_due'                => (int) (($labs['due'] ?? null) ? count($labs['due']) : 0),
            'comms_pending'           => $comms->count(),
        ];
    }

    /* ===================================================================== */
    /*  Today's schedule                                                     */
    /* ===================================================================== */

    private function todaySchedule(int $branchId, Carbon $today): Collection
    {
        return DB::table('appointments')
            ->join('patients', 'patients.id', '=', 'appointments.patient_id')
            ->leftJoin('users as doctors', 'doctors.id', '=', 'appointments.doctor_id')
            ->leftJoin('users as chairside', 'chairside.id', '=', 'appointments.chairside_assistant_id')
            ->leftJoin('treatment_types', 'treatment_types.id', '=', 'appointments.treatment_id')
            ->where('appointments.branch_id', $branchId)
            ->whereDate('appointments.appointment_date', $today->toDateString())
            ->select([
                'appointments.id',
                'appointments.patient_id',
                'appointments.appointment_time',
                'appointments.status',
                'appointments.type',
                'appointments.staff_instruction',
                'appointments.is_walkin',
                'appointments.amount_to_collect',
                'appointments.prep_item',
                'appointments.chairside_assistant_id',
                'patients.name as patient_name',
                'patients.medical_alert',
                'doctors.name as doctor_name',
                'chairside.name as chairside_assistant_name',
                'treatment_types.name as treatment_name',
            ])
            ->orderBy('appointments.appointment_time')
            ->get()
            ->map(fn ($r) => [
                'id'                       => (int) $r->id,
                'patient_id'               => (int) $r->patient_id,
                'patient_name'             => $r->patient_name,
                'doctor_name'              => $r->doctor_name,
                'treatment'                => $r->treatment_name,
                'time'                     => $r->appointment_time
                    ? Carbon::parse($r->appointment_time)->format('h:i A')
                    : null,
                'status'                   => $r->status,
                'type'                     => $r->type,
                'medical_alert'            => $r->medical_alert,
                'staff_instruction'        => $r->staff_instruction,
                // Today's Patient Flow popup (Huddle board, 2026-07-06)
                'is_walkin'                => (bool) $r->is_walkin,
                'amount_to_collect'        => $r->amount_to_collect !== null ? (float) $r->amount_to_collect : null,
                'prep_item'                => $r->prep_item,
                'chairside_assistant_id'   => $r->chairside_assistant_id ? (int) $r->chairside_assistant_id : null,
                'chairside_assistant_name' => $r->chairside_assistant_name,
            ]);
    }

    /* ===================================================================== */
    /*  Yesterday's flow (with visit-logged flags)                           */
    /* ===================================================================== */

    private function yesterdayFlow(int $branchId, Carbon $yesterday): Collection
    {
        $rows = DB::table('appointments')
            ->join('patients', 'patients.id', '=', 'appointments.patient_id')
            ->leftJoin('users as doctors', 'doctors.id', '=', 'appointments.doctor_id')
            ->leftJoin('treatment_types', 'treatment_types.id', '=', 'appointments.treatment_id')
            ->where('appointments.branch_id', $branchId)
            ->whereDate('appointments.appointment_date', $yesterday->toDateString())
            ->select([
                'appointments.id',
                'appointments.patient_id',
                'appointments.appointment_time',
                'appointments.status as appointment_status',
                'patients.name as patient_name',
                'patients.medical_alert',
                'doctors.name as doctor_name',
                'treatment_types.name as treatment_name',
            ])
            ->orderBy('appointments.appointment_time')
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        // Work out which appointments actually had their visit logged. A visit
        // counts as "logged" if EITHER a consultation OR a treatment visit
        // exists — matched first on appointment_id, falling back to patient+date.
        $apptIds = $rows->pluck('id');
        $patIds  = $rows->pluck('patient_id')->unique();
        $dateStr = $yesterday->toDateString();

        $consByAppt = DB::table('consultations')->whereNull('deleted_at')
            ->whereIn('appointment_id', $apptIds)->pluck('appointment_id')->flip();
        $consByPat  = DB::table('consultations')->whereNull('deleted_at')
            ->whereIn('patient_id', $patIds)->whereDate('consultation_date', $dateStr)
            ->pluck('patient_id')->flip();
        $tvByAppt   = DB::table('treatment_visits')->whereNull('deleted_at')
            ->whereIn('appointment_id', $apptIds)->pluck('appointment_id')->flip();
        $tvByPat    = DB::table('treatment_visits')->whereNull('deleted_at')
            ->whereIn('patient_id', $patIds)->whereDate('visit_date', $dateStr)
            ->pluck('patient_id')->flip();

        // Each patient's NEXT upcoming appointment (earliest after yesterday).
        $nextAppts = DB::table('appointments')
            ->leftJoin('users as na_doctors', 'na_doctors.id', '=', 'appointments.doctor_id')
            ->leftJoin('treatment_types as na_tt', 'na_tt.id', '=', 'appointments.treatment_id')
            ->where('appointments.branch_id', $branchId)
            ->whereIn('appointments.patient_id', $patIds)
            ->whereDate('appointments.appointment_date', '>', $dateStr)
            ->whereNotIn('appointments.status', ['cancelled', 'no_show'])
            ->select([
                'appointments.patient_id',
                'appointments.appointment_date',
                'appointments.appointment_time',
                'na_doctors.name as doctor_name',
                'na_tt.name as treatment_name',
            ])
            ->orderBy('appointments.appointment_date')
            ->orderBy('appointments.appointment_time')
            ->get()
            ->groupBy('patient_id')
            ->map(fn ($g) => $g->first());   // earliest next appt per patient

        $skipped = ['cancelled', 'no_show'];

        return $rows->map(function ($r) use ($consByAppt, $consByPat, $tvByAppt, $tvByPat, $skipped, $nextAppts) {
            $hasConsult = isset($consByAppt[$r->id]) || isset($consByPat[$r->patient_id]);
            $hasVisit   = isset($tvByAppt[$r->id])   || isset($tvByPat[$r->patient_id]);
            $logged     = $hasConsult || $hasVisit;
            $shouldHave = ! in_array($r->appointment_status, $skipped, true);

            $next = $nextAppts->get($r->patient_id);
            $nextAppt = $next ? [
                'date'      => $next->appointment_date,
                'time'      => $next->appointment_time
                    ? Carbon::parse($next->appointment_time)->format('h:i A')
                    : null,
                'doctor'    => $next->doctor_name,
                'treatment' => $next->treatment_name,
            ] : null;

            return [
                'id'            => (int) $r->id,
                'patient_id'    => (int) $r->patient_id,
                'patient_name'  => $r->patient_name,
                'doctor_name'   => $r->doctor_name,
                'treatment'     => $r->treatment_name,
                'status'        => $r->appointment_status,
                'visit_logged'  => $logged,
                // visit_flag = should have a visit logged but doesn't (needs attention)
                'visit_flag'    => $shouldHave && ! $logged,
                'visit_source'  => $hasConsult ? 'consultation' : ($hasVisit ? 'treatment_visit' : null),
                'medical_alert' => $r->medical_alert,
                'next_appt'     => $nextAppt,
            ];
        });
    }

    /* ===================================================================== */
    /*  Branch tasks (due today / overdue)                                   */
    /* ===================================================================== */

    /**
     * Tasks due today or overdue for the branch. Shared by the board payload
     * AND the standalone GET /huddle/tasks endpoint (via $scope).
     *
     * @param  string  $scope  'open' (today+overdue, default) | 'today' | 'overdue' | 'all'
     */
    public function tasks(int $branchId, Carbon $today, string $scope = 'open'): Collection
    {
        $q = DB::table('tasks')
            ->leftJoin('users as assignee', 'assignee.id', '=', 'tasks.assigned_to')
            ->whereNull('tasks.deleted_at')
            ->where('tasks.branch_id', $branchId);

        // Status filter — 'all' keeps done tasks too
        if ($scope !== 'all') {
            $q->whereIn('tasks.status', ['pending', 'in_progress']);
        }

        $todayStr = $today->toDateString();
        match ($scope) {
            'today'   => $q->whereDate('tasks.due_date', $todayStr),
            'overdue' => $q->whereDate('tasks.due_date', '<', $todayStr),
            'all'     => null,
            default   => $q->where(function ($w) use ($todayStr) {   // 'open'
                $w->whereDate('tasks.due_date', $todayStr)
                  ->orWhereDate('tasks.due_date', '<', $todayStr);
            }),
        };

        return $q->select([
                'tasks.id',
                'tasks.title',
                'tasks.priority',
                'tasks.category',
                'tasks.status',
                'tasks.due_date',
                'tasks.due_time',
                'tasks.assigned_to',
                'assignee.name as assignee_name',
            ])
            ->orderByRaw("FIELD(tasks.priority, 'urgent', 'high', 'medium', 'low')")
            ->orderBy('tasks.due_date')
            ->get()
            ->map(fn ($t) => [
                'id'            => (int) $t->id,
                'title'         => $t->title,
                'priority'      => $t->priority,
                'category'      => $t->category,
                'category_label'=> Task::CATEGORIES[$t->category] ?? ucfirst((string) $t->category),
                'status'        => $t->status,
                'done'          => $t->status === 'done',
                'due_date'      => $t->due_date,
                'due_time'      => $t->due_time
                    ? Carbon::parse($t->due_time)->format('h:i A')
                    : null,
                'is_overdue'    => $t->due_date
                    && Carbon::parse($t->due_date)->lt($today)
                    && $t->status !== 'done',
                'assigned_to'   => $t->assigned_to ? (int) $t->assigned_to : null,
                'assignee_name' => $t->assignee_name,
            ]);
    }

    /* ===================================================================== */
    /*  Critical alerts (patient safety + low stock)                         */
    /* ===================================================================== */

    private function alerts(int $branchId, Carbon $today): Collection
    {
        // Patient safety alerts for patients seen today
        $patientAlerts = DB::table('patient_alerts')
            ->join('patients', 'patients.id', '=', 'patient_alerts.patient_id')
            ->join('appointments', 'appointments.patient_id', '=', 'patient_alerts.patient_id')
            ->where('appointments.branch_id', $branchId)
            ->whereDate('appointments.appointment_date', $today->toDateString())
            ->where('patient_alerts.is_active', true)
            ->select(['patients.name', 'patient_alerts.alert'])
            ->distinct()
            ->get()
            ->map(fn ($a) => [
                'type'    => 'patient',
                'message' => $a->name . ': ' . $a->alert,
                'level'   => 'error',
            ]);

        // Low-stock inventory (guarded — tables may not be migrated)
        try {
            $invItems = DB::table('inventory_items as i')
                ->leftJoin(
                    DB::raw('(SELECT inventory_item_id, SUM(available_qty) as total_qty FROM inventory_stocks GROUP BY inventory_item_id) as s'),
                    's.inventory_item_id', '=', 'i.id'
                )
                ->where('i.is_active', true)
                ->whereRaw('COALESCE(s.total_qty, 0) <= i.minimum_qty')
                ->select(['i.product_name', 'i.minimum_qty', DB::raw('COALESCE(s.total_qty, 0) as total_qty')])
                ->orderBy(DB::raw('COALESCE(s.total_qty, 0)'))
                ->limit(10)
                ->get()
                ->map(fn ($item) => [
                    'type'    => 'inventory',
                    'message' => $item->product_name . ' (' . $item->total_qty . ' left, min ' . $item->minimum_qty . ')',
                    'level'   => $item->total_qty <= 0 ? 'error' : 'warning',
                ]);

            $patientAlerts = $patientAlerts->merge($invItems);
        } catch (\Throwable $e) {
            // Inventory not migrated — skip silently
        }

        return $patientAlerts;
    }

    /* ===================================================================== */
    /*  Today's huddle notes                                                 */
    /* ===================================================================== */

    private function notes(int $branchId, Carbon $today): Collection
    {
        return DB::table('huddle_notes')
            ->leftJoin('users', 'users.id', '=', 'huddle_notes.user_id')
            ->where('huddle_notes.branch_id', $branchId)
            ->whereDate('huddle_notes.created_at', $today->toDateString())
            ->select([
                'huddle_notes.body',
                'huddle_notes.category',
                'huddle_notes.created_at',
                'users.name as author_name',
            ])
            ->orderBy('huddle_notes.created_at')
            ->get()
            ->map(fn ($n) => [
                'category' => $n->category,
                'body'     => $n->body,
                'author'   => $n->author_name ?? 'Team',
                'time'     => Carbon::parse($n->created_at)->format('h:i A'),
            ]);
    }

    /* ===================================================================== */
    /*  Labs: overdue / due today + trial loop + remakes                     */
    /* ===================================================================== */

    /** Empty labs shape so the key is always present even if tables missing. */
    private function emptyLabs(): array
    {
        return ['due' => [], 'trial_pending' => [], 'remakes' => ['count' => 0, 'total_cost' => 0]];
    }

    private function labs(int $branchId, Carbon $today): array
    {
        $todayStr = $today->toDateString();

        // Open statuses = work still at lab or in transit
        $openStatuses = ['order_placed', 'impression_sent', 'scan_sent',
                         'trial_received', 'trial_returned'];

        // Overdue (expected back already) OR due exactly today
        $due = DB::table('lab_cases')
            ->join('patients', 'patients.id', '=', 'lab_cases.patient_id')
            ->leftJoin('lab_vendors', 'lab_vendors.id', '=', 'lab_cases.lab_vendor_id')
            ->where('lab_cases.branch_id', $branchId)
            ->whereIn('lab_cases.status', $openStatuses)
            ->whereNull('lab_cases.deleted_at')
            ->whereDate('lab_cases.expected_return_date', '<=', $todayStr)
            ->select([
                'lab_cases.id',
                'lab_cases.case_number',
                'lab_cases.status',
                'lab_cases.priority',
                'lab_cases.trial_round',
                'lab_cases.is_remake',
                'lab_cases.expected_return_date as due_date',
                'patients.name as patient_name',
                'lab_vendors.name as lab_name',
            ])
            ->orderByRaw("CASE WHEN DATE(lab_cases.expected_return_date) < ? THEN 0 ELSE 1 END", [$todayStr])
            ->orderBy('lab_cases.expected_return_date')
            ->limit(15)
            ->get()
            ->map(function ($r) use ($today) {
                $overdue = $r->due_date && Carbon::parse($r->due_date)->lt($today);
                return [
                    'id'           => (int) $r->id,
                    'case_number'  => $r->case_number,
                    'patient_name' => $r->patient_name,
                    'lab_name'     => $r->lab_name,
                    'status'       => $r->status,
                    'priority'     => $r->priority,
                    'is_remake'    => (bool) $r->is_remake,
                    'due_date'     => $r->due_date,
                    'is_overdue'   => $overdue,
                    'overdue_days' => $overdue ? (int) Carbon::parse($r->due_date)->diffInDays($today) : 0,
                ];
            })
            ->all();

        // Trial loop — awaiting doctor trial review
        $trialPending = DB::table('lab_cases')
            ->join('patients', 'patients.id', '=', 'lab_cases.patient_id')
            ->leftJoin('lab_vendors', 'lab_vendors.id', '=', 'lab_cases.lab_vendor_id')
            ->where('lab_cases.branch_id', $branchId)
            ->where('lab_cases.status', 'trial_received')
            ->whereNull('lab_cases.deleted_at')
            ->select([
                'lab_cases.id', 'lab_cases.case_number', 'lab_cases.trial_round',
                'patients.name as patient_name', 'lab_vendors.name as lab_name',
            ])
            ->orderBy('lab_cases.updated_at')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'id'           => (int) $r->id,
                'case_number'  => $r->case_number,
                'patient_name' => $r->patient_name,
                'lab_name'     => $r->lab_name,
                'trial_round'  => $r->trial_round,
            ])
            ->all();

        // Remakes open right now
        $remake = DB::table('lab_cases')
            ->where('lab_cases.branch_id', $branchId)
            ->where('lab_cases.is_remake', true)
            ->whereNotIn('lab_cases.status', ['complete', 'rejected'])
            ->whereNull('lab_cases.deleted_at')
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(lab_cases.estimated_cost), 0) as total_cost')
            ->first();

        return [
            'due'           => $due,
            'trial_pending' => $trialPending,
            'remakes'       => [
                'count'      => (int) ($remake->cnt ?? 0),
                'total_cost' => (float) ($remake->total_cost ?? 0),
            ],
        ];
    }

    /* ===================================================================== */
    /*  Comms list: today's reminders + yesterday's follow-ups + PRM queue   */
    /* ===================================================================== */

    public function comms(int $branchId, Carbon $today, Carbon $yesterday): Collection
    {
        $todayStr     = $today->toDateString();
        $yesterdayStr = $yesterday->toDateString();

        // 1 — Appointment reminders for today
        $reminders = DB::table('appointments')
            ->join('patients', 'patients.id', '=', 'appointments.patient_id')
            ->leftJoin('users as dr', 'dr.id', '=', 'appointments.doctor_id')
            ->where('appointments.branch_id', $branchId)
            ->whereDate('appointments.appointment_date', $todayStr)
            ->whereNotIn('appointments.status', ['cancelled', 'no_show'])
            ->select([
                'appointments.id', 'appointments.patient_id', 'appointments.appointment_time',
                'appointments.type', 'patients.name as patient_name', 'patients.phone',
                'dr.name as doctor_name',
            ])
            ->orderBy('appointments.appointment_time')
            ->get()
            ->map(fn ($r) => [
                'id'           => 'rem_' . $r->id,
                'source_id'    => (int) $r->id,
                'comm_type'    => 'reminder',
                'patient_id'   => (int) $r->patient_id,
                'patient_name' => $r->patient_name,
                'phone'        => $r->phone ?? '—',
                'label'        => 'Appointment Reminder',
                'note'         => 'Remind for ' . ($r->type ? ucfirst(str_replace('_', ' ', $r->type)) : 'appointment')
                                  . ' at ' . ($r->appointment_time ? Carbon::parse($r->appointment_time)->format('h:i A') : '—')
                                  . ' with Dr. ' . ($r->doctor_name ?? '—'),
                'selected'     => true,
            ]);

        // 2 — Treatment follow-ups for yesterday's treated patients
        $followUps = DB::table('appointments')
            ->join('patients', 'patients.id', '=', 'appointments.patient_id')
            ->leftJoin('users as dr', 'dr.id', '=', 'appointments.doctor_id')
            ->leftJoin('treatment_types', 'treatment_types.id', '=', 'appointments.treatment_id')
            ->where('appointments.branch_id', $branchId)
            ->whereDate('appointments.appointment_date', $yesterdayStr)
            ->whereIn('appointments.status', ['done', 'checkout', 'completed'])
            ->whereNotExists(function ($q) use ($yesterdayStr) {
                $q->from('follow_ups')
                  ->whereColumn('follow_ups.patient_id', 'appointments.patient_id')
                  ->where('follow_ups.trigger_type', 'post_treatment')
                  ->whereDate('follow_ups.created_at', '>=', $yesterdayStr)
                  ->whereNull('follow_ups.deleted_at');
            })
            ->select([
                'appointments.id', 'appointments.patient_id', 'appointments.type',
                'patients.name as patient_name', 'patients.phone',
                'dr.name as doctor_name', 'treatment_types.name as treatment_name',
            ])
            ->orderBy('patients.name')
            ->get()
            ->map(fn ($r) => [
                'id'           => 'fu_' . $r->id,
                'source_id'    => (int) $r->id,
                'comm_type'    => 'follow_up',
                'patient_id'   => (int) $r->patient_id,
                'patient_name' => $r->patient_name,
                'phone'        => $r->phone ?? '—',
                'label'        => 'Treatment Follow-up',
                'note'         => 'Follow up on ' . ($r->treatment_name ?? ($r->type ? ucfirst(str_replace('_', ' ', $r->type)) : 'treatment'))
                                  . ' with Dr. ' . ($r->doctor_name ?? '—') . ' (yesterday)',
                'selected'     => true,
            ]);

        // 3 — Pending PRM communication-queue items (due today or overdue)
        $prm = collect();
        try {
            $prm = \App\Models\CommunicationQueue::query()
                ->where('status', '!=', 'closed')
                ->where(function ($q) use ($todayStr) {
                    $q->whereDate('follow_up_date', '<=', $todayStr)
                      ->orWhereNull('follow_up_date');
                })
                ->orderByRaw("FIELD(status,'overdue','pending','waiting_for_patient')")
                ->orderBy('created_at')
                ->limit(50)
                ->get()
                ->map(fn ($c) => [
                    'id'           => 'prm_' . $c->id,
                    'source_id'    => (int) $c->id,
                    'comm_type'    => 'prm',
                    'patient_id'   => $c->patient_id ? (int) $c->patient_id : null,
                    'patient_name' => $c->person_name ?? '—',
                    'phone'        => $c->phone ?? '—',
                    'label'        => $c->comm_type_label ?? ucfirst(str_replace('_', ' ', $c->comm_type ?? 'Communication')),
                    'note'         => ($c->next_action ? 'Next: ' . ucfirst(str_replace('_', ' ', $c->next_action)) . ' · ' : '')
                                      . ucfirst($c->channel ?? '') . ($c->status === 'overdue' ? ' · ⚠ Overdue' : ''),
                    'selected'     => false,
                ]);
        } catch (\Throwable $e) {
            $prm = collect();
        }

        return $reminders->concat($followUps)->concat($prm);
    }

    /* ===================================================================== */
    /*  Helper: run a section, swallow failures into a safe default          */
    /* ===================================================================== */

    private function safe(callable $fn, $default = null)
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            return $default;
        }
    }
}
