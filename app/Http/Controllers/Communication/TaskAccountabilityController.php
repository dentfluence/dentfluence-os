<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Services\Tasks\TaskAccountabilityReport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Tasks > Accountability — a read of the outcome trail, for whoever runs the
 * clinic. One action, no writes, no state.
 *
 * Access is gated by `module:reports` on the route rather than `module:tasks`:
 * every member of staff can see the task board, but a per-person breakdown of
 * who finished what is a management view. Someone who can see the board should
 * not automatically be able to read their colleague's week.
 */
class TaskAccountabilityController extends Controller
{
    /** How far back a range may be asked for, in days. */
    private const MAX_RANGE_DAYS = 186;

    public function index(Request $request, TaskAccountabilityReport $report)
    {
        $data = $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date',
        ]);

        // This week, Monday to today, is the default — the question this page
        // exists to answer is "how did this week go", and making the owner
        // pick dates before seeing anything is how a report goes unread.
        $from = isset($data['from']) ? Carbon::parse($data['from'])->startOfDay() : now()->startOfWeek();
        $to   = isset($data['to'])   ? Carbon::parse($data['to'])->startOfDay()   : now();

        // A backwards range returns nothing and looks like a bug in the data
        // rather than a typo in the form, so it is corrected instead.
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        // An unbounded range would let one bookmark table-scan the whole trail
        // on every load. Six months is far past any useful accountability
        // conversation.
        if ($from->diffInDays($to) > self::MAX_RANGE_DAYS) {
            $from = $to->copy()->subDays(self::MAX_RANGE_DAYS);
        }

        $branchId = Auth::user()->branch_id;

        return view('tasks.accountability', array_merge(
            $report->build($branchId, $from, $to),
            [
                'from'    => $from,
                'to'      => $to,
                'presets' => $this->presets(),
            ]
        ));
    }

    /** The three ranges anyone actually asks for, so nobody types dates. */
    private function presets(): array
    {
        return [
            'This week' => ['from' => now()->startOfWeek()->toDateString(),              'to' => now()->toDateString()],
            'Last week' => ['from' => now()->subWeek()->startOfWeek()->toDateString(),   'to' => now()->subWeek()->endOfWeek()->toDateString()],
            'This month'=> ['from' => now()->startOfMonth()->toDateString(),             'to' => now()->toDateString()],
        ];
    }
}
