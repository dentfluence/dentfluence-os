<?php

declare(strict_types=1);

namespace App\Modules\Huddle\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Huddle\Services\HuddleCloseService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * The "Huddle done" tick and the huddle log.
 *
 * Kept out of HuddleController on purpose — that file is already 78 KB of
 * board assembly and this is a different job: it records whether the meeting
 * happened, it does not build the board.
 */
class HuddleCloseController extends Controller
{
    public function __construct(
        private readonly HuddleCloseService $closeService,
    ) {}

    /** POST /huddle/close — tick today's huddle and freeze the briefing. */
    public function close(Request $request)
    {
        $user = auth()->user();

        try {
            $this->closeService->close($user->branch_id, $user, Carbon::today());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Huddle marked done.');
    }

    /** DELETE /huddle/close — untick today's huddle (same day only). */
    public function reopen(Request $request)
    {
        $user = auth()->user();

        try {
            $this->closeService->reopen($user->branch_id, Carbon::today());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Huddle reopened.');
    }

    /**
     * GET /huddle/history — the huddle log.
     * ?days=30|60|90 (default 30)
     */
    public function history(Request $request)
    {
        $user     = auth()->user();
        $branchId = $user->branch_id;

        $days = (int) $request->query('days', 30);
        $days = in_array($days, [30, 60, 90], true) ? $days : 30;

        $to   = Carbon::today();
        $from = $to->copy()->subDays($days - 1);

        $rows    = $this->closeService->history($branchId, $from, $to);
        $pending = $this->closeService->pendingCount($rows);

        $expected = $rows->where('is_expected', true)->count();
        $doneCount = $rows->where('is_expected', true)->where('is_done', true)->count();

        return view('huddle.history', compact('rows', 'pending', 'days', 'expected', 'doneCount', 'from', 'to'));
    }

    /**
     * GET /huddle/history/{date} — the frozen briefing for one day.
     *
     * Deliberately refuses to recompute. A day nobody ticked has no snapshot
     * and that is what the page says; inventing one from today's live data
     * would put today's stock and today's tasks under a past date.
     */
    public function show(string $date)
    {
        $user = auth()->user();

        try {
            $day = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (\Throwable $e) {
            abort(404);
        }

        if ($day->isFuture()) {
            abort(404);
        }

        $board    = $this->closeService->boardFor($user->branch_id, $day);
        $snapshot = data_get($board?->meta, 'briefing');
        $closedBy = data_get($board?->meta, 'closed_by.name');

        // The dated record — appointments, visits, consultations, money. Always
        // available for any past day, because those rows carry their own date.
        $record = app(\App\Modules\Huddle\Services\HuddleDayRecordService::class)
            ->forDate($user->branch_id, $day);

        return view('huddle.history-show', [
            'day'      => $day,
            'board'    => $board,
            'snapshot' => $snapshot,
            'closedBy' => $closedBy,
            'record'   => $record,
        ]);
    }
}
