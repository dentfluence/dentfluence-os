<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Models\CommunicationQueue;
use App\Services\RecallEngineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * RecallController — Phase 2 Communication OS.
 *
 * Handles the admin recall dashboard:
 *   GET  /communication/recall         — view recall queue, stats per trigger
 *   POST /communication/recall/run-now — manually trigger engine (admin only)
 */
class RecallController extends Controller
{
    // ── Index ─────────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $triggerFilter = $request->get('trigger');
        $statusFilter  = $request->get('status', 'pending');

        // All recall items (source_engine = recall)
        $query = CommunicationQueue::where('source_engine', 'recall')
            ->with('patient');

        if ($triggerFilter) {
            $query->where('purpose', $triggerFilter);
        }

        if ($statusFilter && $statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        $items = $query
            ->orderByRaw("FIELD(priority, 'high','medium','low')")
            ->orderBy('created_at', 'desc')
            ->paginate(25)
            ->withQueryString();

        // Stats per trigger type (open items only)
        $stats = CommunicationQueue::where('source_engine', 'recall')
            ->whereNotIn('status', ['closed'])
            ->selectRaw('purpose, COUNT(*) as total, SUM(CASE WHEN priority="high" THEN 1 ELSE 0 END) as high_count')
            ->groupBy('purpose')
            ->get()
            ->keyBy('purpose');

        // Total open recall items
        $openTotal = CommunicationQueue::where('source_engine', 'recall')
            ->whereNotIn('status', ['closed'])
            ->count();

        $triggerLabels = self::triggerLabels();
        $activeNav     = 'recall';

        return view('communication.recall.index', compact(
            'items', 'stats', 'openTotal',
            'triggerFilter', 'statusFilter', 'triggerLabels', 'activeNav'
        ));
    }

    // ── Manual Run ────────────────────────────────────────────────────────────

    public function runNow(RecallEngineService $engine): RedirectResponse
    {
        $summary = $engine->runAll();
        $total   = $summary['total'] ?? 0;

        session()->flash('recall_run_summary', $summary);

        return redirect()
            ->route('communication.recall.index')
            ->with('success', "Recall Engine ran successfully. {$total} item(s) queued.");
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public static function triggerLabels(): array
    {
        return [
            'recall_no_visit'      => '6-Month No Visit',
            'recall_approved_plan' => 'Approved Plan, No Appt',
            'recall_post_op'       => 'Post-Op Follow-Up',
            'recall_lab_received'  => 'Lab Ready, No Appt',
            'recall_7day_followup' => '7-Day Follow-Up',
            'recall_birthday'      => 'Birthday',
        ];
    }
}
