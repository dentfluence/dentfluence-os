<?php

namespace App\Http\Controllers\Relationship;

use App\Http\Controllers\Controller;
use App\Models\CommunicationQueue;
use Illuminate\View\View;

/**
 * RecallPipelineController — PRE (Phase 1 · Workstream D, slice 3).
 *
 * A read-only board of recall communications. Recalls live in the legacy
 * `communication_queue` (purpose = 'recall' or source_engine = 'recall');
 * columns come from that table's RELIABLE `status` vocabulary
 * (CommunicationQueue::STATUSES). Recall journeys are not synced in shadow yet,
 * so there is no journey column here — this is a faithful legacy read.
 *
 * Fully additive: NEW route (relationship.recalls); the legacy Communication
 * List surface is untouched. No writes, no migration.
 *
 * Route: GET /relationship/recalls  [relationship.recalls]
 */
class RecallPipelineController extends Controller
{
    /** Max cards rendered per column (rest summarised as "+N more"). */
    private const CARDS_PER_COLUMN = 40;

    /** Column colours for the legacy CommunicationQueue statuses. */
    private const STATUS_STYLES = [
        'pending'             => ['color' => '#854F0B', 'bg' => '#FAEEDA'],
        'waiting_for_patient' => ['color' => '#185FA5', 'bg' => '#E6F1FB'],
        'overdue'             => ['color' => '#8A1F1F', 'bg' => '#FDECEC'],
        'closed'              => ['color' => '#3B6D11', 'bg' => '#EAF3DE'],
    ];

    public function index(): View
    {
        // Read side — recall rows only, grouped by the reliable legacy status.
        $recalls = CommunicationQueue::query()
            ->where(function ($q) {
                $q->where('purpose', 'recall')->orWhere('source_engine', 'recall');
            })
            ->select([
                'id', 'person_name', 'phone', 'channel', 'status', 'priority',
                'follow_up_date', 'due_at', 'attempt_count', 'assigned_to', 'is_overdue',
            ])
            ->orderByRaw('follow_up_date IS NULL, follow_up_date ASC')
            ->orderByDesc('id')
            ->get();

        $grouped = $recalls->groupBy('status');

        // Build ordered columns from the canonical STATUSES map.
        $columns = [];
        foreach (CommunicationQueue::STATUSES as $key => $label) {
            $bucket = $grouped->get($key, collect());
            $columns[] = [
                'key'    => $key,
                'label'  => $label,
                'color'  => self::STATUS_STYLES[$key]['color'] ?? '#534AB7',
                'bg'     => self::STATUS_STYLES[$key]['bg'] ?? '#EEEDFE',
                'count'  => $bucket->count(),
                'items'  => $bucket->take(self::CARDS_PER_COLUMN),
                'hidden' => max(0, $bucket->count() - self::CARDS_PER_COLUMN),
            ];
        }

        $openCount    = $recalls->where('status', '!=', 'closed')->count();
        $overdueCount = $recalls->filter(fn ($r) => $r->is_overdue || $r->status === 'overdue')->count();

        return view('relationship.recalls.index', [
            'columns'      => $columns,
            'total'        => $recalls->count(),
            'openCount'    => $openCount,
            'overdueCount' => $overdueCount,
        ]);
    }
}
