<?php

namespace App\Http\Controllers;

use App\Models\DataBreach;
use App\Services\BreachService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * DataBreachController (DPDP 5.3)
 * -------------------------------
 * Breach register + the report-to-board and notify-patients actions.
 */
class DataBreachController extends Controller
{
    public function __construct(private BreachService $breaches) {}

    public function index()
    {
        $breaches = DataBreach::latest('discovered_at')->paginate(25);
        $counts = [
            'open'         => DataBreach::whereIn('status', ['open', 'contained'])->count(),
            'unreported'   => DataBreach::whereNull('reported_to_board_at')->count(),
            'total'        => DataBreach::count(),
        ];
        return view('breaches.index', compact('breaches', 'counts'));
    }

    public function create()
    {
        return view('breaches.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'          => ['required', 'string', 'max:160'],
            'description'    => ['nullable', 'string', 'max:4000'],
            'severity'       => ['required', Rule::in(DataBreach::SEVERITIES)],
            'nature'         => ['nullable', 'string', 'max:2000'],
            'affected_scope' => ['nullable', 'string', 'max:160'],
            'affected_count' => ['nullable', 'integer', 'min:0'],
            'occurred_at'    => ['nullable', 'date'],
            'discovered_at'  => ['required', 'date'],
        ]);

        $breach = $this->breaches->log($data);

        return redirect()->route('breaches.show', $breach)->with('success', "Breach {$breach->reference} logged.");
    }

    public function show(DataBreach $breach)
    {
        return view('breaches.show', compact('breach'));
    }

    public function update(Request $request, DataBreach $breach)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(DataBreach::STATUSES)],
        ]);
        $breach->update($data);
        return back()->with('success', 'Status updated.');
    }

    public function reportBoard(Request $request, DataBreach $breach)
    {
        $data = $request->validate(['board_reference' => ['nullable', 'string', 'max:120']]);
        $this->breaches->markReportedToBoard($breach, $data['board_reference'] ?? null);
        return back()->with('success', 'Marked as reported to the Data Protection Board.');
    }

    public function notifyAffected(DataBreach $breach)
    {
        $this->breaches->markPatientsNotified($breach);
        return back()->with('success', 'Marked affected patients as notified.');
    }
}
