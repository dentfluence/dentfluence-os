<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CommunicationController
 *
 * Handles Communication Manager routes.
 * Session 2: UI built with dummy data.
 * Session 11: wired to real service layer.
 */
class CommunicationController extends Controller
{
    public function index(Request $request): View
    {
        $queue = $this->getDummyQueue();

        $stats = [
            'total_pending'   => collect($queue)->where('status', 'pending')->count(),
            'overdue'         => collect($queue)->where('is_overdue', true)->count(),
            'callbacks_today' => collect($queue)->where('type', 'callback')->count(),
            'completed_today' => collect($queue)->where('status', 'completed')->count(),
        ];

        $filters = [
            'source'   => $request->get('source'),
            'status'   => $request->get('status'),
            'staff'    => $request->get('staff'),
            'priority' => $request->get('priority'),
        ];

        return view('communication.manager.index', [
            'queue'     => $queue,
            'stats'     => $stats,
            'filters'   => $filters,
            'pageTitle' => 'Communication Manager',
            'activeNav' => 'manager',
        ]);
    }

    public function queue(Request $request): View
    {
        $queue = $this->getDummyQueue();

        // Filter to pending/in-progress only
        $items = collect($queue)
            ->whereIn('status', ['pending', 'in_progress'])
            ->values()
            ->toArray();

        $filters = $request->only(['source', 'status', 'staff', 'priority']);

        return view('communication.manager.queue', [
            'items'     => $items,
            'filters'   => $filters,
            'pageTitle' => 'Execution Queue',
            'activeNav' => 'manager',
        ]);
    }

    public function overdue(): View
    {
        $queue = $this->getDummyQueue();

        $items = collect($queue)
            ->where('is_overdue', true)
            ->values()
            ->toArray();

        return view('communication.manager.overdue', [
            'items'     => $items,
            'pageTitle' => 'Overdue Communications',
            'activeNav' => 'manager',
        ]);
    }

    public function logForm(): View
    {
        return view('communication.manager.log-form', [
            'classifications' => config('communication.classifications', [
                'new_patient'     => ['label' => 'New Patient'],
                'existing'        => ['label' => 'Existing Patient'],
                'ongoing_case'    => ['label' => 'Ongoing Case'],
                'doctor'          => ['label' => 'Doctor'],
                'vendor'          => ['label' => 'Vendor'],
                'lab'             => ['label' => 'Lab'],
                'spam'            => ['label' => 'Spam'],
                'other_important' => ['label' => 'Other Important'],
                'other'           => ['label' => 'Other'],
            ]),
            'staff' => [
                ['id' => 1, 'name' => 'Dr. Priya'],
                ['id' => 2, 'name' => 'Riya (Coordinator)'],
                ['id' => 3, 'name' => 'Sneha (Front Desk)'],
            ],
            'pageTitle' => 'Log Communication',
            'activeNav' => 'manager',
        ]);
    }

    public function logStore(): never
    {
        abort(501, 'Backend wiring comes in Session 11.');
    }

    private function getDummyQueue(): array
    {
        $stub = base_path('resources/stubs/communication/dummy-queue.json');
        if (file_exists($stub)) {
            return json_decode(file_get_contents($stub), true) ?? [];
        }
        return [];
    }
}