<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Relationship\TodayController;
use App\Services\MyDay\MyDayQueue;
use App\Services\Tasks\TaskBoardData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * My Day — one ordered queue of work for the signed-in person.
 *
 * One action, no writes, no state. Everything it shows belongs to a module
 * and is changed there; this page only decides what order to face it in.
 *
 * ── EMBEDDED BOARDS (23 Sep) ────────────────────────────────────────────────
 * Summary rows that link out were honest but useless: you read the row, you
 * clicked, you lost your place, and you came back to find the list unchanged
 * because nothing on My Day could close anything. So the bands that host a
 * board get the REAL board — Today's Actions and Tasks — drawers and all, so
 * work is finished on the page where it is listed.
 *
 * ── WHY THIS RESOLVES ANOTHER CONTROLLER ────────────────────────────────────
 * TodayController::boardPayload() builds the calls board out of eight private
 * helpers and six constants on that class, three of which the write endpoints
 * share. Moving all of it into a service to reuse ONE read would put the
 * clinic's most-used board at risk for a refactor nobody asked for. Calling
 * the method is the smaller risk, and it is a read with no writes and no
 * session state. If that ever stops being true, extract it then.
 */
class MyDayController extends Controller
{
    public function index(Request $request, MyDayQueue $queue, TaskBoardData $taskBoard)
    {
        $user = Auth::user();
        $data = $queue->build($user);

        $declared = collect($data['bands'])->flatMap(fn ($b) => $b['boards'] ?? [])->all();

        $boards = [];
        $boardTotal = 0;

        if (in_array('calls', $declared, true)) {
            // A FRESH REQUEST, deliberately. boardPayload() reads ?date= and
            // would happily render a future preview if someone landed on
            // /my-day?date=2026-10-01. My Day is today; the date picker
            // belongs to the board's own page.
            $boards['calls'] = app(TodayController::class)->boardPayload(new Request());

            $boardTotal += (int) ($boards['calls']['totalCount'] ?? 0);
        }

        if (in_array('tasks', $declared, true)) {
            // ── WHOSE TASKS (CEO ruling, 23 Sep) ─────────────────────────
            // "let owner and manager see all task and calls."
            //
            // The first version forced assigned_to even for the owner, on the
            // reasoning that My Day is one person's shift. For an owner or a
            // manager that reasoning is wrong: their shift IS the clinic, and
            // a page that hid the team's work from the person accountable for
            // it sent them to /tasks every morning to find out what was
            // actually happening.
            //
            // The boundary is the SAME one /tasks uses — User::taskScope() —
            // so there is one answer to "whose work may I see" and My Day
            // cannot become a way around it.
            $boards['tasks'] = $taskBoard->build(array_filter([
                'view'        => 'due_now',
                'assigned_to' => $user->seesOwnTasksOnly() ? $user->id : null,
                'per_page'    => 25,
            ]), $user);

            $boardTotal += $boards['tasks']['tasks']->total();
        }

        // ── AN EMPTY SECTION IS NOT A SECTION ────────────────────────────
        // A band hosting a board used to render whatever the board had to
        // say, including its own 64px "Nothing here" panel. On 23 Sep that
        // put an empty TASKS box the height of a screen between the calls
        // and the lab work — a heading, a count of 0, and a paragraph
        // explaining that nothing matched a filter the page does not show.
        //
        // Reading a box to learn it is empty is work. A section with no rows
        // and no board content does not render at all; when EVERY section is
        // empty the page says "You're clear." and stops, which it already
        // did and which is the whole point of it.
        $counts = [
            'calls' => (int) ($boards['calls']['totalCount'] ?? 0),
            'tasks' => (int) ($boards['tasks']['tasks']?->total() ?? 0),
        ];

        $bands = array_values(array_filter(
            $data['bands'],
            function (array $band) use ($counts) {
                if (! empty($band['rows'])) {
                    return true;
                }

                foreach ($band['boards'] ?? [] as $key) {
                    if (($counts[$key] ?? 0) > 0) {
                        return true;
                    }
                }

                return false;
            },
        ));

        return view('my-day.index', [
            'bands'  => $bands,
            'total'  => $data['total'] + $boardTotal,
            'boards' => $boards,
        ]);
    }
}
