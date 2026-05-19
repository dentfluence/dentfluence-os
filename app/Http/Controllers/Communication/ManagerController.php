<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ManagerController extends Controller
{
    /**
     * Main communication manager screen — the operational nerve center.
     * Currently loads dummy data. Will wire to DB in Session 11.
     */
    public function index(Request $request)
    {
        $queue     = $this->getDummyQueue();
        $overdue   = collect($queue)->where('is_overdue', true)->values();
        $pending   = collect($queue)->where('status', 'pending')->values();
        $callbacks = collect($queue)->where('type', 'callback')->values();

        $stats = [
            'total_pending'   => collect($queue)->where('status', 'pending')->count(),
            'overdue'         => $overdue->count(),
            'callbacks_today' => $callbacks->count(),
            'completed_today' => collect($queue)->where('status', 'completed')->count(),
        ];

        $filters = [
            'source'   => $request->get('source'),
            'status'   => $request->get('status'),
            'staff'    => $request->get('staff'),
            'priority' => $request->get('priority'),
        ];

        return view('communication.manager.index', compact('queue', 'overdue', 'stats', 'filters'));
    }

    /**
     * Execution queue — filtered, prioritized list.
     */
    public function queue(Request $request)
    {
        $queue = $this->getDummyQueue();

        // Apply filters
        if ($source = $request->get('source')) {
            $queue = array_filter($queue, fn($item) => $item['source'] === $source);
        }
        if ($status = $request->get('status')) {
            $queue = array_filter($queue, fn($item) => $item['status'] === $status);
        }

        return view('communication.manager.queue', [
            'items'   => array_values($queue),
            'filters' => $request->only(['source', 'status', 'staff', 'priority']),
        ]);
    }

    /**
     * Overdue communications screen.
     */
    public function overdue()
    {
        $queue   = $this->getDummyQueue();
        $overdue = array_filter($queue, fn($item) => $item['is_overdue'] === true);

        return view('communication.manager.overdue', [
            'items' => array_values($overdue),
        ]);
    }

    /**
     * Manual call log form.
     */
    public function logForm()
    {
        $classifications = [
            'new_patient'    => 'New Patient',
            'existing'       => 'Existing Patient',
            'ongoing_case'   => 'Ongoing Case',
            'doctor'         => 'Doctor',
            'vendor'         => 'Vendor',
            'lab'            => 'Lab',
            'spam'           => 'Spam',
            'other_important'=> 'Other Important',
            'other'          => 'Other',
        ];

        $staff = [
            ['id' => 1, 'name' => 'Dr. Priya'],
            ['id' => 2, 'name' => 'Riya (Coordinator)'],
            ['id' => 3, 'name' => 'Sneha (Front Desk)'],
        ];

        return view('communication.manager.log-form', compact('classifications', 'staff'));
    }

    /**
     * Dummy queue data — replaced by real DB queries in Session 11.
     */
    private function getDummyQueue(): array
    {
        $stub = base_path('resources/stubs/communication/dummy-queue.json');
        if (file_exists($stub)) {
            return json_decode(file_get_contents($stub), true) ?? [];
        }
        return [];
    }
}
