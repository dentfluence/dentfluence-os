<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HrAttendance;
use App\Models\HrDepartment;
use App\Models\HrStaffProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class HrAttendanceController extends Controller
{
    /* ────────────────────────────────────────────────────────
       INDEX — Today's attendance board (admin web view)
    ──────────────────────────────────────────────────────── */

    public function index(Request $request)
    {
        $date = $request->date ? \Carbon\Carbon::parse($request->date) : now();

        // All active staff who have an HR profile
        $staffQuery = User::with([
                'hrProfile.department',
                'currentShift.shift',
            ])
            ->whereHas('hrProfile')
            ->where('is_active', true);

        if ($request->filled('department_id')) {
            $staffQuery->whereHas('hrProfile', fn($q) =>
                $q->where('department_id', $request->department_id)
            );
        }

        $allStaff = $staffQuery->orderBy('name')->get();

        // Today's existing attendance records keyed by user_id
        $records = HrAttendance::whereDate('date', $date)
            ->get()
            ->keyBy('user_id');

        // Merge: for each staff, attach their attendance record (or null)
        $staffWithAttendance = $allStaff->map(function ($user) use ($records) {
            $user->todayAttendance = $records->get($user->id);
            return $user;
        });

        $departments = HrDepartment::active()->orderBy('name')->get();

        // Summary counts for the selected date
        $presentCount  = $records->whereIn('status', ['present', 'late', 'half_day'])->count();
        $absentCount   = $records->where('status', 'absent')->count();
        $onLeaveCount  = $records->where('status', 'on_leave')->count();
        $notMarkedCount = $allStaff->count() - $records->count();

        return view('hr.attendance.index', compact(
            'staffWithAttendance',
            'departments',
            'date',
            'presentCount',
            'absentCount',
            'onLeaveCount',
            'notMarkedCount',
        ));
    }

    /* ────────────────────────────────────────────────────────
       MARK — Admin marks / updates one staff member's attendance
    ──────────────────────────────────────────────────────── */

    public function mark(Request $request)
    {
        $request->validate([
            'user_id'    => 'required|exists:users,id',
            'date'       => 'required|date',
            'status'     => 'required|in:present,absent,late,half_day,on_leave,holiday',
            'check_in'   => 'nullable|date_format:H:i',
            'check_out'  => 'nullable|date_format:H:i',
            'notes'      => 'nullable|string|max:255',
        ]);

        HrAttendance::updateOrCreate(
            [
                'user_id' => $request->user_id,
                'date'    => $request->date,
            ],
            [
                'status'            => $request->status,
                'check_in'          => $request->check_in,
                'check_out'         => $request->check_out,
                'check_in_method'   => 'manual',
                'check_out_method'  => $request->check_out ? 'manual' : null,
                'marked_by'         => auth()->id(),
                'notes'             => $request->notes,
            ]
        );

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Attendance updated.');
    }

    /* ────────────────────────────────────────────────────────
       MARK BULK — Mark all unmarked staff as present/absent at once
    ──────────────────────────────────────────────────────── */

    public function markBulk(Request $request)
    {
        $request->validate([
            'date'       => 'required|date',
            'status'     => 'required|in:present,absent,holiday',
            'user_ids'   => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        foreach ($request->user_ids as $userId) {
            HrAttendance::updateOrCreate(
                ['user_id' => $userId, 'date' => $request->date],
                [
                    'status'          => $request->status,
                    'check_in_method' => 'manual',
                    'marked_by'       => auth()->id(),
                ]
            );
        }

        return back()->with('success', count($request->user_ids) . ' staff marked as ' . $request->status . '.');
    }

    /* ────────────────────────────────────────────────────────
       QR CHECK-IN — Android app hits this with the staff QR token.
       First hit = check-in. Second hit same day = check-out.
       No login required — authenticated by the unique QR token.
    ──────────────────────────────────────────────────────── */

    public function qrCheckin(string $token)
    {
        // Find staff by QR token
        $profile = HrStaffProfile::where('qr_token', $token)
            ->with('user')
            ->first();

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid QR code.',
            ], 404);
        }

        $user = $profile->user;
        $today = now()->toDateString();
        $time  = now()->format('H:i');

        $existing = HrAttendance::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        if (! $existing) {
            // First scan today = check-in
            HrAttendance::create([
                'user_id'         => $user->id,
                'date'            => $today,
                'check_in'        => $time,
                'status'          => 'present',
                'check_in_method' => 'qr',
            ]);

            return response()->json([
                'success' => true,
                'action'  => 'check_in',
                'name'    => $user->name,
                'time'    => now()->format('h:i A'),
                'message' => "Welcome, {$user->name}! Checked in at " . now()->format('h:i A'),
            ]);
        }

        if ($existing->check_out) {
            // Already checked out — just acknowledge
            return response()->json([
                'success' => true,
                'action'  => 'already_complete',
                'name'    => $user->name,
                'message' => "Already checked in and out today, {$user->name}.",
            ]);
        }

        // Second scan = check-out
        $existing->update([
            'check_out'        => $time,
            'check_out_method' => 'qr',
        ]);

        return response()->json([
            'success' => true,
            'action'  => 'check_out',
            'name'    => $user->name,
            'time'    => now()->format('h:i A'),
            'message' => "Goodbye, {$user->name}! Checked out at " . now()->format('h:i A'),
        ]);
    }
}
