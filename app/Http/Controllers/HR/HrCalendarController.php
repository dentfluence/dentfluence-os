<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HrAttendance;
use App\Models\HrTrainingSession;
use App\Models\HrTrainingEnrollment;
use App\Models\User;
use Illuminate\Http\Request;

class HrCalendarController extends Controller
{
    public function index()
    {
        $staff = User::where('is_active', true)->orderBy('name')->get();
        return view('hr.calendar.index', compact('staff'));
    }

    /**
     * Return calendar events as JSON (consumed by the calendar JS on the frontend).
     * Query params: start, end (date strings), staff_id (optional)
     */
    public function events(Request $request)
    {
        $start = $request->input('start', today()->startOfMonth()->toDateString());
        $end   = $request->input('end',   today()->endOfMonth()->toDateString());
        $staffId = $request->input('staff_id');

        $events = [];

        // ── Training sessions ──────────────────────────────────
        $sessionsQuery = HrTrainingSession::whereBetween('scheduled_date', [$start, $end]);

        if ($staffId) {
            $sessionsQuery->whereHas('enrollments', fn($q) => $q->where('user_id', $staffId));
        }

        foreach ($sessionsQuery->get() as $session) {
            $events[] = [
                'id'    => 'training_' . $session->id,
                'title' => '🎓 ' . $session->title,
                'start' => $session->scheduled_date->toDateString()
                           . ($session->start_time ? 'T' . $session->start_time : ''),
                'end'   => $session->scheduled_date->toDateString()
                           . ($session->end_time ? 'T' . $session->end_time : ''),
                'color' => match($session->status) {
                    'completed' => '#16a34a',
                    'cancelled' => '#dc2626',
                    default     => '#7c3aed',
                },
                'url'   => route('hr.training.show', $session->id),
                'extendedProps' => [
                    'type'   => 'training',
                    'status' => $session->status,
                    'venue'  => $session->venue,
                ],
            ];
        }

        // ── Attendance leave / absent markers ──────────────────
        $attendanceQuery = HrAttendance::whereBetween('date', [$start, $end])
            ->whereIn('status', ['leave', 'absent']);

        if ($staffId) {
            $attendanceQuery->where('user_id', $staffId);
        }

        foreach ($attendanceQuery->with('user')->get() as $att) {
            $color = $att->status === 'leave' ? '#2563eb' : '#ef4444';
            $label = $att->status === 'leave' ? '🏖 Leave' : '❌ Absent';
            $events[] = [
                'id'    => 'att_' . $att->id,
                'title' => $label . ': ' . ($att->user->name ?? ''),
                'start' => $att->date,
                'allDay'=> true,
                'color' => $color,
                'extendedProps' => [
                    'type'      => 'attendance',
                    'status'    => $att->status,
                    'staff'     => $att->user->name ?? '',
                ],
            ];
        }

        return response()->json($events);
    }
}
