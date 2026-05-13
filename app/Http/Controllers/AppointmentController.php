<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\TreatmentCategory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AppointmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Appointment::with(['patient', 'doctor', 'treatmentCategory', 'treatment'])
            ->where('branch_id', Auth::user()->branch_id)
            ->orderBy('appointment_date')
            ->orderBy('appointment_time');

        if ($request->filled('date')) {
            $date = $request->get('date');
            $view = $request->get('view', 'day');

            if ($view === 'week') {
                $start = \Carbon\Carbon::parse($date)->startOfWeek(\Carbon\Carbon::SUNDAY);
                $end   = $start->copy()->addDays(6);
                $query->whereBetween('appointment_date', [$start->toDateString(), $end->toDateString()]);
            } elseif ($view === 'month') {
                $start = \Carbon\Carbon::parse($date)->startOfMonth();
                $end   = \Carbon\Carbon::parse($date)->endOfMonth();
                $query->whereBetween('appointment_date', [$start->toDateString(), $end->toDateString()]);
            } else {
                $query->whereDate('appointment_date', $date);
            }
        }

        $doctors = User::where('branch_id', Auth::user()->branch_id)
                       ->where('is_active', true)
                       ->get(['id', 'name']);

        $treatmentCategories = TreatmentCategory::active()
            ->orderBy('name')
            ->with(['treatments' => fn($q) => $q->orderBy('name')])
            ->get(['id', 'name']);

        $timeSlots = [];
        for ($h = 9; $h <= 20; $h++) {
            $timeSlots[] = sprintf('%02d:00', $h);
            $timeSlots[] = sprintf('%02d:30', $h);
        }

        $appointments = $query->get()->map(function ($a) {
            $date = $a->appointment_date;
            if ($date instanceof \Carbon\Carbon) {
                $date = $date->format('Y-m-d');
            } elseif (is_string($date) && strlen($date) > 10) {
                $date = substr($date, 0, 10);
            }

            return [
                'id'                    => $a->id,
                'patient_id'            => $a->patient_id,
                'patient_name'          => $a->patient?->name ?? '—',
                'patient_phone'         => $a->patient?->phone ?? '',
                'doctor_name'           => $a->doctor?->name ?? '—',
                'appointment_date'      => $date,
                'appointment_time'      => substr($a->appointment_time, 0, 5),
                'duration_minutes'      => $a->duration_minutes ?? 30,
                'type'                  => $a->type,
                'status'                => $a->status,
                'notes'                 => $a->notes,
                'chief_complaint'       => $a->chief_complaint,
                'treatment_category_id' => $a->treatment_category_id,
                'treatment_category'    => $a->treatmentCategory?->name,
                'treatment_id'          => $a->treatment_id,
                'treatment'             => $a->treatment?->name,
            ];
        })->values();

        if ($request->boolean('json')) {
            return response()->json($appointments);
        }

        return view('appointments.index', compact(
            'appointments',
            'doctors',
            'timeSlots',
            'treatmentCategories'
        ));
    }

    public function create(Request $request)
    {
        $patients = Patient::where('branch_id', Auth::user()->branch_id)
                           ->orderBy('name')
                           ->get(['id', 'name', 'phone']);

        $doctors = User::where('branch_id', Auth::user()->branch_id)
                       ->where('is_active', true)
                       ->get(['id', 'name']);

        $treatmentCategories = TreatmentCategory::active()
            ->orderBy('name')
            ->with(['treatments' => fn($q) => $q->orderBy('name')])
            ->get(['id', 'name']);

        $date = $request->get('date', today()->toDateString());
        $hour = $request->get('hour', null);

        $timeSlots = [];
        for ($h = 9; $h <= 20; $h++) {
            $timeSlots[] = sprintf('%02d:00', $h);
            $timeSlots[] = sprintf('%02d:30', $h);
        }

        return view('appointments.create', compact(
            'patients', 'doctors', 'date', 'hour', 'timeSlots', 'treatmentCategories'
        ));
    }

    public function store(Request $request)
    {
        $branchId = Auth::user()->branch_id;

        // ── Modal path: first_name + last_name + mobile sent (no patient_id) ──
        if ($request->filled('first_name') && ! $request->filled('patient_id')) {

            $request->validate([
                'first_name'            => 'required|string|max:100',
                'last_name'             => 'required|string|max:100',
                'mobile'                => 'required|string|max:20',
                'appointment_date'      => 'required|date',
                'appointment_time'      => 'required',
                'notes'                 => 'required|string|max:1000',
                'treatment_category_id' => 'nullable|exists:treatment_categories,id',
                'treatment_id'          => 'nullable|exists:treatments,id',
            ]);

            // Find existing patient by mobile in this branch, or create a new one
            $patient = Patient::firstOrCreate(
                [
                    'mobile'    => $request->mobile,
                    'branch_id' => $branchId,
                ],
                [
                    'name'      => trim($request->first_name . ' ' . $request->last_name),
                    'phone'     => $request->mobile,
                    'branch_id' => $branchId,
                ]
            );

            // Use first available doctor for this branch as default
            $doctorId = User::where('branch_id', $branchId)
                            ->where('is_active', true)
                            ->value('id');

            $appointment = Appointment::create([
                'patient_id'            => $patient->id,
                'doctor_id'             => $doctorId,
                'branch_id'             => $branchId,
                'created_by'            => Auth::id(),
                'appointment_date'      => $request->appointment_date,
                'appointment_time'      => $request->appointment_time,
                'duration_minutes'      => 30,
                'type'                  => 'consultation',
                'status'                => 'scheduled',
                'notes'                 => $request->notes,
                'treatment_category_id' => $request->treatment_category_id,
                'treatment_id'          => $request->treatment_id,
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success'     => true,
                    'ok'          => true,
                    'id'          => $appointment->id,
                    'patient_id'  => $patient->id,
                    'patient_name'=> $patient->name,
                ]);
            }

            return redirect()
                ->route('appointments.index', ['date' => $request->appointment_date])
                ->with('success', 'Appointment booked successfully.');
        }

        // ── Full form path: patient_id explicitly provided ──
        $data = $request->validate([
            'patient_id'            => 'required|exists:patients,id',
            'doctor_id'             => 'required|exists:users,id',
            'appointment_date'      => 'required|date',
            'appointment_time'      => 'required',
            'duration_minutes'      => 'nullable|integer|min:10|max:240',
            'type'                  => 'required|in:consultation,treatment',
            'notes'                 => 'required|string|max:1000',
            'treatment_category_id' => 'nullable|exists:treatment_categories,id',
            'treatment_id'          => 'nullable|exists:treatments,id',
        ]);

        $data['branch_id']        = $branchId;
        $data['created_by']       = Auth::id();
        $data['status']           = 'scheduled';
        $data['duration_minutes'] = $data['duration_minutes'] ?? 30;

        $appointment = Appointment::create($data);

        $date = $appointment->appointment_date instanceof \Carbon\Carbon
            ? $appointment->appointment_date->format('Y-m-d')
            : substr($appointment->appointment_date, 0, 10);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'ok'      => true,
                'id'      => $appointment->id,
            ]);
        }

        return redirect()
            ->route('appointments.index', ['date' => $date])
            ->with('success', 'Appointment booked successfully.');
    }

    public function updateStatus(Request $request, Appointment $appointment)
    {
        $request->validate([
            'status' => 'required|in:scheduled,checkin,in_chair,checkout,done,cancelled,no_show',
        ]);

        $appointment->update(['status' => $request->status]);

        return response()->json(['ok' => true]);
    }

    public function show(Appointment $appointment)
    {
        $appointment->load(['patient.notes.createdBy', 'patient.alerts', 'doctor', 'createdBy']);
        return view('appointments.show', compact('appointment'));
    }

    public function today()
    {
        return view('appointments.today');
    }
}