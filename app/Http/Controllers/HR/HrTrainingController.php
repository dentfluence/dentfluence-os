<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HrTrainingSession;
use App\Models\HrTrainingEnrollment;
use App\Models\HrPeriodicTrainingRequirement;
use App\Models\HrPeriodicTrainingRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class HrTrainingController extends Controller
{
    // ─────────────────────────────────────────────────────────────
    //  TRAINING SESSIONS
    // ─────────────────────────────────────────────────────────────

    public function index()
    {
        $sessions = HrTrainingSession::with('enrollments')
            ->orderByDesc('scheduled_date')
            ->paginate(20);

        $upcoming = HrTrainingSession::where('status', 'scheduled')
            ->where('scheduled_date', '>=', today())
            ->orderBy('scheduled_date')
            ->get();

        $staff = User::where('is_active', true)->orderBy('name')->get();

        return view('hr.training.index', compact('sessions', 'upcoming', 'staff'));
    }

    public function create()
    {
        $staff = User::where('is_active', true)->orderBy('name')->get();
        return view('hr.training.create', compact('staff'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'            => 'required|string|max:255',
            'description'      => 'nullable|string',
            'type'             => 'required|in:one_time,periodic',
            'trainer_name'     => 'nullable|string|max:255',
            'trainer_user_id'  => 'nullable|exists:users,id',
            'venue'            => 'nullable|string|max:255',
            'scheduled_date'   => 'required|date',
            'start_time'       => 'nullable|date_format:H:i',
            'end_time'         => 'nullable|date_format:H:i',
            'duration_minutes' => 'nullable|integer|min:1',
            'notes'            => 'nullable|string',
            'staff_ids'        => 'nullable|array',
            'staff_ids.*'      => 'exists:users,id',
        ]);

        $data['created_by'] = auth()->id();

        $session = HrTrainingSession::create($data);

        // Enroll selected staff immediately
        if (!empty($data['staff_ids'])) {
            foreach ($data['staff_ids'] as $userId) {
                HrTrainingEnrollment::firstOrCreate([
                    'training_session_id' => $session->id,
                    'user_id'             => $userId,
                ]);
            }
        }

        return redirect()->route('hr.training.show', $session)
            ->with('success', 'Training session created.');
    }

    public function show(HrTrainingSession $session)
    {
        $session->load(['enrollments.user', 'createdBy', 'internalTrainer']);

        // Staff not yet enrolled (for adding more)
        $enrolledIds = $session->enrollments->pluck('user_id')->toArray();
        $availableStaff = User::where('is_active', true)
            ->whereNotIn('id', $enrolledIds)
            ->orderBy('name')
            ->get();

        return view('hr.training.show', compact('session', 'availableStaff'));
    }

    public function edit(HrTrainingSession $session)
    {
        $staff = User::where('is_active', true)->orderBy('name')->get();
        return view('hr.training.edit', compact('session', 'staff'));
    }

    public function update(Request $request, HrTrainingSession $session)
    {
        $data = $request->validate([
            'title'            => 'required|string|max:255',
            'description'      => 'nullable|string',
            'type'             => 'required|in:one_time,periodic',
            'trainer_name'     => 'nullable|string|max:255',
            'trainer_user_id'  => 'nullable|exists:users,id',
            'venue'            => 'nullable|string|max:255',
            'scheduled_date'   => 'required|date',
            'start_time'       => 'nullable|date_format:H:i',
            'end_time'         => 'nullable|date_format:H:i',
            'duration_minutes' => 'nullable|integer|min:1',
            'status'           => 'required|in:scheduled,completed,cancelled',
            'notes'            => 'nullable|string',
        ]);

        $session->update($data);

        return redirect()->route('hr.training.show', $session)
            ->with('success', 'Session updated.');
    }

    public function destroy(HrTrainingSession $session)
    {
        $session->delete();
        return redirect()->route('hr.training.index')
            ->with('success', 'Session deleted.');
    }

    // ─────────────────────────────────────────────────────────────
    //  ENROLLMENT
    // ─────────────────────────────────────────────────────────────

    public function enroll(Request $request, HrTrainingSession $session)
    {
        $request->validate(['user_ids' => 'required|array', 'user_ids.*' => 'exists:users,id']);

        foreach ($request->user_ids as $userId) {
            HrTrainingEnrollment::firstOrCreate([
                'training_session_id' => $session->id,
                'user_id'             => $userId,
            ]);
        }

        return back()->with('success', count($request->user_ids) . ' staff enrolled.');
    }

    public function unenroll(HrTrainingSession $session, User $user)
    {
        HrTrainingEnrollment::where('training_session_id', $session->id)
            ->where('user_id', $user->id)
            ->delete();

        return back()->with('success', $user->name . ' removed from session.');
    }

    // Mark attendance for all enrolled staff in one POST
    public function markAttendance(Request $request, HrTrainingSession $session)
    {
        $request->validate(['attendance' => 'required|array']);

        foreach ($request->attendance as $enrollmentId => $status) {
            HrTrainingEnrollment::where('id', $enrollmentId)
                ->where('training_session_id', $session->id)
                ->update(['attendance' => $status]);
        }

        return back()->with('success', 'Attendance saved.');
    }

    // Mark session as completed and mark all "present" enrollments as completed
    public function markComplete(HrTrainingSession $session)
    {
        $session->update(['status' => 'completed']);

        $session->enrollments()
            ->where('attendance', 'present')
            ->update(['completed' => true, 'completed_at' => today()]);

        return back()->with('success', 'Session marked complete.');
    }

    // ─────────────────────────────────────────────────────────────
    //  PERIODIC TRAINING
    // ─────────────────────────────────────────────────────────────

    public function periodicIndex()
    {
        $requirements = HrPeriodicTrainingRequirement::where('is_active', true)
            ->with(['records.user'])
            ->get();

        $allStaff = User::where('is_active', true)->orderBy('name')->get();

        // Build compliance summary: for each requirement × staff, get latest record
        $compliance = [];
        foreach ($requirements as $req) {
            foreach ($allStaff as $staff) {
                $latest = HrPeriodicTrainingRecord::where('requirement_id', $req->id)
                    ->where('user_id', $staff->id)
                    ->latest('completed_on')
                    ->first();
                $compliance[$req->id][$staff->id] = $latest;
            }
        }

        $sessions = HrTrainingSession::orderByDesc('scheduled_date')->get();

        return view('hr.training.periodic', compact('requirements', 'allStaff', 'compliance', 'sessions'));
    }

    public function storeRequirement(Request $request)
    {
        $data = $request->validate([
            'name'             => 'required|string|max:255',
            'description'      => 'nullable|string',
            'applies_to'       => 'nullable|string|max:100',
            'frequency_months' => 'required|integer|min:1',
        ]);

        $data['created_by'] = auth()->id();

        HrPeriodicTrainingRequirement::create($data);

        return back()->with('success', 'Requirement added.');
    }

    public function destroyRequirement(HrPeriodicTrainingRequirement $requirement)
    {
        $requirement->update(['is_active' => false]);
        return back()->with('success', 'Requirement archived.');
    }

    public function storeRecord(Request $request)
    {
        $data = $request->validate([
            'requirement_id'     => 'required|exists:hr_periodic_training_requirements,id',
            'user_id'            => 'required|exists:users,id',
            'completed_on'       => 'required|date',
            'training_session_id'=> 'nullable|exists:hr_training_sessions,id',
            'notes'              => 'nullable|string',
        ]);

        // Compute next_due_on from requirement frequency
        $req = HrPeriodicTrainingRequirement::findOrFail($data['requirement_id']);
        $data['next_due_on'] = Carbon::parse($data['completed_on'])
            ->addMonths($req->frequency_months)
            ->toDateString();
        $data['recorded_by'] = auth()->id();

        HrPeriodicTrainingRecord::create($data);

        return back()->with('success', 'Compliance record saved. Next due: ' . $data['next_due_on']);
    }
}
