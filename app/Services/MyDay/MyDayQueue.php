<?php

namespace App\Services\MyDay;

use App\Models\LabCase;
use App\Models\Task;
use App\Models\User;
use App\Services\Relationship\TodayActionsEngine;
use App\Services\Relationship\TodayActionsVisibility;
use App\Services\Relationship\TodayCallList;
use Illuminate\Support\Facades\DB;

/**
 * MY DAY — one ordered queue of work, for one person.
 *
 * ── WHY THIS EXISTS ─────────────────────────────────────────────────────────
 * Staff work was spread across five surfaces — the huddle, Today's Actions,
 * Tasks, Lab, Inventory — each organised by MODULE. A receptionist's day is
 * organised by TIME and by PERSON. That mismatch, not the number of pages, is
 * why nobody knew what to do first.
 *
 * This is NOT a dashboard. A dashboard of five panels is the same scatter with
 * more scrolling and still no order. It is one list, top to bottom, and when
 * it is empty the day's admin is done.
 *
 * ── WHAT IT IS NOT ──────────────────────────────────────────────────────────
 * READ ONLY, and it owns no data. Every row is a pointer at a record that
 * already lives in a module, and every write still happens through that
 * module's own rules. Nothing here becomes a second source of truth — the one
 * mistake that would make this page a liability instead of a help.
 *
 * It also does NOT replace the Daily Huddle. The huddle is a ten-minute
 * meeting for the whole team, once. This is one person's whole shift. Merging
 * them destroys both: the meeting becomes a to-do list, and the to-do list
 * becomes something you look at once and close.
 */
class MyDayQueue
{
    public function __construct(
        private readonly TodayActionsEngine $actions,
        private readonly TodayActionsVisibility $visibility,
        private readonly TodayCallList $callList,
    ) {}

    /**
     * @return array{bands: array<int, array<string, mixed>>, total: int}
     */
    public function build(User $user): array
    {
        $sources = $this->collect($user);

        $bands = [];
        $total = 0;

        foreach (config('my_day.bands', []) as $key => $band) {
            $rows = [];

            foreach ($band['sources'] ?? [] as $source) {
                $rows = array_merge($rows, $sources[$source] ?? []);
            }

            $boards = $band['boards'] ?? [];

            // A band with nothing in it is not rendered at all. An empty
            // "Before close" heading every morning teaches people to skim
            // past headings, which is exactly what we are trying to undo.
            //
            // A band hosting a board is never empty in this sense: the board
            // renders its own empty state, in its own words, and hiding the
            // heading would make the Tasks board vanish on a clear day with
            // no explanation.
            if (empty($rows) && empty($boards)) {
                continue;
            }

            $bands[] = [
                'key'    => $key,
                'label'  => $band['label'] ?? ucfirst($key),
                'hint'   => $band['hint'] ?? null,
                'rows'   => $rows,
                'boards' => $boards,
            ];

            $total += count($rows);
        }

        return ['bands' => $bands, 'total' => $total];
    }

    /** Every source, keyed by the name config/my_day.php uses. */
    private function collect(User $user): array
    {
        return [
            'confirm_appointments' => $this->confirmAppointments($user),
            'unsent_lab'           => $this->unsentLab($user),
            'calls'                => $this->calls($user),
            'tasks'                => $this->tasks($user),
            'lab_chase'            => $this->labChase($user),
            'overdue_expenses'     => $this->overdueExpenses($user),
            'low_stock'            => $this->lowStock($user),
        ];
    }

    private function limit(): int
    {
        return (int) config('my_day.per_source_limit', 8);
    }

    /**
     * One row. Every field is here because the person has to decide something
     * from it without opening anything:
     *   do    — the verb. What you actually do, not what the record is called.
     *   who   — the patient, vendor or item it concerns.
     *   note  — the one detail that changes how you do it.
     *   url   — where it gets done. V1 links out; closing in place is next.
     */
    private function row(
        string $kind,
        string $do,
        ?string $who,
        ?string $note,
        string $url,
        bool $urgent = false,
        ?array $action = null,
    ): array {
        return [
            'kind'   => $kind,
            'do'     => $do,
            'who'    => $who,
            'note'   => $note,
            // Every link carries ?from=my-day so the module it opens can
            // offer a way back. Without it the page you came from is a
            // browser Back button away at best, and staff stop returning.
            'url'    => $this->returnable($url),
            'urgent' => $urgent,
            'action' => $action,
        ];
    }

    /**
     * ONE BUTTON, or nothing.
     *
     * A row gets an action only where the next step is not a judgement. A
     * lab case waiting to be sent has one obvious next status; a stock item
     * running low does NOT have an obvious quantity, supplier or price, so it
     * gets a link to the product and no button. Guessing on the second kind
     * puts wrong numbers in the ledger, which is worse than a click.
     *
     * @return array{label: string, url: string, confirm?: string}
     */
    private function action(string $label, string $url, ?string $confirm = null): array
    {
        return array_filter([
            'label'   => $label,
            'url'     => $url,
            'confirm' => $confirm,
        ]);
    }

    /** Append ?from=my-day without trampling a query string the route built. */
    private function returnable(string $url): string
    {
        return $url . (str_contains($url, '?') ? '&' : '?') . 'from=my-day';
    }

    // ── Sources ─────────────────────────────────────────────────────────────

    /**
     * Today's appointments that nobody has confirmed. One row for the whole
     * batch, not one per patient: at 9:30 this is a single sitting of calls,
     * and eight rows would push everything else off the screen.
     */
    private function confirmAppointments(User $user): array
    {
        $count = DB::table('appointments')
            ->where('branch_id', $user->branch_id)
            ->whereDate('appointment_date', today())
            ->where('status', 'scheduled')
            ->count();

        if ($count === 0) {
            return [];
        }

        return [$this->row(
            'appointment',
            "Confirm {$count} " . ($count === 1 ? 'appointment' : 'appointments') . ' for today',
            null,
            'Still marked scheduled — nobody has called',
            route('appointments.index', ['date' => today()->toDateString()]),
        )];
    }

    /**
     * Lab cases written but still in the clinic. Top of the day on purpose:
     * it is the only thing on this list that can be cleared in ten minutes,
     * and a case sitting unsent is the most expensive kind of forgetting.
     */
    private function unsentLab(User $user): array
    {
        try {
            $cases = DB::table('lab_cases')
                ->join('patients', 'patients.id', '=', 'lab_cases.patient_id')
                ->where('lab_cases.branch_id', $user->branch_id)
                ->whereIn('lab_cases.status', LabCase::UNSENT_STATUSES)
                ->whereNull('lab_cases.deleted_at')
                ->select([
                    'lab_cases.id', 'lab_cases.case_number', 'lab_cases.status',
                    'lab_cases.created_at', 'patients.name as patient_name',
                ])
                ->orderBy('lab_cases.created_at')
                ->limit($this->limit())
                ->get();
        } catch (\Throwable $e) {
            return [];
        }

        $canEdit = $user->canAccess('lab', 'edit');

        return $cases->map(function ($c) use ($canEdit) {
            $days = $c->created_at
                ? (int) \Carbon\Carbon::parse($c->created_at)->startOfDay()->diffInDays(today())
                : 0;

            // The same next step the case page offers as its primary button —
            // LabCase::nextAction() answers it for both, so a clinic that
            // changes the flow changes it once.
            $next = LabCase::nextActionFor((string) $c->status);

            return $this->row(
                'lab',
                'Send lab case ' . $c->case_number,
                $c->patient_name,
                $days === 0 ? 'Written today' : "Waiting {$days} " . ($days === 1 ? 'day' : 'days'),
                route('lab.show', $c->id),
                $days >= 2,
                $canEdit && $next
                    ? $this->action($next['label'], route('lab.transition', [$c->id, $next['to']]))
                    : null,
            );
        })->all();
    }

    /**
     * Patients to contact — ONE ROW PER PATIENT, folded exactly the way the
     * Today's Actions board folds them.
     *
     * NOT RENDERED while the morning band hosts the 'calls' BOARD instead of
     * this source: the real board renders there now, drawer and all. Kept
     * because the swap back is one config line, and because of what follows.
     *
     * IT GOES THROUGH TodayCallList, NOT THE ENGINE DIRECTLY. The first
     * version looped the engine's output item by item, so a patient with two
     * reasons produced two rows and a receptionist working this page would
     * have rung them twice — precisely the failure the 11 Sep rule was
     * written to stop: one patient, one row, one call. Rebuilding the list by
     * hand walked straight back into it. The rule lives in one place now, and
     * if the board's folding changes this changes with it.
     */
    private function calls(User $user): array
    {
        try {
            $todayRaw = $this->visibility->apply(
                $this->actions->generate(includeDone: false, dueWindow: 'today')
            );

            // Yesterday's missed calls ride on the patient's row as a carried
            // reason rather than appearing as separate work — same as the
            // board. Passing an empty overdue set would lose them.
            $overdueRaw = $this->visibility->apply(
                $this->actions->generate(includeDone: false, dueWindow: 'overdue')
            );

            $list = $this->callList->build(
                $todayRaw,
                $overdueRaw,
                today(),
                \App\Http\Controllers\Relationship\TodayController::categoryMeta(),
            );
        } catch (\Throwable $e) {
            return [];
        }

        $rows = [];

        foreach ($list['rows'] ?? [] as $r) {
            if (count($rows) >= $this->limit()) {
                break;
            }

            // A row already handled today is not work. The board shows it
            // faded for reassurance; a queue you are working down should not
            // carry it at all.
            if (! empty($r['isDone'])) {
                continue;
            }

            $reasons = (int) ($r['reasonCount'] ?? 1);

            // The note carries what the board's chips carry: that this one
            // call covers more than one thing. Without it someone rings, deals
            // with the first reason and hangs up.
            $note = $r['primary']['whyText'] ?? null;
            if ($reasons > 1) {
                $note = trim(($note ? $note . ' · ' : '')
                    . $reasons . ' reasons, one call');
            }
            if (! empty($r['attempted']) && ! empty($r['stTxt'])) {
                $note = trim(($note ? $note . ' · ' : '') . $r['stTxt']);
            }

            $rows[] = $this->row(
                'call',
                $r['primary']['doText'] ?? 'Call patient',
                $r['patient_name'] ?? null,
                $note,
                route('relationship.today'),
                ($r['priority'] ?? '') === 'high',
            );
        }

        return $rows;
    }

    /**
     * NOT RENDERED while config/my_day.php gives the morning band the
     * 'tasks' BOARD instead of this source. Kept because the two are a
     * straight swap: drop 'boards' and put 'tasks' back in 'sources' and the
     * summary rows return. Deleting it would make that a rewrite.
     *
     * Tasks assigned to THIS person and due today or earlier. Someone else's
     * task is not this person's day, which is the whole difference between
     * this page and the task board.
     */
    private function tasks(User $user): array
    {
        $tasks = Task::query()
            ->where('branch_id', $user->branch_id)
            ->where('assigned_to', $user->id)
            ->visibleToReception()
            ->open()
            ->whereDate('due_date', '<=', today())
            ->with('patient:id,name')
            ->orderBy('due_date')
            ->orderByRaw("FIELD(priority,'urgent','high','medium','low')")
            ->limit($this->limit())
            ->get();

        return $tasks->map(fn (Task $t) => $this->row(
            'task',
            $t->title,
            $t->patient?->name,
            // The attempt trail, because "called four times, never picks up"
            // and "nobody has touched it" need opposite responses and looked
            // identical before the outcome trail existed.
            $t->daysLate() > 0
                ? $t->daysLate() . ' ' . ($t->daysLate() === 1 ? 'day' : 'days') . ' late'
                  . ($t->attemptLabel() ? ' · ' . $t->attemptLabel() : '')
                : ($t->attemptLabel() ?: null),
            route('tasks.index', ['view' => 'today']),
            $t->daysLate() > 0 || $t->priority === 'urgent',
        ))->all();
    }

    /**
     * Lab work that is late coming back. Chasing, not sending.
     *
     * NO BUTTON, DELIBERATELY. Chasing is a phone call to the lab; the case
     * does not change status because you rang them. A button marking it
     * "received" would be a lie told to make a row disappear, and the whole
     * point of the 22 Sep ruling is that a case stays on the list until it is
     * actually delivered to the patient.
     */
    private function labChase(User $user): array
    {
        try {
            $cases = DB::table('lab_cases')
                ->join('patients', 'patients.id', '=', 'lab_cases.patient_id')
                ->leftJoin('lab_vendors', 'lab_vendors.id', '=', 'lab_cases.lab_vendor_id')
                ->where('lab_cases.branch_id', $user->branch_id)
                ->whereIn('lab_cases.status', LabCase::OPEN_STATUSES)
                // A case that has never left the clinic is on the "send it"
                // list above. Listing it here too told a receptionist to chase
                // a lab for work it never received — one case, two rows, two
                // contradictory instructions.
                ->whereNotIn('lab_cases.status', LabCase::UNSENT_STATUSES)
                ->whereNull('lab_cases.deleted_at')
                ->whereDate('lab_cases.expected_return_date', '<', today())
                ->select([
                    'lab_cases.id', 'lab_cases.case_number', 'lab_cases.expected_return_date',
                    'patients.name as patient_name', 'lab_vendors.name as lab_name',
                ])
                ->orderBy('lab_cases.expected_return_date')
                ->limit($this->limit())
                ->get();
        } catch (\Throwable $e) {
            return [];
        }

        return $cases->map(function ($c) {
            $days = (int) \Carbon\Carbon::parse($c->expected_return_date)->diffInDays(today());

            return $this->row(
                'lab',
                'Chase ' . ($c->lab_name ?: 'the lab') . ' — ' . $c->case_number,
                $c->patient_name,
                "{$days} " . ($days === 1 ? 'day' : 'days') . ' overdue',
                route('lab.show', $c->id),
                true,
            );
        })->all();
    }

    /**
     * BILLS THE CLINIC HAS NOT PAID AND IS NOW LATE ON.
     *
     * OVERDUE ONLY. An unpaid bill that is not yet due is not today's work,
     * and listing it here would teach people to skim the section — which is
     * how the one that IS late gets skimmed past too.
     *
     * NO BUTTON, same rule as stock: settling a bill needs an amount, a mode
     * and an account, and `expenses.mark-paid` asks for all three. The row
     * opens the bill; the money is moved there.
     *
     * NOT BRANCH-SCOPED, because the finance module is not: FinanceController
     * reads expenses clinic-wide and `finance_expenses` carries `clinic_id`,
     * not `branch_id`. Matching the module is right until Phase 3 tenancy
     * changes both together. Doing it differently here would make My Day and
     * the Expenses page disagree about what is outstanding.
     */
    private function overdueExpenses(User $user): array
    {
        if (! $user->canAccess('finance')) {
            return [];
        }

        try {
            $bills = \App\Models\Finance\FinanceExpense::query()
                ->overdue()
                ->whereNotIn('status', ['rejected', 'cancelled'])
                ->with('vendor:id,name')
                ->orderBy('due_date')
                ->limit($this->limit())
                ->get();
        } catch (\Throwable $e) {
            return [];
        }

        return $bills->map(function ($bill) {
            $days = (int) \Carbon\Carbon::parse($bill->due_date)->diffInDays(today());

            return $this->row(
                'payment',
                'Pay ' . $bill->title,
                $bill->vendor?->name,
                '₹' . number_format((float) $bill->total_amount, 2)
                    . ' · ' . $days . ' ' . ($days === 1 ? 'day' : 'days') . ' overdue',
                route('finance.expenses.edit', $bill->id),
                // A week late is a different conversation from a day late.
                $days >= 7,
            );
        })->all();
    }

    /**
     * Stock at or below its minimum. One row per item, because each one is a
     * separate decision about a separate supplier.
     *
     * NO BUTTON, DELIBERATELY. "Order" is a quantity, a supplier and a price,
     * and none of the three can be guessed from a low-stock row. The link goes
     * straight to that product's page — not the inventory dashboard, which is
     * where this used to land and where you then had to hunt for the item you
     * had just been told about.
     */
    private function lowStock(User $user): array
    {
        if (! $user->canAccess('inventory')) {
            return [];
        }

        try {
            $items = DB::table('inventory_items as i')
                ->leftJoin(
                    DB::raw('(SELECT inventory_item_id, SUM(available_qty) as total_qty FROM inventory_stocks GROUP BY inventory_item_id) as s'),
                    's.inventory_item_id', '=', 'i.id'
                )
                ->where('i.is_active', true)
                ->whereRaw('COALESCE(s.total_qty, 0) <= i.minimum_qty')
                ->select(['i.id', 'i.product_name', 'i.minimum_qty', DB::raw('COALESCE(s.total_qty, 0) as total_qty')])
                ->orderBy(DB::raw('COALESCE(s.total_qty, 0)'))
                ->limit($this->limit())
                ->get();
        } catch (\Throwable $e) {
            return [];
        }

        return $items->map(fn ($i) => $this->row(
            'stock',
            'Order ' . $i->product_name,
            null,
            $i->total_qty <= 0
                ? 'Out of stock'
                : "{$i->total_qty} left, minimum {$i->minimum_qty}",
            route('inventory.products.show', $i->id),
            $i->total_qty <= 0,
        ))->all();
    }
}
