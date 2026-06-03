<?php

declare(strict_types=1);

namespace App\Modules\Huddle\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Huddle\Resources\HuddleBoardResource;
use App\Modules\Huddle\Services\HuddleAggregationService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HuddleController extends Controller
{
    public function __construct(
        private readonly HuddleAggregationService $aggregationService,
    ) {}

    /**
     * GET /huddle
     * Browser  → Blade view with all required variables
     * AJAX/JSON → full board JSON payload
     */
    public function index(Request $request)
    {
        if ($request->expectsJson() || $request->ajax()) {
            $user     = auth()->user();
            $boardDTO = $this->aggregationService->buildBoardForUser(
                $user->branch_id,
                $user->role,
            );
            return (new HuddleBoardResource($boardDTO))
                ->response()
                ->setStatusCode(200);
        }

        $user      = auth()->user();
        $branchId  = $user->branch_id;
        $today     = Carbon::today();
        $yesterday = Carbon::yesterday();

        // ── Today's appointments ──────────────────────────────────────────────
        $todaysAppointments = DB::table('appointments')
            ->join('patients', 'patients.id', '=', 'appointments.patient_id')
            ->leftJoin('users as doctors', 'doctors.id', '=', 'appointments.doctor_id')
            ->leftJoin('treatment_types', 'treatment_types.id', '=', 'appointments.treatment_id')
            ->where('appointments.branch_id', $branchId)
            ->whereDate('appointments.appointment_date', $today->toDateString())
            ->select([
                'appointments.id',
                'appointments.patient_id',
                'appointments.appointment_time',
                'appointments.status',
                'appointments.staff_instruction',
                'appointments.type',
                'patients.name as patient_name',
                'patients.medical_alert',
                'doctors.name as doctor_name',
                'treatment_types.name as treatment_name',
            ])
            ->orderBy('appointments.appointment_time')
            ->get()
            ->map(function ($row) {
                $row->patient  = (object) ['name' => $row->patient_name, 'medical_alert' => $row->medical_alert];
                $row->doctor   = (object) ['name' => $row->doctor_name];
                $row->treatment = $row->treatment_name ? (object) ['name' => $row->treatment_name] : null;
                return $row;
            });

        // ── Yesterday summary ─────────────────────────────────────────────────
        $yesterdayAppts = DB::table('appointments')
            ->where('branch_id', $branchId)
            ->whereDate('appointment_date', $yesterday->toDateString())
            ->whereIn('status', ['done', 'checkout'])
            ->select('treatment_id')
            ->get();

        $yesterdaySummary = [
            'patients_treated' => $yesterdayAppts->count(),
            'treatments_done'  => $yesterdayAppts->groupBy('treatment_id')->map->count(),
            'lab_sent'         => 0,
            'lab_received'     => 0,
        ];

        // ── Yesterday appointments + visit log status ─────────────────────────
        $yesterdaysAppointments = DB::table('appointments')
            ->join('patients', 'patients.id', '=', 'appointments.patient_id')
            ->leftJoin('users as doctors', 'doctors.id', '=', 'appointments.doctor_id')
            ->leftJoin('treatment_types', 'treatment_types.id', '=', 'appointments.treatment_id')
            ->leftJoin('consultations', function ($join) use ($yesterday) {
                $join->on('consultations.patient_id', '=', 'appointments.patient_id')
                    ->on('consultations.doctor_id',  '=', 'appointments.doctor_id')
                    ->whereDate('consultations.consultation_date', $yesterday->toDateString())
                    ->whereNull('consultations.deleted_at');
            })
            ->where('appointments.branch_id', $branchId)
            ->whereDate('appointments.appointment_date', $yesterday->toDateString())
            ->select([
                'appointments.id',
                'appointments.patient_id',
                'appointments.doctor_id',
                'appointments.appointment_time',
                'appointments.status as appointment_status',
                'appointments.type',
                'patients.name as patient_name',
                'patients.medical_alert',
                'doctors.name as doctor_name',
                'treatment_types.name as treatment_name',
                'consultations.id as consultation_id',
                'consultations.status as consultation_status',
                'consultations.chief_complaint',
                'consultations.visit_type',
                'consultations.primary_diagnosis',
                'consultations.finishing_notes',
            ])
            ->orderBy('appointments.appointment_time')
            ->get()
            ->map(function ($row) {
                $skippedStatuses    = ['cancelled', 'no_show'];
                $visitLogged        = $row->consultation_id !== null;
                $shouldHaveVisit    = !in_array($row->appointment_status, $skippedStatuses);
                $row->visit_logged  = $visitLogged;
                $row->visit_flag    = $shouldHaveVisit && !$visitLogged;
                $row->patient       = (object) ['name' => $row->patient_name, 'medical_alert' => $row->medical_alert];
                $row->doctor        = (object) ['name' => $row->doctor_name];
                $row->treatment     = $row->treatment_name ? (object) ['name' => $row->treatment_name] : null;
                return $row;
            });

        // ── Today's treatment visits ──────────────────────────────────────────
        // treatment_visits has no branch_id; scope via patients with appointments at this branch today
        $branchPatientIdsToday = DB::table('appointments')
            ->where('branch_id', $branchId)
            ->whereDate('appointment_date', $today->toDateString())
            ->pluck('patient_id')
            ->unique();

        $todaysTreatmentVisits = DB::table('treatment_visits')
            ->join('patients', 'patients.id', '=', 'treatment_visits.patient_id')
            ->leftJoin('users as tv_doctors', 'tv_doctors.id', '=', 'treatment_visits.doctor_id')
            ->whereIn('treatment_visits.patient_id', $branchPatientIdsToday)
            ->whereDate('treatment_visits.visit_date', $today->toDateString())
            ->select([
                'treatment_visits.id',
                'treatment_visits.patient_id',
                'treatment_visits.visit_date',
                'treatment_visits.visit_type',
                'treatment_visits.treatment_name',
                'treatment_visits.status',
                'patients.name as patient_name',
                'tv_doctors.name as doctor_name',
            ])
            ->orderBy('treatment_visits.visit_date')
            ->get()
            ->map(function ($row) {
                $row->patient = (object) ['name' => $row->patient_name, 'medical_alert' => null];
                $row->doctor  = (object) ['name' => $row->doctor_name];
                return $row;
            });

        // ── Today's consultations ─────────────────────────────────────────────
        $todaysConsultations = DB::table('consultations')
            ->join('patients', 'patients.id', '=', 'consultations.patient_id')
            ->leftJoin('users as cons_doctors', 'cons_doctors.id', '=', 'consultations.doctor_id')
            ->join('appointments', function ($join) use ($branchId, $today) {
                $join->on('appointments.patient_id', '=', 'consultations.patient_id')
                    ->where('appointments.branch_id', $branchId)
                    ->whereDate('appointments.appointment_date', $today->toDateString());
            })
            ->whereDate('consultations.consultation_date', $today->toDateString())
            ->whereNull('consultations.deleted_at')
            ->select([
                'consultations.id',
                'consultations.patient_id',
                'consultations.consultation_date',
                'consultations.visit_type',
                'consultations.chief_complaint',
                'consultations.status',
                'patients.name as patient_name',
                'cons_doctors.name as doctor_name',
            ])
            ->distinct()
            ->get()
            ->map(function ($row) {
                $row->patient = (object) ['name' => $row->patient_name];
                $row->doctor  = (object) ['name' => $row->doctor_name];
                return $row;
            });

        // ── Yesterday's treatment visits ──────────────────────────────────────
        $branchPatientIdsYesterday = DB::table('appointments')
            ->where('branch_id', $branchId)
            ->whereDate('appointment_date', $yesterday->toDateString())
            ->pluck('patient_id')
            ->unique();

        $yesterdaysTreatmentVisits = DB::table('treatment_visits')
            ->join('patients', 'patients.id', '=', 'treatment_visits.patient_id')
            ->leftJoin('users as ytv_doctors', 'ytv_doctors.id', '=', 'treatment_visits.doctor_id')
            ->whereIn('treatment_visits.patient_id', $branchPatientIdsYesterday)
            ->whereDate('treatment_visits.visit_date', $yesterday->toDateString())
            ->select([
                'treatment_visits.id',
                'treatment_visits.patient_id',
                'treatment_visits.visit_date',
                'treatment_visits.visit_type',
                'treatment_visits.treatment_name',
                'treatment_visits.status',
                'patients.name as patient_name',
                'ytv_doctors.name as doctor_name',
            ])
            ->orderBy('treatment_visits.visit_date')
            ->get()
            ->map(function ($row) {
                $row->patient = (object) ['name' => $row->patient_name];
                $row->doctor  = (object) ['name' => $row->doctor_name];
                return $row;
            });

        // ── Yesterday's consultations ─────────────────────────────────────────
        $yesterdaysConsultations = DB::table('consultations')
            ->join('patients', 'patients.id', '=', 'consultations.patient_id')
            ->leftJoin('users as ycons_doctors', 'ycons_doctors.id', '=', 'consultations.doctor_id')
            ->whereDate('consultations.consultation_date', $yesterday->toDateString())
            ->whereNull('consultations.deleted_at')
            ->select([
                'consultations.id',
                'consultations.patient_id',
                'consultations.consultation_date',
                'consultations.visit_type',
                'consultations.chief_complaint',
                'consultations.primary_diagnosis',
                'consultations.status',
                'patients.name as patient_name',
                'ycons_doctors.name as doctor_name',
            ])
            ->get()
            ->map(function ($row) {
                $row->patient = (object) ['name' => $row->patient_name];
                $row->doctor  = (object) ['name' => $row->doctor_name];
                return $row;
            });

        // ── Labs due today (stub) ─────────────────────────────────────────────
        $labsDueToday = collect();

        // ── Critical alerts — patient ─────────────────────────────────────────
        $criticalAlerts = DB::table('patient_alerts')
            ->join('patients', 'patients.id', '=', 'patient_alerts.patient_id')
            ->join('appointments', 'appointments.patient_id', '=', 'patient_alerts.patient_id')
            ->where('appointments.branch_id', $branchId)
            ->whereDate('appointments.appointment_date', $today->toDateString())
            ->where('patient_alerts.is_active', true)
            ->select(['patients.name', 'patient_alerts.alert'])
            ->get()
            ->map(fn($a) => [
                'type'    => 'patient',
                'message' => $a->name . ': ' . $a->alert,
                'level'   => 'error',
                'icon'    => 'alert-triangle',
            ]);

        // ── Critical alerts — inventory ───────────────────────────────────────
        // Only query if inventory_items table exists (graceful degradation)
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
                ->get();

            $inventoryAlerts = $invItems->map(fn($item) => [
                'type'    => 'inventory',
                'message' => $item->product_name,
                'qty'     => $item->total_qty,
                'min_qty' => $item->minimum_qty,
                'level'   => $item->total_qty <= 0 ? 'error' : 'warning',
                'icon'    => 'package',
            ]);

            $criticalAlerts = $criticalAlerts->merge($inventoryAlerts);
        } catch (\Exception $e) {
            // Inventory tables not yet migrated — silently skip
        }

        // ── Huddle notes ──────────────────────────────────────────────────────
        $huddleNotes = DB::table('huddle_notes')
            ->whereDate('created_at', $today->toDateString())
            ->where('branch_id', $branchId)
            ->get()
            ->groupBy('category')
            ->map(fn($group) => $group->map(fn($n) => [
                'body'                 => $n->body,
                'author'               => ['name' => DB::table('users')->where('id', $n->user_id)->value('name') ?? 'Team'],
                'created_at_formatted' => Carbon::parse($n->created_at)->format('H:i'),
            ]));

        // ── My tasks ──────────────────────────────────────────────────────────
        $myTasks = DB::table('tasks')
            ->where('assigned_to', $user->id)
            ->where('branch_id', $branchId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->select(['id', 'title', 'priority', 'due_time', 'status'])
            ->orderBy('priority', 'desc')
            ->get()
            ->map(fn($t) => array_merge((array) $t, ['done' => false]));

        // ── Comms list (stub) ─────────────────────────────────────────────────
        $commList = collect();

        // ── Booking modal variables ───────────────────────────────────────────
        $doctors = User::where('branch_id', $branchId)
            ->where('role', 'doctor')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $treatmentCategories = DB::table('treatment_categories')
            ->orderBy('name')
            ->get(['id', 'name']);

        $timeSlots = [];
        for ($h = 8; $h <= 20; $h++) {
            $timeSlots[] = sprintf('%02d:00', $h);
            $timeSlots[] = sprintf('%02d:30', $h);
        }

        $commController = new \App\Http\Controllers\Communication\HuddleController();
        $commCounts     = $commController->buildCounts();
        $commOverdue    = $commController->buildOverdueItems();
        $commAlerts     = $commController->buildAlerts();

        return view('huddle.index', compact(
            'today',
            'yesterday',
            'todaysAppointments',
            'todaysTreatmentVisits',
            'todaysConsultations',
            'yesterdaySummary',
            'yesterdaysAppointments',
            'yesterdaysTreatmentVisits',
            'yesterdaysConsultations',
            'labsDueToday',
            'criticalAlerts',
            'huddleNotes',
            'myTasks',
            'commList',
            'doctors',
            'treatmentCategories',
            'timeSlots',
            'commCounts',
            'commOverdue',
            'commAlerts',
        ));
    }

    /**
     * PATCH /huddle/appointments/{id}/instruction
     */
    public function updateInstruction(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'staff_instruction' => ['nullable', 'string', 'max:1000'],
        ]);

        $updated = DB::table('appointments')
            ->where('id', $id)
            ->where('branch_id', auth()->user()->branch_id)
            ->update(['staff_instruction' => $request->input('staff_instruction')]);

        if (!$updated) {
            return response()->json(['message' => 'Appointment not found.'], 404);
        }

        return response()->json(['message' => 'Saved.']);
    }

    /**
     * GET /huddle/accountability
     */
    public function accountability(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Not yet implemented.']);
    }

    /**
     * POST /huddle/notes
     */
    public function storeNote(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Not yet implemented.']);
    }
}
