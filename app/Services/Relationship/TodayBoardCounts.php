<?php

namespace App\Services\Relationship;

use App\Http\Controllers\Relationship\TodayController;
use Illuminate\Support\Carbon;

/**
 * TodayBoardCounts — the ONE number the Today's Actions board shows, for any
 * other surface that wants to quote it (Daily Huddle tile, 2026-09-11).
 *
 * Until now the Huddle counted for itself — TodayActionsProjector::liveSummary()
 * (every category, no due-window, no Settings filters, one count per REASON)
 * plus its own comm-list total — and told the doctor "87 to do" while the desk
 * had 13 people to call. Three counters, three answers. This is the fourth
 * counter only in the sense that it is the board's own pipeline reused:
 * same engine windows, same visibility rules, same TodayCallList grouping,
 * so the number here is the number on the board, by construction.
 *
 * Presentation-free: returns counts only.
 */
class TodayBoardCounts
{
    public function __construct(
        private readonly TodayActionsEngine     $engine,
        private readonly TodayActionsVisibility $visibility,
        private readonly TodayCallList          $callList,
    ) {}

    /**
     * @return array{today:int, reasons:int, pending:int, done:int}
     *   today   — patients with a call due today (open rows on the board)
     *   reasons — open reasons folded into those calls
     *   pending — patients with overdue calls and nothing today (Pending Calls)
     *   done    — patients fully handled today
     */
    public function counts(): array
    {
        try {
            $today   = Carbon::today();
            $todayRaw   = $this->visibility->apply($this->engine->generate(includeDone: true,  dueWindow: 'today'));
            $overdueRaw = $this->visibility->apply($this->engine->generate(includeDone: false, dueWindow: 'overdue'));

            $list = $this->callList->build($todayRaw, $overdueRaw, $today, TodayController::categoryMeta());

            return [
                'today'   => count($list['rows']),
                'reasons' => array_sum(array_map(fn ($r) => $r['reasonCount'], $list['rows'])),
                'pending' => count($list['pendingRows']),
                'done'    => count($list['doneRows']),
            ];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('TodayBoardCounts failed', ['error' => $e->getMessage()]);

            return ['today' => 0, 'reasons' => 0, 'pending' => 0, 'done' => 0];
        }
    }
}
