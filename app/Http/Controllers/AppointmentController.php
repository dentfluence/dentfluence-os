<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AppSetting;
use App\Models\DoctorBlockedSlot;
use App\Models\Operatory;
use App\Models\Patient;
use App\Models\TreatmentCategory;
use App\Models\User;
use App\Services\AppointmentService;
use App\Services\Relationship\AppointmentActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AppointmentController extends Controller
{
    use \App\Http\Controllers\Concerns\ChecksStaleUpdates;

    public function __construct(
        private AppointmentActivityLogger $activityLogger,
        private AppointmentService $appointments,
    ) {}

    // ── Index / Calendar view ────────────────────────────────────
    public function index(Request $request)
    {
        $branchId = Auth::user()->branch_id;

        $query = Appointment::with(['patient', 'doctor', 'treatmentCategory', 'treatment', 'operatory'])
            ->where('branch_id', $branchId)
            ->where('hidden_from_calendar', false)   // exclude soft-hidden cancelled appointments
            ->orderBy('appointment_date')
            ->orderBy('appointment_time');

        if ($request->filled('date')) {
            $date = $request->get('date');
            $view = $request->get('view', 'day');

            if ($view === 'week') {
                $start = Carbon::parse($date)->startOfWeek(Carbon::SUNDAY);
                $end   = $start->copy()->addDays(6);
                $query->whereBetween('appointment_date', [$start->toDateString(), $end->toDateString()]);
            } elseif ($view === 'month') {
                $start = Carbon::parse($date)->startOfMonth();
                $end   = Carbon::parse($date)->endOfMonth();
                $query->whereBetween('appointment_date', [$start->toDateString(), $end->toDateString()]);
            } else {
                $query->whereDate('appointment_date', $date);
            }
        } else {
            $query->whereBetween('appointment_date', [
                today()->subDays(7)->toDateString(),
                today()->addDays(60)->toDateString(),
            ]);
        }

        if ($request->filled('doctor_id')) {
            $query->where('doctor_id', $request->doctor_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $doctors = User::where('branch_id', $branchId)
            ->where('is_active', true)
            ->where(fn($q) => $q->whereIn('role', User::DOCTOR_ROLES)->orWhere('name', 'like', 'Dr.%'))
            ->get(['id', 'name']);

        $treatmentCategories = TreatmentCategory::active()
            ->orderBy('name')
            ->with(['treatments' => fn($q) => $q->orderBy('name')])
            ->get(['id', 'name']);

        $timeSlots = [];
        for ($h = 8; $h <= 21; $h++) {
            $timeSlots[] = sprintf('%02d:00', $h);
            $timeSlots[] = sprintf('%02d:30', $h);
        }

        $appointments = $query->get()->map(fn($a) => $this->formatAppointment($a))->values();

        // Today's queue for sidebar
        $todayAppointments = Appointment::with(['patient', 'doctor', 'treatmentCategory', 'treatment', 'operatory'])
            ->where('branch_id', $branchId)
            ->whereDate('appointment_date', today())
            ->orderBy('appointment_time')
            ->get()
            ->map(fn($a) => $this->formatAppointment($a))
            ->values();

        // Live status counters
        $statusCounts = $this->getTodayStatusCounts($branchId);

        if ($request->boolean('json')) {
            return response()->json($appointments);
        }

        $calendarPrefs = AppSetting::group('calendar');

        return view('appointments.index', compact(
            'appointments',
            'doctors',
            'timeSlots',
            'treatmentCategories',
            'todayAppointments',
            'statusCounts',
            'calendarPrefs'
        ));
    }

    // ── Store ────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $branchId = Auth::user()->branch_id;

        // ── Walk-in with NEW patient (first_name present, no patient_id) ──
        if ($request->filled('first_name') && ! $request->filled('patient_id')) {
            $request->validate([
                'first_name'           => 'required|string|max:100',
                'last_name'            => 'required|string|max:100',
                'mobile'               => 'required|string|max:20',
                'appointment_date'     => 'required|date',
                'appointment_time'     => 'required',
                'notes'                => 'nullable|string|max:1000',
                'treatment_category_id'=> 'nullable|exists:treatment_categories,id',
                'treatment_id'         => 'nullable|exists:treatments,id',
            ]);

            $doctorId = $request->filled('doctor_id')
                ? $request->doctor_id
                : User::where('branch_id', $branchId)->where('is_active', true)->value('id');

            $duration = $this->autoDuration($request->treatment_category_id);

            // ── Guards run BEFORE the patient is created ───────────
            // Previously Patient::create() ran first, so a rejected booking
            // left an orphan patient record behind on every failed attempt.

            // Blocked slot (doctor on leave) — hard block.
            if ($err = $this->blockedSlotConflict($doctorId, $request->appointment_date, $request->appointment_time, $duration)) {
                return $request->expectsJson()
                    ? response()->json($err, 422)
                    : back()->withErrors($err['message'])->withInput();
            }

            // Double-booking guard (bypass with allow_overlap).
            if (! $this->overlapAllowed($request)
                && $err = $this->overlapConflict($doctorId, $request->appointment_date, $request->appointment_time, $duration, null, $branchId)) {
                return $request->expectsJson()
                    ? response()->json($err, 422)
                    : back()->withErrors($err['message'])->withInput();
            }

            // Always create a new patient — multiple family members can share a phone number
            $patient = Patient::create([
                'name'      => trim($request->first_name . ' ' . $request->last_name),
                'phone'     => $request->mobile,
                'branch_id' => $branchId,
            ]);

            $appointment = Appointment::create([
                'patient_id'           => $patient->id,
                'doctor_id'            => $doctorId,
                'branch_id'            => $branchId,
                'created_by'           => Auth::id(),
                'appointment_date'     => $request->appointment_date,
                'appointment_time'     => $request->appointment_time,
                'duration_minutes'     => $duration,
                'type'                 => 'consultation',
                'status'               => 'checkin',
                'notes'                => $request->notes ?? 'Walk-in',
                'treatment_category_id'=> $request->treatment_category_id,
                'treatment_id'         => $request->treatment_id,
                'is_walkin'            => true,
                'checked_in_at'        => now(),
            ]);

            $this->activityLogger->booked($appointment, Auth::user());

            if ($request->expectsJson()) {
                return response()->json([
                    'success'      => true,
                    'ok'           => true,
                    'appointment'  => $this->formatAppointment($appointment->load(['patient', 'doctor', 'treatmentCategory', 'treatment'])),
                ]);
            }

            return redirect()
                ->route('appointments.index', ['date' => $request->appointment_date])
                ->with('success', 'Walk-in booked successfully.');
        }

        // ── Walk-in with EXISTING patient (patient_id + is_walkin) ──
        if ($request->filled('patient_id') && $request->boolean('is_walkin')) {
            $request->validate([
                'patient_id'           => 'required|exists:patients,id',
                'appointment_date'     => 'required|date',
                'appointment_time'     => 'required',
                'notes'                => 'nullable|string|max:1000',
                'treatment_category_id'=> 'nullable|exists:treatment_categories,id',
            ]);

            $doctorId = $request->filled('doctor_id')
                ? $request->doctor_id
                : User::where('branch_id', $branchId)->where('is_active', true)->value('id');

            // ── Blocked slot check ─────────────────────────────────
            $wiDuration = $this->autoDuration($request->treatment_category_id);
            if ($err = $this->blockedSlotConflict($doctorId, $request->appointment_date, $request->appointment_time, $wiDuration)) {
                return $request->expectsJson()
                    ? response()->json($err, 422)
                    : back()->withErrors($err['message'])->withInput();
            }

            // ── Double-booking guard (bypass with allow_overlap) ───
            if (! $this->overlapAllowed($request)
                && $err = $this->overlapConflict($doctorId, $request->appointment_date, $request->appointment_time, $wiDuration, null, $branchId)) {
                return $request->expectsJson()
                    ? response()->json($err, 422)
                    : back()->withErrors($err['message'])->withInput();
            }

            $appointment = Appointment::create([
                'patient_id'           => $request->patient_id,
                'doctor_id'            => $doctorId,
                'branch_id'            => $branchId,
                'created_by'           => Auth::id(),
                'appointment_date'     => $request->appointment_date,
                'appointment_time'     => $request->appointment_time,
                'duration_minutes'     => $this->autoDuration($request->treatment_category_id),
                'type'                 => $request->type ?? 'consultation',
                'status'               => 'checkin',
                'notes'                => $request->notes ?? 'Walk-in',
                'treatment_category_id'=> $request->treatment_category_id,
                'is_walkin'            => true,
                'checked_in_at'        => now(),
            ]);

            $this->activityLogger->booked($appointment, Auth::user());

            if ($request->expectsJson()) {
                return response()->json([
                    'success'     => true,
                    'ok'          => true,
                    'appointment' => $this->formatAppointment($appointment->load(['patient', 'doctor', 'treatmentCategory', 'treatment'])),
                ]);
            }

            return redirect()
                ->route('appointments.index', ['date' => $request->appointment_date])
                ->with('success', 'Walk-in booked successfully.');
        }

        // Full form path
        $data = $request->validate([
            'patient_id'           => 'required|exists:patients,id',
            'doctor_id'            => 'required|exists:users,id',
            'appointment_date'     => 'required|date',
            'appointment_time'     => 'required',
            'duration_minutes'     => 'nullable|integer|min:10|max:240',
            'type'                 => 'required|in:consultation,treatment,follow-up',
            'notes'                => 'nullable|string|max:1000',
            'treatment_category_id'=> 'nullable|exists:treatment_categories,id',
            'treatment_id'         => 'nullable|exists:treatments,id',
            'chair_number'         => 'nullable|integer|min:1|max:20',
            'operatory_id'         => 'nullable|exists:operatories,id',
        ]);

        $data['branch_id']        = $branchId;
        $data['created_by']       = Auth::id();
        $data['status']           = 'scheduled';
        $data['duration_minutes'] = $data['duration_minutes']
            ?? $this->autoDuration($data['treatment_category_id'] ?? null);

        // ── Blocked slot check ─────────────────────────────────────
        if ($err = $this->blockedSlotConflict($data['doctor_id'], $data['appointment_date'], $data['appointment_time'], $data['duration_minutes'])) {
            return $request->expectsJson()
                ? response()->json($err, 422)
                : back()->withErrors($err['message'])->withInput();
        }

        // ── Double-booking guard (bypass with allow_overlap) ────────
        if (! $this->overlapAllowed($request)
            && $err = $this->overlapConflict($data['doctor_id'], $data['appointment_date'], $data['appointment_time'], $data['duration_minutes'], null, $branchId)) {
            return $request->expectsJson()
                ? response()->json($err, 422)
                : back()->withErrors($err['message'])->withInput();
        }

        $appointment = Appointment::create($data);

        $this->activityLogger->booked($appointment, Auth::user());

        $date = $appointment->appointment_date instanceof Carbon
            ? $appointment->appointment_date->format('Y-m-d')
            : substr($appointment->appointment_date, 0, 10);

        if ($request->expectsJson()) {
            return response()->json([
                'success'     => true,
                'ok'          => true,
                'id'          => $appointment->id,
                'appointment' => $this->formatAppointment($appointment->load(['patient', 'doctor', 'treatmentCategory', 'treatment'])),
            ]);
        }

        return redirect()
            ->route('appointments.index', ['date' => $date])
            ->with('success', 'Appointment booked successfully.');
    }

    // ── Update Status (PATCH /appointments/{id}/status) ──────────
    public function updateStatus(Request $request, Appointment $appointment)
    {
        $request->validate([
            'status' => 'required|in:scheduled,checkin,in_chair,checkout,done,cancelled,no_show',
        ]);

        $update = [
            'previous_status' => $appointment->status, // save for revert
            'status'          => $request->status,
        ];

        match ($request->status) {
            'checkin'  => $update['checked_in_at'] = now(),
            'in_chair' => $update['in_chair_at']   = now(),
            'done'     => $update['completed_at']  = now(),
            default    => null,
        };

        $appointment->update($update);

        match ($request->status) {
            'checkin' => $this->activityLogger->checkedIn($appointment, Auth::user()),
            'done'    => $this->activityLogger->completed($appointment, Auth::user()),
            // Direct cancel via the status dropdown (no reason captured) — the
            // calendar's "Cancel Appointment" modal uses cancelWithReason() below instead.
            'cancelled' => $this->activityLogger->cancelled($appointment, Auth::user()),
            // Fires 'appointment.missed' → the enabled missed_appointment_followup
            // rule auto-creates the reschedule call task. Nothing emitted this
            // event before, so that rule could never fire.
            'no_show'   => $this->activityLogger->missed($appointment, Auth::user()),
            default   => null,
        };

        $fresh = $appointment->fresh()->load(['patient', 'doctor', 'treatmentCategory', 'treatment', 'operatory']);

        return response()->json([
            'ok'          => true,
            'status'      => $fresh->status,
            'appointment' => $this->formatAppointment($fresh),
            'counts'      => $this->getTodayStatusCounts($appointment->branch_id),
        ]);
    }

    // ── Cancel with reason (PATCH /appointments/{id}/cancel) ────────────────
    public function cancelWithReason(Request $request, Appointment $appointment)
    {
        $request->validate([
            'cancel_reason'   => 'required|string|max:500',
            'cancelled_party' => 'required|in:patient,clinic',
        ]);

        $appointment->update([
            'previous_status' => $appointment->status,
            'status'          => 'cancelled',
            'cancel_reason'   => $request->cancel_reason,
            'cancelled_party' => $request->cancelled_party,
        ]);

        $this->activityLogger->cancelled($appointment, Auth::user(), $request->cancel_reason, $request->cancelled_party);

        $fresh = $appointment->fresh()->load(['patient', 'doctor', 'treatmentCategory', 'treatment', 'operatory']);

        return response()->json([
            'ok'          => true,
            'appointment' => $this->formatAppointment($fresh),
            'counts'      => $this->getTodayStatusCounts($appointment->branch_id),
        ]);
    }

    // ── Revert to previous status (PATCH /appointments/{id}/revert) ──────────
    public function revertStatus(Appointment $appointment)
    {
        $prev = $appointment->previous_status;

        if (! $prev) {
            return response()->json(['ok' => false, 'message' => 'No previous status to revert to.'], 422);
        }

        $update = [
            'status'          => $prev,
            'previous_status' => null,
            'cancel_reason'   => null,
            'cancelled_party' => null,
        ];

        $appointment->update($update);

        $fresh = $appointment->fresh()->load(['patient', 'doctor', 'treatmentCategory', 'treatment', 'operatory']);

        return response()->json([
            'ok'          => true,
            'appointment' => $this->formatAppointment($fresh),
            'counts'      => $this->getTodayStatusCounts($appointment->branch_id),
        ]);
    }

    // ── Assign Operatory (PATCH /appointments/{id}/operatory) ────
    // Lightweight endpoint — lets front desk reassign chair at check-in
    // without going through the full edit form.
    public function assignOperatory(Request $request, Appointment $appointment)
    {
        $request->validate([
            'operatory_id' => 'nullable|exists:operatories,id',
        ]);

        $appointment->update(['operatory_id' => $request->operatory_id ?: null]);

        $fresh = $appointment->fresh()->load(['patient', 'doctor', 'treatmentCategory', 'treatment', 'operatory']);

        return response()->json([
            'ok'          => true,
            'appointment' => $this->formatAppointment($fresh),
        ]);
    }

    // ── Today Queue (GET /appointments/queue/today) ──────────────
    public function todayQueue(Request $request)
    {
        $branchId = Auth::user()->branch_id;

        $query = Appointment::with(['patient', 'doctor', 'treatmentCategory', 'treatment', 'operatory'])
            ->where('branch_id', $branchId)
            ->whereDate('appointment_date', today())
            ->orderBy('appointment_time');

        if ($request->filled('doctor_id')) {
            $query->where('doctor_id', $request->doctor_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $appointments = $query->get()->map(fn($a) => $this->formatAppointment($a))->values();

        return response()->json([
            'appointments' => $appointments,
            'counts'       => $this->getTodayStatusCounts($branchId),
        ]);
    }

    // ── Live Status Counts (GET /appointments/status-counts) ─────
    public function statusCounts(Request $request)
    {
        $branchId = Auth::user()->branch_id;
        return response()->json($this->getTodayStatusCounts($branchId));
    }

    // ── Quick View (GET /appointments/{id}/quick) ────────────────
    public function quickView(Appointment $appointment)
    {
        $appointment->load(['patient', 'doctor', 'treatmentCategory', 'treatment']);
        return response()->json($this->formatAppointment($appointment));
    }

    // ── Show ─────────────────────────────────────────────────────
    public function show(Appointment $appointment)
    {
        $appointment->load(['patient.notes.createdBy', 'patient.alerts', 'doctor', 'createdBy']);
        return view('appointments.show', compact('appointment'));
    }

    // ── Today view ───────────────────────────────────────────────
    public function today()
    {
        return view('appointments.today');
    }

    // ── Create ───────────────────────────────────────────────────
    public function create(Request $request)
    {
        $branchId = Auth::user()->branch_id;

        $patients = Patient::where('branch_id', $branchId)
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);

        $doctors = User::where('branch_id', $branchId)
            ->where('is_active', true)
            ->where(fn($q) => $q->whereIn('role', User::DOCTOR_ROLES)->orWhere('name', 'like', 'Dr.%'))
            ->get(['id', 'name']);

        $treatmentCategories = TreatmentCategory::active()
            ->orderBy('name')
            ->with(['treatments' => fn($q) => $q->orderBy('name')])
            ->get(['id', 'name']);

        $date      = $request->get('date', today()->toDateString());
        $hour      = $request->get('hour', null);
        $timeSlots = [];

        for ($h = 8; $h <= 21; $h++) {
            $timeSlots[] = sprintf('%02d:00', $h);
            $timeSlots[] = sprintf('%02d:30', $h);
        }

        $operatories = Operatory::forBranch($branchId)->active()->ordered()->get(['id', 'name']);

        return view('appointments.create', compact(
            'patients', 'doctors', 'date', 'hour', 'timeSlots', 'treatmentCategories', 'operatories'
        ));
    }

    // ── Edit ─────────────────────────────────────────────────────
    public function edit(Appointment $appointment)
    {
        $branchId = Auth::user()->branch_id;

        $patients = Patient::where('branch_id', $branchId)->orderBy('name')->get(['id', 'name', 'phone']);
        $doctors  = User::where('branch_id', $branchId)->where('is_active', true)->where(fn($q) => $q->whereIn('role', User::DOCTOR_ROLES)->orWhere('name', 'like', 'Dr.%'))->get(['id', 'name']);
        $treatmentCategories = TreatmentCategory::active()
            ->orderBy('name')
            ->with(['treatments' => fn($q) => $q->orderBy('name')])
            ->get(['id', 'name']);

        $timeSlots = [];
        for ($h = 8; $h <= 21; $h++) {
            $timeSlots[] = sprintf('%02d:00', $h);
            $timeSlots[] = sprintf('%02d:30', $h);
        }

        $operatories = Operatory::forBranch($branchId)->active()->ordered()->get(['id', 'name']);

        return view('appointments.edit', compact('appointment', 'patients', 'doctors', 'timeSlots', 'treatmentCategories', 'operatories'));
    }

    // ── Update ──────────────────────────────────────────────────
    public function update(Request $request, Appointment $appointment)
    {
        $data = $request->validate([
            'patient_id'           => 'required|exists:patients,id',
            'doctor_id'            => 'required|exists:users,id',
            'appointment_date'     => 'required|date',
            'appointment_time'     => 'required',
            'duration_minutes'     => 'nullable|integer|min:10|max:240',
            'type'                 => 'required|in:consultation,treatment,follow-up',
            'notes'                => 'nullable|string|max:1000',
            'treatment_category_id'=> 'nullable|exists:treatment_categories,id',
            'treatment_id'         => 'nullable|exists:treatments,id',
            'chair_number'         => 'nullable|integer|min:1|max:20',
            'operatory_id'         => 'nullable|exists:operatories,id',
        ]);

        // Optimistic lock — two receptionists editing the same appointment no
        // longer silently overwrite each other.
        $this->assertNotStale($request, $appointment);

        $duration = $data['duration_minutes']
            ?? $appointment->duration_minutes
            ?? $this->autoDuration($data['treatment_category_id'] ?? null);

        // ── Blocked slot + double-booking guards ───────────────────
        // The edit form could previously move an appointment onto a doctor's
        // leave or on top of another patient with no check at all.
        if ($err = $this->blockedSlotConflict($data['doctor_id'], $data['appointment_date'], $data['appointment_time'], $duration)) {
            return $request->expectsJson()
                ? response()->json($err, 422)
                : back()->withErrors($err['message'])->withInput();
        }

        if (! $this->overlapAllowed($request)
            && $err = $this->overlapConflict($data['doctor_id'], $data['appointment_date'], $data['appointment_time'], $duration, $appointment->id, $appointment->branch_id)) {
            return $request->expectsJson()
                ? response()->json($err, 422)
                : back()->withErrors($err['message'])->withInput();
        }

        $appointment->update($data);

        if ($request->expectsJson()) {
            return response()->json([
                'ok'          => true,
                'appointment' => $this->formatAppointment($appointment->fresh()->load(['patient', 'doctor', 'treatmentCategory', 'treatment', 'operatory'])),
            ]);
        }

        $date = $appointment->appointment_date instanceof Carbon
            ? $appointment->appointment_date->format('Y-m-d')
            : substr($appointment->appointment_date, 0, 10);

        return redirect()
            ->route('appointments.index', ['date' => $date])
            ->with('success', 'Appointment updated.');
    }

    // ── Reschedule (drag-drop: date + time, optional duration) ───
    public function reschedule(Request $request, Appointment $appointment)
    {
        $data = $request->validate([
            'appointment_date' => 'required|date',
            'appointment_time' => 'required|date_format:H:i',
            'duration_minutes' => 'nullable|integer|min:10|max:480',
        ]);

        $duration = $data['duration_minutes'] ?? $appointment->duration_minutes ?? 30;

        // ── Blocked slot + double-booking guards ───────────────────
        // Drag-drop is the most conflict-prone action in the app and previously
        // ran NO checks — a cleaning could be dropped onto an in-progress RCT or
        // onto the doctor's leave and it would just snap into place.
        // The calendar reverts the drag on a 422 (see onEventDrop).
        if ($err = $this->blockedSlotConflict($appointment->doctor_id, $data['appointment_date'], $data['appointment_time'], $duration)) {
            return response()->json($err, 422);
        }

        if (! $this->overlapAllowed($request)
            && $err = $this->overlapConflict($appointment->doctor_id, $data['appointment_date'], $data['appointment_time'], $duration, $appointment->id, $appointment->branch_id)) {
            return response()->json($err, 422);
        }

        $appointment->update($data);

        return response()->json([
            'ok'          => true,
            'appointment' => $this->formatAppointment(
                $appointment->fresh()->load(['patient', 'doctor', 'treatmentCategory', 'treatment', 'operatory'])
            ),
        ]);
    }

    // ── Destroy ──────────────────────────────────────────────────
    public function destroy(Request $request, Appointment $appointment)
    {
        $appointment->delete();

        if ($request->boolean('json') || $request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('appointments.index')->with('success', 'Appointment deleted.');
    }

    // ── Hide from calendar (PATCH /appointments/{id}/hide) ───────
    public function hideFromCalendar(Appointment $appointment)
    {
        $appointment->update(['hidden_from_calendar' => true]);

        return response()->json([
            'ok'          => true,
            'appointment' => $this->formatAppointment($appointment->fresh()->load(['patient', 'doctor', 'treatmentCategory', 'treatment', 'operatory'])),
        ]);
    }

    // ── Conflict Check (GET /appointments/check-conflict) ────────
    public function checkConflict(Request $request)
    {
        $request->validate([
            'doctor_id'        => 'required',
            'appointment_date' => 'required|date',
            'appointment_time' => 'required',
            'duration_minutes' => 'nullable|integer',
            'exclude_id'       => 'nullable|integer',
        ]);

        // Advisory check for the booking modals. Uses the same shared overlap
        // filter the server now ENFORCES on write, so the warning the user sees
        // and the rule the server applies can never drift apart.
        $clash = $this->appointments->overlapConflict(
            (int) $request->doctor_id,
            (int) Auth::user()->branch_id,
            $request->appointment_date,
            $request->appointment_time,
            (int) ($request->duration_minutes ?? 30),
            $request->exclude_id ? (int) $request->exclude_id : null
        );

        return response()->json([
            'has_conflict' => (bool) $clash,
            'conflicts'    => $clash ? [[
                'patient_name' => $clash->patient?->name,
                'time'         => substr((string) $clash->appointment_time, 0, 5),
                'duration'     => $clash->duration_minutes,
            ]] : [],
        ]);
    }

    // ── Block Slot: store ─────────────────────────────────────────
    public function storeBlockedSlot(Request $request)
    {
        $data = $request->validate([
            'doctor_id'  => 'required|exists:users,id',
            'block_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time'   => 'required|date_format:H:i|after:start_time',
            'reason'     => 'nullable|string|max:255',
            'block_type' => 'nullable|in:unavailable,break,emergency',
        ]);

        $data['created_by'] = Auth::id();
        $data['block_type'] = $data['block_type'] ?? 'unavailable';

        $slot = DoctorBlockedSlot::create($data);

        return response()->json([
            'ok'   => true,
            'slot' => $slot,
        ]);
    }

    // ── Block Slot: list (for calendar) ───────────────────────────
    public function indexBlockedSlots(Request $request)
    {
        $request->validate([
            'start'     => 'nullable|date',
            'end'       => 'nullable|date',
            'doctor_id' => 'nullable|integer',
        ]);

        $query = DoctorBlockedSlot::with('doctor:id,name')
            ->when($request->start && $request->end,
                fn($q) => $q->inRange($request->start, $request->end)
            )
            ->when($request->doctor_id,
                fn($q) => $q->where('doctor_id', $request->doctor_id)
            )
            ->orderBy('block_date')
            ->orderBy('start_time');

        $slots = $query->get()->map(fn($s) => [
            'id'          => $s->id,
            'doctor_id'   => $s->doctor_id,
            'doctor_name' => $s->doctor?->name ?? 'Unknown',
            'block_date'  => $s->block_date->format('Y-m-d'),
            'start_time'  => substr($s->start_time, 0, 5),
            'end_time'    => substr($s->end_time,   0, 5),
            'reason'      => $s->reason,
            'block_type'  => $s->block_type,
            // FullCalendar-friendly fields
            'start'       => $s->block_date->format('Y-m-d') . 'T' . substr($s->start_time, 0, 5),
            'end'         => $s->block_date->format('Y-m-d') . 'T' . substr($s->end_time,   0, 5),
        ]);

        return response()->json($slots);
    }

    // ── Private helpers ──────────────────────────────────────────

    /**
     * Return a JSON error response if the doctor has a blocked slot
     * overlapping the given appointment window, or null if clear.
     */
    /**
     * Doctor double-booking guard (2026-07-14 production hardening).
     *
     * Overlap detection previously existed ONLY as an advisory GET
     * (checkConflict) surfaced as a JS confirm() in the booking modals — so the
     * mobile API, the edit form, drag-drop reschedule, and two receptionists
     * booking the same slot simultaneously could all write overlaps silently.
     *
     * This is the same overlap filter, enforced server-side. Deliberate
     * double-booking (second chair, overlap consult) remains possible: the
     * caller passes allow_overlap=true, which is exactly what the modals send
     * after the user confirms the "Book anyway?" prompt.
     *
     * @return array|null  error payload, or null when the slot is free
     */
    private function overlapConflict(
        ?int   $doctorId,
        string $date,
        string $time,
        int    $duration,
        ?int   $excludeId = null,
        ?int   $branchId = null
    ): ?array {
        $clash = $this->appointments->overlapConflict(
            $doctorId,
            $branchId ?? Auth::user()->branch_id,
            $date,
            $time,
            $duration,
            $excludeId
        );

        if (! $clash) return null;

        $who  = $clash->patient?->name ?? 'another patient';
        $when = substr((string) $clash->appointment_time, 0, 5);

        return [
            'success'      => false,
            'ok'           => false,
            'has_conflict' => true,
            'message'      => "This doctor already has {$who} at {$when} ({$clash->duration_minutes} min). Choose another time, or confirm to double-book.",
            'errors'       => ['appointment_time' => 'Doctor is already booked in this slot.'],
        ];
    }

    /** True when the caller explicitly opted into double-booking. */
    private function overlapAllowed(Request $request): bool
    {
        return $request->boolean('allow_overlap');
    }

    private function blockedSlotConflict(?int $doctorId, string $date, string $time, int $duration): ?array
    {
        $block = $this->appointments->blockedSlotConflict($doctorId, $date, $time, $duration);

        if (! $block) return null;

        $doctorName = $block->doctor?->name ?? 'This doctor';
        $reason     = $block->reason ? " ({$block->reason})" : '';

        return [
            'success' => false,
            'ok'      => false,
            'message' => "{$doctorName} is not available from {$block->start_time} to {$block->end_time} on this date{$reason}. Please choose a different time or doctor.",
            'errors'  => ['appointment_time' => 'This slot is blocked for the selected doctor.'],
        ];
    }

    private function getTodayStatusCounts(int $branchId): array
    {
        $base = Appointment::where('branch_id', $branchId)
            ->whereDate('appointment_date', today());

        return array_merge([
            'total'     => (clone $base)->count(),
            'scheduled' => (clone $base)->where('status', 'scheduled')->count(),
            'checkin'   => (clone $base)->where('status', 'checkin')->count(),
            'in_chair'  => (clone $base)->where('status', 'in_chair')->count(),
            'done'      => (clone $base)->where('status', 'done')->count(),
            'cancelled' => (clone $base)->where('status', 'cancelled')->count(),
            'no_show'   => (clone $base)->where('status', 'no_show')->count(),
            'walkin'    => (clone $base)->where('is_walkin', true)->count(),
        ], $this->getChairUtilization($branchId, $base));
    }

    /**
     * Chair/slot utilization for today: booked-minutes across every scheduled
     * appointment (excluding cancelled/no_show, which never occupy a chair)
     * divided by total available chair-minutes (active operatories x the
     * clinic's daily capacity window).
     *
     * Two things are configurable but currently defaulted, since neither is
     * captured anywhere else in the app yet:
     *   - appointments.daily_capacity_hours (AppSetting) — defaults to 14h
     *     (08:00-22:00), matching the booking slot generator in create()/edit().
     *   - Chair count falls back to the branch's distinct chair_number values
     *     when no Operatory rows are configured, and to 1 if neither exists,
     *     so the metric degrades gracefully instead of exploding to 0/0.
     */
    private function getChairUtilization(int $branchId, $baseQuery): array
    {
        $bookedMinutes = (clone $baseQuery)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->get(['duration_minutes'])
            ->sum(fn ($a) => $a->duration_minutes ?? 30);

        $chairCount = Operatory::forBranch($branchId)->active()->count();
        if ($chairCount === 0) {
            $chairCount = max(1, (clone $baseQuery)->whereNotNull('chair_number')->distinct()->count('chair_number'));
        }

        $capacityHours   = (float) AppSetting::get('appointments.daily_capacity_hours', '14');
        $capacityMinutes = max(1, $chairCount * $capacityHours * 60);

        $pct = min(100, round(($bookedMinutes / $capacityMinutes) * 100, 1));

        return [
            'chair_utilization_pct'     => $pct,
            'chair_booked_minutes'      => (int) $bookedMinutes,
            'chair_capacity_minutes'    => (int) $capacityMinutes,
            'chair_count'               => $chairCount,
        ];
    }

    private function formatAppointment(Appointment $a): array
    {
        $date = $a->appointment_date;
        if ($date instanceof Carbon) {
            $date = $date->format('Y-m-d');
        } elseif (is_string($date) && strlen($date) > 10) {
            $date = substr($date, 0, 10);
        }

        return [
            'id'                   => $a->id,
            'patient_id'           => $a->patient_id,
            'patient_name'         => $a->patient?->name ?? '—',
            'patient_phone'        => $a->patient?->phone ?? '',
            'patient_age'          => $a->patient?->age ?? null,
            'doctor_id'            => $a->doctor_id,
            'doctor_name'          => $a->doctor?->name ?? '—',
            'appointment_date'     => $date,
            'appointment_time'     => substr($a->appointment_time, 0, 5),
            'duration_minutes'     => $a->duration_minutes ?? 30,
            'type'                 => $a->type,
            'status'               => $a->status,
            'notes'                => $a->notes,
            'chief_complaint'      => $a->chief_complaint,
            'treatment_category_id'=> $a->treatment_category_id,
            'treatment_category'   => $a->treatmentCategory?->name,
            'treatment_id'         => $a->treatment_id,
            'treatment'            => $a->treatment?->name,
            'is_walkin'            => (bool) $a->is_walkin,
            'chair_number'         => $a->chair_number,
            'operatory_id'         => $a->operatory_id,
            'operatory_name'       => $a->operatory?->name ?? null,
            'checked_in_at'        => $a->checked_in_at?->format('H:i'),
            'in_chair_at'          => $a->in_chair_at?->format('H:i'),
            'completed_at'         => $a->completed_at?->format('H:i'),
            'cancel_reason'        => $a->cancel_reason,
            'cancelled_party'      => $a->cancelled_party,
            'previous_status'      => $a->previous_status,
            // Colors for calendar display
            'treatment_color'      => $a->treatmentCategory?->color ?? null,
            'doctor_color'         => $a->doctor?->color ?? null,
        ];
    }

    private function autoDuration(?int $categoryId): int
    {
        if (! $categoryId) return 30;

        $cat = TreatmentCategory::find($categoryId);
        if (! $cat) return 30;

        $map = [
            'consultation'  => 30,
            'rct'           => 60,
            'root canal'    => 60,
            'implant'       => 90,
            'surgery'       => 90,
            'cleaning'      => 45,
            'scaling'       => 45,
            'follow'        => 30,
            'crown'         => 60,
            'extraction'    => 30,
            'filling'       => 45,
            'orthodontic'   => 30,
            'braces'        => 30,
            'xray'          => 15,
            'x-ray'         => 15,
            'whitening'     => 60,
            'veneer'        => 90,
        ];

        $nameLower = strtolower($cat->name);
        foreach ($map as $keyword => $minutes) {
            if (str_contains($nameLower, $keyword)) return $minutes;
        }

        return 30;
    }
}