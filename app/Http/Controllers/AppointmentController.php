<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\TreatmentCategory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AppointmentController extends Controller
{
    // ── Index / Calendar view ────────────────────────────────────
    public function index(Request $request)
    {
        $branchId = Auth::user()->branch_id;

        $query = Appointment::with(['patient', 'doctor', 'treatmentCategory', 'treatment'])
            ->where('branch_id', $branchId)
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
        $todayAppointments = Appointment::with(['patient', 'doctor', 'treatmentCategory', 'treatment'])
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

        return view('appointments.index', compact(
            'appointments',
            'doctors',
            'timeSlots',
            'treatmentCategories',
            'todayAppointments',
            'statusCounts'
        ));
    }

    // ── Store ────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $branchId = Auth::user()->branch_id;

        // Walk-in / modal path
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

            $patient = Patient::firstOrCreate(
                ['mobile' => $request->mobile, 'branch_id' => $branchId],
                [
                    'name'      => trim($request->first_name . ' ' . $request->last_name),
                    'phone'     => $request->mobile,
                    'branch_id' => $branchId,
                ]
            );

            $doctorId = $request->filled('doctor_id')
                ? $request->doctor_id
                : User::where('branch_id', $branchId)->where('is_active', true)->value('id');

            $duration = $this->autoDuration($request->treatment_category_id);

            $appointment = Appointment::create([
                'patient_id'           => $patient->id,
                'doctor_id'            => $doctorId,
                'branch_id'            => $branchId,
                'created_by'           => Auth::id(),
                'appointment_date'     => $request->appointment_date,
                'appointment_time'     => $request->appointment_time,
                'duration_minutes'     => $duration,
                'type'                 => 'consultation',
                'status'               => $request->boolean('is_walkin') ? 'checkin' : 'scheduled',
                'notes'                => $request->notes ?? 'Walk-in',
                'treatment_category_id'=> $request->treatment_category_id,
                'treatment_id'         => $request->treatment_id,
                'is_walkin'            => $request->boolean('is_walkin'),
                'checked_in_at'        => $request->boolean('is_walkin') ? now() : null,
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success'      => true,
                    'ok'           => true,
                    'id'           => $appointment->id,
                    'patient_id'   => $patient->id,
                    'patient_name' => $patient->name,
                    'appointment'  => $this->formatAppointment($appointment->load(['patient', 'doctor', 'treatmentCategory', 'treatment'])),
                ]);
            }

            return redirect()
                ->route('appointments.index', ['date' => $request->appointment_date])
                ->with('success', 'Appointment booked successfully.');
        }

        // Full form path
        $data = $request->validate([
            'patient_id'           => 'required|exists:patients,id',
            'doctor_id'            => 'required|exists:users,id',
            'appointment_date'     => 'required|date',
            'appointment_time'     => 'required',
            'duration_minutes'     => 'nullable|integer|min:10|max:240',
            'type'                 => 'required|in:consultation,treatment',
            'notes'                => 'nullable|string|max:1000',
            'treatment_category_id'=> 'nullable|exists:treatment_categories,id',
            'treatment_id'         => 'nullable|exists:treatments,id',
            'chair_number'         => 'nullable|integer|min:1|max:20',
        ]);

        $data['branch_id']        = $branchId;
        $data['created_by']       = Auth::id();
        $data['status']           = 'scheduled';
        $data['duration_minutes'] = $data['duration_minutes']
            ?? $this->autoDuration($data['treatment_category_id'] ?? null);

        $appointment = Appointment::create($data);

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

        $update = ['status' => $request->status];

        match ($request->status) {
            'checkin'  => $update['checked_in_at'] = now(),
            'in_chair' => $update['in_chair_at']   = now(),
            'done'     => $update['completed_at']  = now(),
            default    => null,
        };

        $appointment->update($update);

        $fresh = $appointment->fresh()->load(['patient', 'doctor', 'treatmentCategory', 'treatment']);

        return response()->json([
            'ok'          => true,
            'status'      => $fresh->status,
            'appointment' => $this->formatAppointment($fresh),
            'counts'      => $this->getTodayStatusCounts($appointment->branch_id),
        ]);
    }

    // ── Today Queue (GET /appointments/queue/today) ──────────────
    public function todayQueue(Request $request)
    {
        $branchId = Auth::user()->branch_id;

        $query = Appointment::with(['patient', 'doctor', 'treatmentCategory', 'treatment'])
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

        return view('appointments.create', compact(
            'patients', 'doctors', 'date', 'hour', 'timeSlots', 'treatmentCategories'
        ));
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

        $branchId = Auth::user()->branch_id;
        $start    = Carbon::parse($request->appointment_date . ' ' . $request->appointment_time);
        $duration = $request->duration_minutes ?? 30;
        $end      = $start->copy()->addMinutes($duration);

        $conflicts = Appointment::where('branch_id', $branchId)
            ->where('doctor_id', $request->doctor_id)
            ->whereDate('appointment_date', $request->appointment_date)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->when($request->exclude_id, fn($q) => $q->where('id', '!=', $request->exclude_id))
            ->get()
            ->filter(function ($apt) use ($start, $end) {
                $aptStart = Carbon::parse($apt->appointment_date . ' ' . $apt->appointment_time);
                $aptEnd   = $aptStart->copy()->addMinutes($apt->duration_minutes ?? 30);
                return $start->lt($aptEnd) && $end->gt($aptStart);
            });

        return response()->json([
            'has_conflict' => $conflicts->isNotEmpty(),
            'conflicts'    => $conflicts->map(fn($a) => [
                'patient_name' => $a->patient?->name,
                'time'         => substr($a->appointment_time, 0, 5),
                'duration'     => $a->duration_minutes,
            ])->values(),
        ]);
    }

    // ── Private helpers ──────────────────────────────────────────
    private function getTodayStatusCounts(int $branchId): array
    {
        $base = Appointment::where('branch_id', $branchId)
            ->whereDate('appointment_date', today());

        return [
            'total'     => (clone $base)->count(),
            'scheduled' => (clone $base)->where('status', 'scheduled')->count(),
            'checkin'   => (clone $base)->where('status', 'checkin')->count(),
            'in_chair'  => (clone $base)->where('status', 'in_chair')->count(),
            'done'      => (clone $base)->where('status', 'done')->count(),
            'cancelled' => (clone $base)->where('status', 'cancelled')->count(),
            'no_show'   => (clone $base)->where('status', 'no_show')->count(),
            'walkin'    => (clone $base)->where('is_walkin', true)->count(),
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
            'checked_in_at'        => $a->checked_in_at?->format('H:i'),
            'in_chair_at'          => $a->in_chair_at?->format('H:i'),
            'completed_at'         => $a->completed_at?->format('H:i'),
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