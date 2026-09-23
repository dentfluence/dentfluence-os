<?php

namespace App\Services\MyDay;

use App\Models\LabCase;
use App\Models\Task;
use App\Models\User;
use App\Services\Relationship\TodayActionsEngine;
use App\Services\Relationship\TodayActionsVisibility;
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

            // A band with nothing in it is not rendered at all. An empty
            // "Before close" heading every morning teaches people to skim
            // past headings, which is exactly what we are trying to undo.
            if (empty($rows)) {
                continue;
            }

            $bands[] = [
                'key'   => $key,
                'label' => $band['label'] ?? ucfirst($key),
                'hint'  => $band['hint'] ?? null,
                'rows'  => $rows,
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
    private function row(string $kind, string $do, ?string $who, ?string $note, string $url, bool $urgent = false): array
    {
        return [
            'kind'   => $kind,
            'do'     => $do,
            'who'    => $who,
            'note'   => $note,
            'url'    => $url,
            'urgent' => $urgent,
        ];
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
                ->whereIn('lab_cases.status', ['draft', 'order_placed'])
                ->whereNull('lab_cases.deleted_at')
                ->select(['lab_cases.id', 'lab_cases.case_number', 'lab_cases.created_at', 'patients.name as patient_name'])
                ->orderBy('lab_cases.created_at')
                ->limit($this->limit())
                ->get();
        } catch (\Throwable $e) {
            return [];
        }

        return $cases->map(function ($c) {
            $days = $c->created_at
                ? (int) \Carbon\Carbon::parse($c->created_at)->startOfDay()->diffInDays(today())
                : 0;

            return $this->row(
                'lab',
                'Send lab case ' . $c->case_number,
                $c->patient_name,
                $days === 0 ? 'Written today' : "Waiting {$days} " . ($days === 1 ? 'day' : 'days'),
                route('lab.show', $c->id),
                $days >= 2,
            );
        })->all();
    }

    /**
     * Patients to contact, from the same engine Today's Actions renders — not
     * a second query with its own idea of who needs calling.
     */
    private function calls(User $user): array
    {
        try {
            $grouped = $this->visibility->apply(
                $this->actions->generate(includeDone: false, dueWindow: 'today')
            );
        } catch (\Throwable $e) {
            return [];
        }

        $rows = [];

        foreach ($grouped as $items) {
            foreach ($items as $item) {
                if (count($rows) >= $this->limit()) {
                    break 2;
                }

                $rows[] = $this->row(
                    'call',
                    $item['suggested_action'] ?? 'Call patient',
                    $item['patient_name'] ?? null,
                    $item['reason'] ?? null,
                    route('relationship.today'),
                    ($item['priority'] ?? '') === 'high',
                );
            }
        }

        return $rows;
    }

    /**
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

    /** Lab work that is late coming back. Chasing, not sending. */
    private function labChase(User $user): array
    {
        try {
            $cases = DB::table('lab_cases')
                ->join('patients', 'patients.id', '=', 'lab_cases.patient_id')
                ->leftJoin('lab_vendors', 'lab_vendors.id', '=', 'lab_cases.lab_vendor_id')
                ->where('lab_cases.branch_id', $user->branch_id)
                ->whereIn('lab_cases.status', LabCase::OPEN_STATUSES)
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
     * Stock at or below its minimum. One row per item, because each one is a
     * separate decision about a separate supplier.
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
            route('inventory.index'),
            $i->total_qty <= 0,
        ))->all();
    }
}
