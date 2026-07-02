<?php

declare(strict_types=1);

namespace App\Modules\Huddle\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Huddle\Resources\HuddleBoardResource;
use App\Modules\Huddle\Services\HuddleAggregationService;
use App\Models\CommunicationQueue;
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
                'appointments.doctor_id',
                'appointments.appointment_time',
                'appointments.status',
                'appointments.staff_instruction',
                'appointments.type',
                'patients.name as patient_name',
                'patients.medical_alert',
                'doctors.name as doctor_name',
                'doctors.color as doctor_color',
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
            ->get();

        // ── Work out which appointments actually had their visit logged ───────────
        // A visit counts as "logged" if EITHER a consultation OR a treatment visit
        // exists for it. We match first on appointment_id (the reliable link) and fall
        // back to patient + yesterday's date (covers records saved without an appointment
        // link). This is broader than the consultations join above, which only pulls
        // clinical details when patient + doctor + date all line up.
        $yApptIds = $yesterdaysAppointments->pluck('id');
        $yPatIds  = $yesterdaysAppointments->pluck('patient_id')->unique();
        $yDateStr = $yesterday->toDateString();

        $consByAppt = DB::table('consultations')->whereNull('deleted_at')
            ->whereIn('appointment_id', $yApptIds)->pluck('appointment_id')->flip();
        $consByPat  = DB::table('consultations')->whereNull('deleted_at')
            ->whereIn('patient_id', $yPatIds)->whereDate('consultation_date', $yDateStr)
            ->pluck('patient_id')->flip();
        $tvByAppt   = DB::table('treatment_visits')->whereNull('deleted_at')
            ->whereIn('appointment_id', $yApptIds)->pluck('appointment_id')->flip();
        $tvByPat    = DB::table('treatment_visits')->whereNull('deleted_at')
            ->whereIn('patient_id', $yPatIds)->whereDate('visit_date', $yDateStr)
            ->pluck('patient_id')->flip();

        $yesterdaysAppointments = $yesterdaysAppointments
            ->map(function ($row) use ($consByAppt, $consByPat, $tvByAppt, $tvByPat) {
                $skippedStatuses = ['cancelled', 'no_show'];

                $hasConsult = $row->consultation_id !== null
                    || isset($consByAppt[$row->id])
                    || isset($consByPat[$row->patient_id]);
                $hasVisit   = isset($tvByAppt[$row->id])
                    || isset($tvByPat[$row->patient_id]);

                $visitLogged       = $hasConsult || $hasVisit;
                $shouldHaveVisit   = !in_array($row->appointment_status, $skippedStatuses);
                $row->visit_logged = $visitLogged;
                $row->visit_flag   = $shouldHaveVisit && !$visitLogged;
                // Which record backs the "logged" badge (for the label shown in the view)
                $row->visit_source = $hasConsult ? 'consultation' : ($hasVisit ? 'treatment_visit' : null);
                $row->patient      = (object) ['name' => $row->patient_name, 'medical_alert' => $row->medical_alert];
                $row->doctor       = (object) ['name' => $row->doctor_name];
                $row->treatment    = $row->treatment_name ? (object) ['name' => $row->treatment_name] : null;
                $row->next_appt    = null; // enriched below
                return $row;
            });

        // ── Enrich yesterday's appointments with each patient's next appointment ──
        $yesterdayPatientIds = $yesterdaysAppointments->pluck('patient_id')->unique()->values();
        if ($yesterdayPatientIds->isNotEmpty()) {
            $nextAppts = DB::table('appointments')
                ->join('users as na_doctors', 'na_doctors.id', '=', 'appointments.doctor_id')
                ->leftJoin('treatment_types as na_tt', 'na_tt.id', '=', 'appointments.treatment_id')
                ->whereIn('appointments.patient_id', $yesterdayPatientIds)
                ->where('appointments.branch_id', $branchId)
                ->whereDate('appointments.appointment_date', '>', $yesterday->toDateString())
                ->whereNotIn('appointments.status', ['cancelled', 'no_show'])
                ->select([
                    'appointments.patient_id',
                    'appointments.appointment_date',
                    'appointments.appointment_time',
                    'appointments.type',
                    'na_doctors.name as doctor_name',
                    'na_tt.name as treatment_name',
                ])
                ->orderBy('appointments.appointment_date')
                ->orderBy('appointments.appointment_time')
                ->get()
                ->groupBy('patient_id')
                ->map(fn($g) => $g->first()); // earliest next appt per patient

            $yesterdaysAppointments = $yesterdaysAppointments->map(function ($row) use ($nextAppts) {
                $row->next_appt = $nextAppts->get($row->patient_id);
                return $row;
            });
        }

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
        // Scoped to patients who had treatment visits but NO appointment card already shown above.
        // ($yesterdayPatientIds already computed from $yesterdaysAppointments above)
        $branchPatientIdsYesterday = DB::table('appointments')
            ->where('branch_id', $branchId)
            ->whereDate('appointment_date', $yesterday->toDateString())
            ->pluck('patient_id')
            ->unique();

        $yesterdaysTreatmentVisits = DB::table('treatment_visits')
            ->join('patients', 'patients.id', '=', 'treatment_visits.patient_id')
            ->leftJoin('users as ytv_doctors', 'ytv_doctors.id', '=', 'treatment_visits.doctor_id')
            ->whereIn('treatment_visits.patient_id', $branchPatientIdsYesterday)
            ->whereNotIn('treatment_visits.patient_id', $yesterdayPatientIds->isNotEmpty() ? $yesterdayPatientIds->toArray() : [0])
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
        // Only show consultations for patients WITHOUT an appointment card (walk-in consults).
        // Patients with appointments already have their consultation data embedded in the card above.
        $yesterdaysConsultations = DB::table('consultations')
            ->join('patients', 'patients.id', '=', 'consultations.patient_id')
            ->leftJoin('users as ycons_doctors', 'ycons_doctors.id', '=', 'consultations.doctor_id')
            ->whereDate('consultations.consultation_date', $yesterday->toDateString())
            ->whereNotIn('consultations.patient_id', $yesterdayPatientIds->isNotEmpty() ? $yesterdayPatientIds->toArray() : [0])
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

        // ── Labs: overdue + due today + trial loop + remakes ─────────────────
        // Graceful degradation: if lab tables aren't migrated yet, silently skip.
        try {
            // Open statuses in v3 workflow (work still at lab or in transit)
            $labOpenStatuses = ['order_placed', 'impression_sent', 'scan_sent',
                                'trial_received', 'trial_returned'];

            $labsDueToday = DB::table('lab_cases')
                ->join('patients', 'patients.id', '=', 'lab_cases.patient_id')
                ->leftJoin('lab_vendors', 'lab_vendors.id', '=', 'lab_cases.lab_vendor_id')
                ->where('lab_cases.branch_id', $branchId)
                ->whereIn('lab_cases.status', $labOpenStatuses)
                ->whereNull('lab_cases.deleted_at')
                ->where(function ($q) use ($today) {
                    // Overdue (expected date has passed) OR due exactly today
                    $q->whereDate('lab_cases.expected_return_date', '<=', $today->toDateString());
                })
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
                // Overdue first (oldest expected date first), then due today
                ->orderByRaw("CASE WHEN DATE(lab_cases.expected_return_date) < ? THEN 0 ELSE 1 END", [$today->toDateString()])
                ->orderBy('lab_cases.expected_return_date')
                ->limit(10)
                ->get()
                ->map(function ($row) use ($today) {
                    $row->is_overdue = $row->due_date
                        && \Carbon\Carbon::parse($row->due_date)->lt($today);
                    $row->overdue_days = $row->is_overdue
                        ? (int) \Carbon\Carbon::parse($row->due_date)->diffInDays($today)
                        : 0;
                    return $row;
                });

            // Trial loop cases (awaiting doctor trial review)
            $labTrialPending = DB::table('lab_cases')
                ->join('patients', 'patients.id', '=', 'lab_cases.patient_id')
                ->leftJoin('lab_vendors', 'lab_vendors.id', '=', 'lab_cases.lab_vendor_id')
                ->where('lab_cases.branch_id', $branchId)
                ->whereIn('lab_cases.status', ['trial_received'])
                ->whereNull('lab_cases.deleted_at')
                ->select([
                    'lab_cases.id', 'lab_cases.case_number', 'lab_cases.status',
                    'lab_cases.trial_round', 'patients.name as patient_name',
                    'lab_vendors.name as lab_name',
                ])
                ->orderBy('lab_cases.updated_at')
                ->limit(5)
                ->get();

            // Remake / repeat-work cases open right now
            $labRemakesOpen = DB::table('lab_cases')
                ->join('patients', 'patients.id', '=', 'lab_cases.patient_id')
                ->where('lab_cases.branch_id', $branchId)
                ->where('lab_cases.is_remake', true)
                ->whereNotIn('lab_cases.status', ['complete', 'rejected'])
                ->whereNull('lab_cases.deleted_at')
                ->selectRaw('COUNT(*) as cnt, SUM(lab_cases.estimated_cost) as total_cost')
                ->first();

        } catch (\Exception $e) {
            $labsDueToday    = collect();
            $labTrialPending = collect();
            $labRemakesOpen  = null;
        }

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

        // ── Branch tasks — due today or overdue (all staff) ──────────────────
        // Shown in the Huddle Tasks column so the whole team can see what's due.
        $myTasks = DB::table('tasks')
            ->leftJoin('users as assignee', 'assignee.id', '=', 'tasks.assigned_to')
            ->where('tasks.branch_id', $branchId)
            ->whereIn('tasks.status', ['pending', 'in_progress'])
            ->where(function ($q) use ($today) {
                // Due today OR overdue (so nothing falls through the cracks)
                $q->whereDate('tasks.due_date', $today->toDateString())
                  ->orWhereDate('tasks.due_date', '<', $today->toDateString());
            })
            ->select([
                'tasks.id',
                'tasks.title',
                'tasks.priority',
                'tasks.due_time',
                'tasks.due_date',
                'tasks.status',
                'tasks.category',
                'assignee.name as assignee_name',
            ])
            ->orderByRaw("FIELD(tasks.priority, 'urgent', 'high', 'medium', 'low')")
            ->orderBy('tasks.due_date')
            ->get()
            ->map(fn($t) => array_merge((array) $t, ['done' => false]));

        // ── Comms list: Reminders (today's appts) + Follow-ups (yesterday's treated) ──
        // Section 1 — Appointment Reminders for today (shown first)
        $reminders = DB::table('appointments')
            ->join('patients', 'patients.id', '=', 'appointments.patient_id')
            ->leftJoin('users as dr', 'dr.id', '=', 'appointments.doctor_id')
            ->where('appointments.branch_id', $branchId)
            ->whereDate('appointments.appointment_date', $today->toDateString())
            ->whereNotIn('appointments.status', ['cancelled', 'no_show'])
            ->select([
                'appointments.id',
                'appointments.patient_id',
                'appointments.appointment_time',
                'appointments.type',
                'patients.name as patient_name',
                'patients.phone',
                'dr.name as doctor_name',
            ])
            ->orderBy('appointments.appointment_time')
            ->get()
            ->map(fn($r) => [
                'id'           => 'rem_' . $r->id,         // prefix to avoid ID clash
                'source_id'    => $r->id,
                'source_type'  => 'appointment',
                'comm_type'    => 'reminder',
                'patient_id'   => $r->patient_id,
                'patient_name' => $r->patient_name,
                'phone'        => $r->phone ?? '—',
                'label'        => 'Appointment Reminder',
                'note'         => 'Remind for ' . ($r->type ? ucfirst(str_replace('_', ' ', $r->type)) : 'appointment')
                                  . ' at ' . ($r->appointment_time ? \Carbon\Carbon::parse($r->appointment_time)->format('h:i A') : '—')
                                  . ' with Dr. ' . ($r->doctor_name ?? '—'),
                'selected'     => true,  // pre-checked by default
            ]);

        // Section 2 — Treatment Follow-ups for yesterday's patients (shown after reminders)
        $followUps = DB::table('appointments')
            ->join('patients', 'patients.id', '=', 'appointments.patient_id')
            ->leftJoin('users as dr', 'dr.id', '=', 'appointments.doctor_id')
            ->leftJoin('treatment_types', 'treatment_types.id', '=', 'appointments.treatment_id')
            ->where('appointments.branch_id', $branchId)
            ->whereDate('appointments.appointment_date', $yesterday->toDateString())
            ->whereIn('appointments.status', ['done', 'checkout', 'completed'])
            // skip if a FollowUp already exists for this patient+trigger
            ->whereNotExists(function ($q) use ($yesterday) {
                $q->from('follow_ups')
                  ->whereColumn('follow_ups.patient_id', 'appointments.patient_id')
                  ->where('follow_ups.trigger_type', 'post_treatment')
                  ->whereDate('follow_ups.created_at', '>=', $yesterday->toDateString())
                  ->whereNull('follow_ups.deleted_at');
            })
            ->select([
                'appointments.id',
                'appointments.patient_id',
                'appointments.type',
                'patients.name as patient_name',
                'patients.phone',
                'dr.name as doctor_name',
                'treatment_types.name as treatment_name',
            ])
            ->orderBy('patients.name')
            ->get()
            ->map(fn($r) => [
                'id'           => 'fu_' . $r->id,
                'source_id'    => $r->id,
                'source_type'  => 'appointment',
                'comm_type'    => 'follow_up',
                'patient_id'   => $r->patient_id,
                'patient_name' => $r->patient_name,
                'phone'        => $r->phone ?? '—',
                'label'        => 'Treatment Follow-up',
                'note'         => 'Follow up on ' . ($r->treatment_name ?? ($r->type ? ucfirst(str_replace('_', ' ', $r->type)) : 'treatment'))
                                  . ' with Dr. ' . ($r->doctor_name ?? '—') . ' (yesterday)',
                'selected'     => true,
            ]);

        // Section 3 — Pending PRM Communication Queue items (due today or overdue)
        $prmComms = CommunicationQueue::query()
            ->where('status', '!=', 'closed')
            ->where(function ($q) use ($today) {
                $q->whereDate('follow_up_date', '<=', $today->toDateString())
                  ->orWhereNull('follow_up_date');
            })
            ->orderByRaw("FIELD(status,'overdue','pending','waiting_for_patient')")
            ->orderBy('created_at')
            ->get()
            ->map(fn($c) => [
                'id'           => 'prm_' . $c->id,
                'source_id'    => $c->id,
                'source_type'  => 'prm',
                'comm_type'    => 'prm',
                'patient_id'   => $c->patient_id,
                'patient_name' => $c->person_name ?? '—',
                'phone'        => $c->phone ?? '—',
                'label'        => $c->comm_type_label ?? ucfirst(str_replace('_', ' ', $c->comm_type ?? 'Communication')),
                'note'         => ($c->next_action ? 'Next: ' . ucfirst(str_replace('_', ' ', $c->next_action)) . ' · ' : '')
                                  . ucfirst($c->channel ?? '') . ($c->status === 'overdue' ? ' · ⚠ Overdue' : ''),
                'selected'     => false,  // not pre-checked — staff picks which to action
            ]);

        // Reminders first, then follow-ups, then PRM comms
        $commList = $reminders->concat($followUps)->concat($prmComms);

        // ── Staff list for task quick-add ────────────────────────────────────
        $staff = User::where('branch_id', $branchId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

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

        // ── Phase 7: Relationship Actions from TodayActionsEngine ─────────────
        // Additive — fault-tolerant, never breaks the huddle if engine fails.
        try {
            $relationshipItems = $this->aggregationService->getRelationshipItems();
        } catch (\Throwable $e) {
            $relationshipItems = [];
        }

        // Slice E4 (Workstream E) — shared read: the Daily Huddle shows the
        // Today's Actions projection summary instead of running its own queries.
        $todaySnapshot = app(\App\Services\Relationship\TodayActionsProjector::class)->summary();

        return view('huddle.index', compact(
            'today',
            'todaySnapshot',
            'staff',
            'yesterday',
            'todaysAppointments',
            'todaysTreatmentVisits',
            'todaysConsultations',
            'yesterdaySummary',
            'yesterdaysAppointments',
            'yesterdaysTreatmentVisits',
            'yesterdaysConsultations',
            'labsDueToday',
            'labTrialPending',
            'labRemakesOpen',
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
            'relationshipItems',
        ));
    }

    /**
     * GET /huddle/report
     * Period-driven performance report shared by the Weekly / Monthly /
     * Quarterly / Annual tabs. The period determines a rolling date window:
     *   week    = last 7 days     month = last 30 days
     *   quarter = last 90 days    year  = last 365 days
     *   custom  = ?from=YYYY-MM-DD&to=YYYY-MM-DD
     * Each tab is just this same view with a different ?period= value.
     */
    public function report(Request $request)
    {
        $user     = auth()->user();
        $branchId = $user->branch_id;
        $today    = Carbon::today();

        // ── Resolve the period into a [from, to] window ───────────────────────
        $period = $request->query('period', 'week');
        $allowed = ['week', 'month', 'quarter', 'year', 'custom'];
        if (! in_array($period, $allowed, true)) {
            $period = 'week';
        }

        if ($period === 'custom') {
            // Validate custom range; fall back to last 7 days on bad input
            try {
                $from = $request->filled('from')
                    ? Carbon::parse($request->query('from'))->startOfDay()
                    : $today->copy()->subDays(6)->startOfDay();
                $to = $request->filled('to')
                    ? Carbon::parse($request->query('to'))->endOfDay()
                    : $today->copy()->endOfDay();
            } catch (\Exception $e) {
                $from = $today->copy()->subDays(6)->startOfDay();
                $to   = $today->copy()->endOfDay();
            }
            // Guard against reversed dates
            if ($from->gt($to)) {
                [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
            }
        } else {
            $days = match ($period) {
                'month'   => 30,
                'quarter' => 90,
                'year'    => 365,
                default   => 7,   // week
            };
            $from = $today->copy()->subDays($days - 1)->startOfDay();
            $to   = $today->copy()->endOfDay();
        }

        $periodLabels = [
            'week'    => 'Last 7 Days',
            'month'   => 'Last 30 Days',
            'quarter' => 'Last 90 Days',
            'year'    => 'Last 365 Days',
            'custom'  => 'Custom Range',
        ];
        $periodLabel = $periodLabels[$period];
        $fromDate    = $from->toDateString();
        $toDate      = $to->toDateString();
        $rangeDays   = (int) $from->diffInDays($to) + 1;

        // ── Previous comparable window (same length, immediately before) ──────
        // Used to show period-over-period trend chips on each card.
        $prevTo       = $from->copy()->subDay()->endOfDay();
        $prevFrom     = $from->copy()->subDays($rangeDays)->startOfDay();
        $prevFromDate = $prevFrom->toDateString();
        $prevToDate   = $prevTo->toDateString();

        // ══ 1. COLLECTIONS & REVENUE ══════════════════════════════════════════
        // Source of truth = finance_transactions (same as the AI KpiReport tool).
        // finance_transactions is clinic-wide (clinic_id, no branch_id column).
        $collected   = 0.0;  $prevCollected = 0.0;
        $refunds     = 0.0;  $prevRefunds   = 0.0;
        $byMode      = collect();
        $txCount     = 0;
        if (class_exists(\App\Models\Finance\FinanceTransaction::class)) {
            $income = \App\Models\Finance\FinanceTransaction::query()
                ->where('type', 'income')
                ->where('status', 'active')
                ->whereBetween('transaction_date', [$fromDate, $toDate]);
            $collected = (float) (clone $income)->sum('amount');
            $txCount   = (clone $income)->count();

            // Breakdown by payment mode (cash / upi / card / …)
            $byMode = (clone $income)
                ->selectRaw('payment_mode, SUM(amount) as total, COUNT(*) as cnt')
                ->groupBy('payment_mode')
                ->orderByDesc('total')
                ->get();

            $refunds = (float) \App\Models\Finance\FinanceTransaction::query()
                ->where('type', 'refund')
                ->where('status', 'active')
                ->whereBetween('transaction_date', [$fromDate, $toDate])
                ->sum('amount');

            // Previous-window equivalents (for trend chips)
            $prevCollected = (float) \App\Models\Finance\FinanceTransaction::query()
                ->where('type', 'income')->where('status', 'active')
                ->whereBetween('transaction_date', [$prevFromDate, $prevToDate])
                ->sum('amount');
            $prevRefunds = (float) \App\Models\Finance\FinanceTransaction::query()
                ->where('type', 'refund')->where('status', 'active')
                ->whereBetween('transaction_date', [$prevFromDate, $prevToDate])
                ->sum('amount');
        }
        $netCollected     = $collected - $refunds;
        $prevNetCollected = $prevCollected - $prevRefunds;
        $avgPerDay        = $rangeDays > 0 ? $collected / $rangeDays : 0;
        $prevAvgPerDay    = $rangeDays > 0 ? $prevCollected / $rangeDays : 0;

        // Outstanding is point-in-time (now), scoped to this branch — no trend
        $outstanding = (float) DB::table('patients')
            ->where('branch_id', $branchId)
            ->sum('outstanding_balance');

        $collectionsCards = [
            ['label' => 'Total Collected', 'value' => $this->fmtMoney($collected), 'sub' => $txCount . ' transactions', 'tone' => 'green', 'trend' => $this->trend($collected, $prevCollected)],
            ['label' => 'Net of Refunds',  'value' => $this->fmtMoney($netCollected), 'sub' => 'Refunds ' . $this->fmtMoney($refunds), 'tone' => 'blue', 'trend' => $this->trend($netCollected, $prevNetCollected)],
            ['label' => 'Avg / Day',       'value' => $this->fmtMoney($avgPerDay), 'sub' => 'Over ' . $rangeDays . ' days', 'tone' => 'teal', 'trend' => $this->trend($avgPerDay, $prevAvgPerDay)],
            ['label' => 'Outstanding (now)', 'value' => $this->fmtMoney($outstanding), 'sub' => 'Branch receivables', 'tone' => 'amber'],
        ];

        // ══ 2. APPOINTMENTS & VISITS ══════════════════════════════════════════
        $apptBase = DB::table('appointments')
            ->where('branch_id', $branchId)
            ->whereBetween('appointment_date', [$fromDate, $toDate]);

        $apptTotal     = (clone $apptBase)->count();
        $apptDone      = (clone $apptBase)->whereIn('status', ['done', 'checkout', 'completed'])->count();
        $apptCancelled = (clone $apptBase)->where('status', 'cancelled')->count();
        $apptNoShow    = (clone $apptBase)->where('status', 'no_show')->count();

        $completionRate = $apptTotal ? round($apptDone / $apptTotal * 100, 1) : 0.0;
        $noShowRate     = $apptTotal ? round($apptNoShow / $apptTotal * 100, 1) : 0.0;

        // Previous-window appointment counts (for trends)
        $prevApptBase = DB::table('appointments')
            ->where('branch_id', $branchId)
            ->whereBetween('appointment_date', [$prevFromDate, $prevToDate]);
        $prevApptTotal     = (clone $prevApptBase)->count();
        $prevApptDone      = (clone $prevApptBase)->whereIn('status', ['done', 'checkout', 'completed'])->count();
        $prevApptCancelled = (clone $prevApptBase)->where('status', 'cancelled')->count();
        $prevApptNoShow    = (clone $prevApptBase)->where('status', 'no_show')->count();

        $apptCards = [
            ['label' => 'Booked',    'value' => (string) $apptTotal, 'sub' => $periodLabel, 'tone' => 'blue', 'trend' => $this->trend($apptTotal, $prevApptTotal)],
            ['label' => 'Completed', 'value' => (string) $apptDone, 'sub' => $completionRate . '% completion', 'tone' => 'green', 'trend' => $this->trend($apptDone, $prevApptDone)],
            // Lower cancellations / no-shows is better → invert so a drop shows green
            ['label' => 'Cancelled', 'value' => (string) $apptCancelled, 'sub' => 'In period', 'tone' => 'amber', 'trend' => $this->trend($apptCancelled, $prevApptCancelled, true)],
            ['label' => 'No-shows',  'value' => (string) $apptNoShow, 'sub' => $noShowRate . '% no-show rate', 'tone' => 'red', 'trend' => $this->trend($apptNoShow, $prevApptNoShow, true)],
        ];

        // ══ 3. NEW PATIENTS & SOURCES ═════════════════════════════════════════
        $newPatientsBase = DB::table('patients')
            ->where('branch_id', $branchId)
            ->whereBetween('created_at', [$from, $to]);

        $newPatients = (clone $newPatientsBase)->count();

        $sourceBreakdown = (clone $newPatientsBase)
            ->selectRaw("COALESCE(NULLIF(TRIM(source), ''), 'Unknown') as source, COUNT(*) as cnt")
            ->groupBy('source')
            ->orderByDesc('cnt')
            ->get();

        // Referrals from existing patients within the period
        $referralCount = (clone $newPatientsBase)
            ->where('referral_type', 'existing_patient')
            ->count();

        // Previous-window patient counts (for trends)
        $prevPatientsBase = DB::table('patients')
            ->where('branch_id', $branchId)
            ->whereBetween('created_at', [$prevFrom, $prevTo]);
        $prevNewPatients   = (clone $prevPatientsBase)->count();
        $prevReferralCount = (clone $prevPatientsBase)->where('referral_type', 'existing_patient')->count();

        $patientCards = [
            ['label' => 'New Patients', 'value' => (string) $newPatients, 'sub' => $periodLabel, 'tone' => 'green', 'trend' => $this->trend($newPatients, $prevNewPatients)],
            ['label' => 'From Referral', 'value' => (string) $referralCount, 'sub' => 'Referred by patients', 'tone' => 'blue', 'trend' => $this->trend($referralCount, $prevReferralCount)],
            ['label' => 'Sources',       'value' => (string) $sourceBreakdown->count(), 'sub' => 'Distinct channels', 'tone' => 'teal'],
        ];

        // ══ 4. LAB & TASKS ════════════════════════════════════════════════════
        $labSent = 0; $labReceived = 0; $labOverdue = 0; $labRemakes = 0;
        $prevLabSent = 0; $prevLabReceived = 0; $prevLabRemakes = 0;
        try {
            $labSent = DB::table('lab_cases')
                ->where('branch_id', $branchId)
                ->whereNull('deleted_at')
                ->whereBetween('sent_date', [$fromDate, $toDate])
                ->count();

            $labReceived = DB::table('lab_cases')
                ->where('branch_id', $branchId)
                ->whereNull('deleted_at')
                ->whereBetween('received_date', [$fromDate, $toDate])
                ->count();

            // Currently overdue (point-in-time): expected back, not yet closed
            $labOverdue = DB::table('lab_cases')
                ->where('branch_id', $branchId)
                ->whereNull('deleted_at')
                ->whereNotIn('status', ['complete', 'rejected', 'delivered'])
                ->whereDate('expected_return_date', '<', $today->toDateString())
                ->count();

            $labRemakes = DB::table('lab_cases')
                ->where('branch_id', $branchId)
                ->whereNull('deleted_at')
                ->where('is_remake', true)
                ->whereBetween('sent_date', [$fromDate, $toDate])
                ->count();

            // Previous-window lab counts (for trends)
            $prevLabSent = DB::table('lab_cases')->where('branch_id', $branchId)->whereNull('deleted_at')
                ->whereBetween('sent_date', [$prevFromDate, $prevToDate])->count();
            $prevLabReceived = DB::table('lab_cases')->where('branch_id', $branchId)->whereNull('deleted_at')
                ->whereBetween('received_date', [$prevFromDate, $prevToDate])->count();
            $prevLabRemakes = DB::table('lab_cases')->where('branch_id', $branchId)->whereNull('deleted_at')
                ->where('is_remake', true)->whereBetween('sent_date', [$prevFromDate, $prevToDate])->count();
        } catch (\Exception $e) {
            // Lab tables not migrated — leave zeros
        }

        $tasksCompleted = 0; $tasksPending = 0; $tasksOverdue = 0; $prevTasksCompleted = 0;
        try {
            $tasksCompleted = DB::table('tasks')
                ->where('branch_id', $branchId)
                ->where('status', 'done')
                ->whereBetween('done_at', [$from, $to])
                ->count();

            $tasksPending = DB::table('tasks')
                ->where('branch_id', $branchId)
                ->where('status', 'pending')
                ->whereBetween('due_date', [$fromDate, $toDate])
                ->count();

            $tasksOverdue = DB::table('tasks')
                ->where('branch_id', $branchId)
                ->where('status', 'pending')
                ->whereDate('due_date', '<', $today->toDateString())
                ->count();

            $prevTasksCompleted = DB::table('tasks')->where('branch_id', $branchId)
                ->where('status', 'done')->whereBetween('done_at', [$prevFrom, $prevTo])->count();
        } catch (\Exception $e) {
            // Tasks table missing — leave zeros
        }

        $labTaskCards = [
            ['label' => 'Lab Sent',       'value' => (string) $labSent, 'sub' => 'Cases sent in period', 'tone' => 'blue', 'trend' => $this->trend($labSent, $prevLabSent)],
            ['label' => 'Lab Received',   'value' => (string) $labReceived, 'sub' => 'Returned in period', 'tone' => 'green', 'trend' => $this->trend($labReceived, $prevLabReceived)],
            ['label' => 'Lab Overdue',    'value' => (string) $labOverdue, 'sub' => 'Past due (now)', 'tone' => 'red'],
            // Fewer remakes is better → invert
            ['label' => 'Remakes',        'value' => (string) $labRemakes, 'sub' => 'Repeat work sent', 'tone' => 'amber', 'trend' => $this->trend($labRemakes, $prevLabRemakes, true)],
            ['label' => 'Tasks Done',     'value' => (string) $tasksCompleted, 'sub' => 'Completed in period', 'tone' => 'green', 'trend' => $this->trend($tasksCompleted, $prevTasksCompleted)],
            ['label' => 'Tasks Pending',  'value' => (string) $tasksPending, 'sub' => 'Due in period', 'tone' => 'amber'],
            ['label' => 'Tasks Overdue',  'value' => (string) $tasksOverdue, 'sub' => 'Past due (now)', 'tone' => 'red'],
        ];

        // ══ PRACTICE PROTOCOL COMPLIANCE ═════════════════════════════════════════
        // Per-person done-vs-missed for protocol-generated tasks in the window.
        $protocolCompliance = collect();
        $protocolTotals     = ['total' => 0, 'done' => 0, 'missed' => 0, 'rate' => 0];
        try {
            $protocolCompliance = DB::table('tasks')
                ->join('users', 'users.id', '=', 'tasks.assigned_to')
                ->whereNotNull('tasks.practice_protocol_id')
                ->where('tasks.branch_id', $branchId)
                ->whereBetween('tasks.due_date', [$fromDate, $toDate])
                ->selectRaw(
                    "users.name as name,
                     COUNT(*) as total,
                     SUM(CASE WHEN tasks.status = 'done' THEN 1 ELSE 0 END) as done,
                     SUM(CASE WHEN tasks.status = 'pending' AND tasks.due_date < ? THEN 1 ELSE 0 END) as missed,
                     SUM(CASE WHEN tasks.requires_evidence = 1 THEN 1 ELSE 0 END) as evidence_required",
                    [$today->toDateString()]
                )
                ->groupBy('users.name')
                ->orderByDesc('total')
                ->get()
                ->map(function ($r) {
                    $r->rate = $r->total > 0 ? (int) round($r->done / $r->total * 100) : 0;
                    return $r;
                });

            $protocolTotals['total']  = (int) $protocolCompliance->sum('total');
            $protocolTotals['done']   = (int) $protocolCompliance->sum('done');
            $protocolTotals['missed'] = (int) $protocolCompliance->sum('missed');
            $protocolTotals['rate']   = $protocolTotals['total'] > 0
                ? (int) round($protocolTotals['done'] / $protocolTotals['total'] * 100)
                : 0;
        } catch (\Exception $e) {
            // tasks table / columns missing — leave empty
        }

        return view('huddle.report', compact(
            'period',
            'periodLabel',
            'periodLabels',
            'fromDate',
            'toDate',
            'rangeDays',
            'collectionsCards',
            'byMode',
            'apptCards',
            'patientCards',
            'sourceBreakdown',
            'labTaskCards',
            'protocolCompliance',
            'protocolTotals'
        ));
    }

    /**
     * Build a period-over-period trend chip for a KPI card.
     * Returns ['dir' => up|down|flat, 'pct' => string, 'good' => bool|null].
     * $invert = true when a DECREASE is the good outcome (no-shows, remakes…).
     *
     * @return array{dir:string,pct:string,good:?bool}
     */
    private function trend(float $current, float $previous, bool $invert = false): array
    {
        // No baseline to compare against
        if ($previous <= 0.0) {
            if ($current <= 0.0) {
                return ['dir' => 'flat', 'pct' => '—', 'good' => null];
            }
            return ['dir' => 'up', 'pct' => 'New', 'good' => $invert ? false : true];
        }

        $change = ($current - $previous) / $previous * 100;
        $dir    = abs($change) < 0.05 ? 'flat' : ($change > 0 ? 'up' : 'down');

        if ($dir === 'flat') {
            return ['dir' => 'flat', 'pct' => '0%', 'good' => null];
        }

        // "good" = movement in the desired direction
        $good = $invert ? ($dir === 'down') : ($dir === 'up');

        return [
            'dir'  => $dir,
            'pct'  => ($change > 0 ? '+' : '') . round($change) . '%',
            'good' => $good,
        ];
    }

    /** Format a rupee amount compactly for KPI cards. */
    private function fmtMoney(float $value): string
    {
        $abs = abs($value);
        if ($abs >= 10000000) {   // ≥ 1 crore
            return '₹' . number_format($value / 10000000, 2) . ' Cr';
        }
        if ($abs >= 100000) {     // ≥ 1 lakh
            return '₹' . number_format($value / 100000, 2) . ' L';
        }
        return '₹' . number_format($value, 0);
    }

    /**
     * POST /huddle/comms/push
     * Called from the Daily Huddle comms list when staff click "Add to Comm List".
     * Creates CommunicationQueue records so they appear in PRM → Communication List.
     */
    public function pushToCommList(Request $request): JsonResponse
    {
        $request->validate([
            'items'          => ['required', 'array', 'min:1'],
            'items.*.note'   => ['nullable', 'string', 'max:500'],
        ]);

        $user    = auth()->user();
        $today   = \Carbon\Carbon::today();
        $created = 0;

        foreach ($request->input('items') as $item) {
            $patientId = isset($item['patient_id']) ? (int) $item['patient_id'] : null;
            $commType  = $item['comm_type'] ?? 'reminder';
            $note      = $item['note'] ?? null;

            // Skip PRM items (already in queue) and items without a patient
            if ($commType === 'prm' || ! $patientId) {
                continue;
            }

            // Source engine tag so PRM can filter huddle-generated items
            $sourceEngine = $commType === 'reminder' ? 'huddle_reminder' : 'huddle_followup';

            // De-duplicate: skip if already in the queue for this patient today from huddle
            $exists = \App\Models\CommunicationQueue::where('patient_id', $patientId)
                ->where('source_engine', $sourceEngine)
                ->where('status', 'pending')
                ->whereDate('created_at', $today->toDateString())
                ->exists();

            if ($exists) {
                continue;
            }

            // Fetch patient name + phone for denormalised columns
            $patient = DB::table('patients')
                ->where('id', $patientId)
                ->select('name', 'phone')
                ->first();

            \App\Models\CommunicationQueue::create([
                'patient_id'    => $patientId,
                'person_name'   => $patient?->name ?? 'Unknown',
                'phone'         => $patient?->phone ?? null,
                'channel'       => 'call',
                'comm_type'     => 'existing_patient',
                'purpose'       => $commType === 'reminder' ? 'appointment' : 'other',
                'direction'     => 'outbound',
                'next_action'   => 'call_back',
                'status'        => 'pending',
                'priority'      => $commType === 'reminder' ? 'high' : 'medium',
                'note'          => $note,
                'source_engine' => $sourceEngine,
                'follow_up_date'=> $today->toDateString(),
                'due_at'        => $today->toDateString() . ' ' . ($commType === 'reminder' ? '09:00:00' : '10:00:00'),
                'assigned_to'   => $user->name,
                'created_by'    => $user->id,
            ]);

            $created++;
        }

        return response()->json([
            'message' => $created . ' item(s) added to the communication list.',
            'created' => $created,
        ]);
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
