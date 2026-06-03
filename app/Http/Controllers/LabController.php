<?php

namespace App\Http\Controllers;

use App\Models\LabCase;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * LabController — Session 7
 *
 * Routes handled:
 *   GET  /lab                                    → index()         all cases, filterable
 *   POST /lab                                    → store()         create new case
 *   PUT  /lab/{labCase}                          → update()        edit / change status
 *   DELETE /lab/{labCase}                        → destroy()
 *
 *   GET  /patients/{patient}/lab-cases           → patientCases()  AJAX — patient tab
 *   POST /patients/{patient}/lab-cases           → store()         create from patient context
 */
class LabController extends Controller
{
    // ── GLOBAL LIST ──────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $status  = $request->query('status', 'all');
        $search  = $request->query('q', '');

        $query = LabCase::with(['patient', 'doctor'])
            ->orderByDesc('sent_date');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search) {
            $query->whereHas('patient', fn($q) =>
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
            )->orWhere('lab_vendor', 'like', "%{$search}%");
        }

        $cases = $query->paginate(25)->withQueryString();

        // Counts for filter tabs
        $counts = [
            'all'         => LabCase::count(),
            'sent'        => LabCase::where('status', 'sent')->count(),
            'in_progress' => LabCase::where('status', 'in_progress')->count(),
            'received'    => LabCase::where('status', 'received')->count(),
            'rejected'    => LabCase::where('status', 'rejected')->count(),
        ];

        $doctors  = User::orderBy('name')->get();
        $patients = Patient::orderBy('name')->get(['id', 'name', 'phone']);

        return view('lab.index', compact('cases', 'counts', 'status', 'search', 'doctors', 'patients'));
    }

    // ── CREATE ───────────────────────────────────────────────────────────────

    public function store(Request $request, ?Patient $patient = null)
    {
        $data = $request->validate([
            'patient_id'           => 'required|exists:patients,id',
            'doctor_id'            => 'nullable|exists:users,id',
            'work_type'            => 'required|in:crown_bridge,denture,implant,ortho',
            'work_subtype'         => 'nullable|string|max:100',
            'tooth_number'         => 'nullable|string|max:50',
            'shade'                => 'nullable|string|max:20',
            'lab_vendor'           => 'nullable|string|max:150',
            'lab_cost'             => 'nullable|numeric|min:0',
            'sent_date'            => 'required|date',
            'expected_return_date' => 'nullable|date|after_or_equal:sent_date',
            'received_date'        => 'nullable|date',
            'status'               => 'required|in:sent,in_progress,received,rejected',
            'instructions'         => 'nullable|string',
            'notes'                => 'nullable|string',
        ]);

        LabCase::create($data);

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Lab case created.');
    }

    // ── UPDATE ───────────────────────────────────────────────────────────────

    public function update(Request $request, LabCase $labCase)
    {
        $data = $request->validate([
            'doctor_id'            => 'nullable|exists:users,id',
            'work_type'            => 'sometimes|required|in:crown_bridge,denture,implant,ortho',
            'work_subtype'         => 'nullable|string|max:100',
            'tooth_number'         => 'nullable|string|max:50',
            'shade'                => 'nullable|string|max:20',
            'lab_vendor'           => 'nullable|string|max:150',
            'lab_cost'             => 'nullable|numeric|min:0',
            'sent_date'            => 'sometimes|required|date',
            'expected_return_date' => 'nullable|date',
            'received_date'        => 'nullable|date',
            'status'               => 'sometimes|required|in:sent,in_progress,received,rejected',
            'instructions'         => 'nullable|string',
            'notes'                => 'nullable|string',
        ]);

        $labCase->update($data);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'case' => $labCase->fresh(['patient', 'doctor'])]);
        }

        return back()->with('success', 'Lab case updated.');
    }

    // ── DELETE ───────────────────────────────────────────────────────────────

    public function destroy(Request $request, LabCase $labCase)
    {
        $labCase->delete();

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Lab case deleted.');
    }

    // ── PATIENT TAB (AJAX) ────────────────────────────────────────────────────

    /**
     * Returns lab cases for a specific patient — used by the patient profile Lab tab.
     */
    public function patientCases(Patient $patient)
    {
        $cases   = $patient->labCases()->with('doctor')->get();
        $doctors = User::orderBy('name')->get();

        return view('patients.partials.lab-tab', compact('patient', 'cases', 'doctors'));
    }
}
