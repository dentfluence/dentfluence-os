<?php

declare(strict_types=1);

namespace App\Modules\Huddle\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Huddle\Resources\HuddleBoardResource;
use App\Modules\Huddle\Services\HuddleAggregationService;
use App\Models\User;
use Carbon\Carbon;
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

        // ── Yesterday appointments + visit log (consultation) status ──────────
        // LEFT JOIN consultations on patient_id + doctor_id + date
        // Flags "Visit Not Logged" when doctor had appointment but no consultation
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

        // ── Labs due today (stub) ─────────────────────────────────────────────
        $labsDueToday = collect();

        // ── Critical alerts ───────────────────────────────────────────────────
        $criticalAlerts = DB::table('patient_alerts')
            ->join('patients', 'patients.id', '=', 'patient_alerts.patient_id')
            ->join('appointments', 'appointments.patient_id', '=', 'patient_alerts.patient_id')
            ->where('appointments.branch_id', $branchId)
            ->whereDate('appointments.appointment_date', $today->toDateString())
            ->where('patient_alerts.is_active', true)
            ->select(['patients.name', 'patient_alerts.alert'])
            ->get()
            ->map(fn ($a) => [
                'message' => $a->name . ': ' . $a->alert,
                'level'   => 'error',
                'icon'    => 'alert-triangle',
            ]);

        // ── Huddle notes ──────────────────────────────────────────────────────
        $huddleNotes = DB::table('huddle_notes')
            ->whereDate('created_at', $today->toDateString())
            ->where('branch_id', $branchId)
            ->get()
            ->groupBy('category')
            ->map(fn ($group) => $group->map(fn ($n) => [
                'body'                => $n->body,
                'author'              => ['name' => DB::table('users')->where('id', $n->user_id)->value('name') ?? 'Team'],
                'created_at_formatted'=> Carbon::parse($n->created_at)->format('H:i'),
            ]));

        // ── My tasks ──────────────────────────────────────────────────────────
        $myTasks = DB::table('tasks')
            ->where('assigned_to', $user->id)
            ->where('branch_id', $branchId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->select(['id', 'title', 'priority', 'due_time', 'status'])
            ->orderBy('priority', 'desc')
            ->get()
            ->map(fn ($t) => array_merge((array) $t, ['done' => false]));

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

        return view('huddle.index', compact(
            'today', 'yesterday',
            'todaysAppointments', 'yesterdaySummary', 'yesterdaysAppointments',
            'labsDueToday', 'criticalAlerts',
            'huddleNotes', 'myTasks', 'commList',
            'doctors', 'treatmentCategories', 'timeSlots',
        ));
    }

    // -------------------------------------------------------------------------
    // Keep existing methods below — do not remove:
    //   accountability()
    //   updateInstruction()
    //   storeNote()
    // -------------------------------------------------------------------------
}