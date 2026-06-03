<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * FollowUpController
 *
 * Handles all Follow-up Engine views and actions.
 * Routes prefix: /communication/followup-engine
 *
 * SESSION 4: UI only — all data is static/dummy.
 * SESSION 11: Replace dummy data with real Eloquent queries.
 */
class FollowUpController extends Controller
{
    /**
     * Main calendar view.
     * GET /communication/followup-engine
     */
    public function index(Request $request)
    {
        $view      = $request->get('view', 'week'); // day | week | month | agenda
        $dummy     = $this->getDummyFollowUps();
        $overdue   = $this->getDummyOverdue();
        $todayList = $this->getDummyTodayList();
        $stats     = $this->getDummyStats();

        return view('communication.followup.index', compact(
            'view', 'dummy', 'overdue', 'todayList', 'stats'
        ));
    }

    /**
     * Queue list view (all follow-ups in list format).
     * GET /communication/followup-engine/queue
     */
    public function queue(Request $request)
    {
        $filter  = $request->get('filter', 'today'); // today | overdue | upcoming | all
        $dummy   = $this->getDummyFollowUps();

        return view('communication.followup.queue', compact('filter', 'dummy'));
    }

    /**
     * Overdue list view.
     * GET /communication/followup-engine/overdue
     */
    public function overdue()
    {
        $overdue = $this->getDummyOverdue();
        return view('communication.followup.overdue', compact('overdue'));
    }

    /**
     * Complete a follow-up (modal POST).
     * POST /communication/followup-engine/{id}/complete
     * SESSION 11: Replace with real DB update + FollowUpRulesService call.
     */
    public function complete(Request $request, $id)
    {
        // TODO SESSION 11: validate, update follow-up, trigger next rules
        return response()->json(['success' => true, 'message' => 'Follow-up completed.']);
    }

    /**
     * Reschedule a follow-up (modal POST).
     * POST /communication/followup-engine/{id}/reschedule
     */
    public function reschedule(Request $request, $id)
    {
        // TODO SESSION 11: validate, update due_date/time
        return response()->json(['success' => true, 'message' => 'Follow-up rescheduled.']);
    }

    /**
     * Schedule a new follow-up manually.
     * POST /communication/followup-engine/schedule
     */
    public function schedule(Request $request)
    {
        // TODO SESSION 11: validate + create follow-up record
        return response()->json(['success' => true, 'message' => 'Follow-up scheduled.']);
    }

    /**
     * Add note to a follow-up.
     * POST /communication/followup-engine/{id}/note
     */
    public function addNote(Request $request, $id)
    {
        // TODO SESSION 11: validate + store note
        return response()->json(['success' => true, 'message' => 'Note saved.']);
    }

    /**
     * Change lead/patient status from follow-up.
     * POST /communication/followup-engine/{id}/change-status
     */
    public function changeStatus(Request $request, $id)
    {
        // TODO SESSION 11: validate + update status
        return response()->json(['success' => true, 'message' => 'Status updated.']);
    }

    /**
     * Convert lead to patient from follow-up.
     * POST /communication/followup-engine/{id}/convert
     */
    public function convertToPatient(Request $request, $id)
    {
        // TODO SESSION 11: validate + create patient record
        return response()->json(['success' => true, 'message' => 'Lead converted to patient.']);
    }

    /**
     * Create a case/trigger from follow-up.
     * POST /communication/followup-engine/{id}/create-case
     */
    public function createCase(Request $request, $id)
    {
        // TODO SESSION 11: validate + route to correct department
        return response()->json(['success' => true, 'message' => 'Case created.']);
    }

    // =========================================================
    // DUMMY DATA — Replace in Session 11
    // =========================================================

    private function getDummyStats(): array
    {
        return [
            'total'     => 128,
            'due_today' => 34,
            'overdue'   => 12,
            'completed' => 82,
            'upcoming'  => 52,
        ];
    }

    private function getDummyTodayList(): array
    {
        return [
            ['id' => 1,  'name' => 'Riya Sharma',    'time' => '10:00 AM', 'channel' => 'call',      'tag' => 'New Lead',       'due_in' => '1h 20m',  'overdue' => false, 'avatar' => 'RS', 'color' => '#6B5BDF'],
            ['id' => 2,  'name' => 'Priya Singh',     'time' => '11:00 AM', 'channel' => 'whatsapp',  'tag' => 'Estimate Given', 'due_in' => '2h 20m',  'overdue' => false, 'avatar' => 'PS', 'color' => '#22C55E'],
            ['id' => 3,  'name' => 'Karan Malhotra',  'time' => '12:00 PM', 'channel' => 'clinic',    'tag' => 'Post-Op',        'due_in' => '3h 20m',  'overdue' => false, 'avatar' => 'KM', 'color' => '#F97316'],
            ['id' => 4,  'name' => 'Siddharth Rao',   'time' => '02:00 PM', 'channel' => 'call',      'tag' => 'Recall',         'due_in' => '5h 20m',  'overdue' => false, 'avatar' => 'SR', 'color' => '#6B5BDF'],
        ];
    }

    private function getDummyOverdue(): array
    {
        return [
            ['id' => 10, 'name' => 'Mohit Bhatt',    'date' => '17 May, 10:00 AM', 'overdue_by' => '2 days',  'channel' => 'call',     'avatar' => 'MB', 'color' => '#EF4444', 'phone' => '98765 43210'],
            ['id' => 11, 'name' => 'Sneha Reddy',    'date' => '16 May, 03:00 PM', 'overdue_by' => '3 days',  'channel' => 'whatsapp', 'avatar' => 'SR', 'color' => '#22C55E', 'phone' => '97654 32109'],
            ['id' => 12, 'name' => 'Amit Kulkarni',  'date' => '15 May, 11:00 AM', 'overdue_by' => '4 days',  'channel' => 'call',     'avatar' => 'AK', 'color' => '#EF4444', 'phone' => '98201 23456'],
            ['id' => 13, 'name' => 'Vikram Mehta',   'date' => '14 May, 04:00 PM', 'overdue_by' => '5 days',  'channel' => 'call',     'avatar' => 'VM', 'color' => '#EF4444', 'phone' => '99876 12345'],
        ];
    }

    private function getDummyFollowUps(): array
    {
        return [
            // Week view events — keyed by date
            '2025-05-18' => [
                ['id' => 20, 'name' => 'Riya Sharma',   'time' => '10:00 AM', 'channel' => 'call',     'type' => 'follow_up', 'color' => '#6B5BDF'],
                ['id' => 21, 'name' => 'Priya Singh',   'time' => '11:00 AM', 'channel' => 'whatsapp', 'type' => 'follow_up', 'color' => '#22C55E'],
                ['id' => 22, 'name' => 'Karan Malhotra','time' => '12:00 PM', 'channel' => 'clinic',   'type' => 'clinic',    'color' => '#F97316'],
                ['id' => 23, 'name' => 'Siddharth Rao', 'time' => '02:00 PM', 'channel' => 'call',     'type' => 'follow_up', 'color' => '#6B5BDF'],
            ],
            '2025-05-19' => [
                ['id' => 24, 'name' => 'Arjun Patel',   'time' => '09:30 AM', 'channel' => 'call',     'type' => 'follow_up', 'color' => '#6B5BDF'],
                ['id' => 25, 'name' => 'Sunita Joshi',  'time' => '11:00 AM', 'channel' => 'whatsapp', 'type' => 'follow_up', 'color' => '#22C55E'],
                ['id' => 26, 'name' => 'Appointment',   'time' => '01:00 PM', 'channel' => 'clinic',   'type' => 'clinic',    'color' => '#F97316'],
                ['id' => 27, 'name' => 'Neha Kapoor',   'time' => '03:00 PM', 'channel' => 'call',     'type' => 'follow_up', 'color' => '#6B5BDF'],
            ],
            '2025-05-20' => [
                ['id' => 28, 'name' => 'Mohit Bhatt',   'time' => '10:00 AM', 'channel' => 'call',     'type' => 'overdue',   'color' => '#EF4444'],
                ['id' => 29, 'name' => 'Rohit Tiwari',  'time' => '12:30 PM', 'channel' => 'whatsapp', 'type' => 'follow_up', 'color' => '#22C55E'],
                ['id' => 30, 'name' => 'Megha Iyer',    'time' => '04:00 PM', 'channel' => 'call',     'type' => 'follow_up', 'color' => '#6B5BDF'],
            ],
            '2025-05-21' => [
                ['id' => 31, 'name' => 'Deepak Nair',   'time' => '10:00 AM', 'channel' => 'call',     'type' => 'follow_up', 'color' => '#6B5BDF'],
                ['id' => 32, 'name' => 'Harshita Agarwal','time' => '02:00 PM','channel' => 'whatsapp', 'type' => 'follow_up', 'color' => '#22C55E'],
                ['id' => 33, 'name' => 'Anjali Verma',  'time' => '05:00 PM', 'channel' => 'call',     'type' => 'follow_up', 'color' => '#6B5BDF'],
            ],
            '2025-05-22' => [
                ['id' => 34, 'name' => 'Rahul Verma',   'time' => '11:00 AM', 'channel' => 'call',     'type' => 'follow_up', 'color' => '#6B5BDF'],
            ],
            '2025-05-23' => [
                ['id' => 35, 'name' => 'Pallavi Joshi', 'time' => '10:00 AM', 'channel' => 'whatsapp', 'type' => 'follow_up', 'color' => '#22C55E'],
                ['id' => 36, 'name' => 'Nisha Chauhan', 'time' => '03:00 PM', 'channel' => 'whatsapp', 'type' => 'follow_up', 'color' => '#22C55E'],
                ['id' => 37, 'name' => 'Review Visit',  'time' => '01:00 PM', 'channel' => 'clinic',   'type' => 'clinic',    'color' => '#F97316'],
            ],
        ];
    }

    public function calendar()
    {
        return view('communication.followup.calendar');
    }
    public function recalls()
    {
        return view('communication.followup.recalls');
    }
}
