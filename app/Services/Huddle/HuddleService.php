<?php

namespace App\Services\Huddle;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * HuddleService — assembles the daily morning-huddle briefing.
 * ----------------------------------------------------------------------------
 * Pure database aggregation (no AI). Returns an ordered list of sections; each
 * section is built in its own guarded method so a failure in one module (e.g.
 * inventory) never breaks the whole briefing.
 *
 * H1 covers the TODAY-facing sections:
 *   - Schedule overview
 *   - Patient safety flags (medical alerts / allergies)
 *   - Money to collect (today's patients with a balance)
 *   - Treatment opportunities (pending plans + recalls due)
 *
 * H2 will append yesterday's flow, failures, tasks, lab, and stock.
 */
class HuddleService
{
    /**
     * Build the full briefing as a structured array:
     *   ['date' => 'Y-m-d', 'sections' => [ ['title','headline','lines'[]], ... ]]
     */
    public function build(?int $branchId = null, ?string $date = null): array
    {
        $day       = $date ? Carbon::parse($date) : today();
        $yesterday = $day->copy()->subDay();
        $appts     = $this->todaysAppointments($branchId, $day);

        $sections = array_values(array_filter([
            // ── Today ──────────────────────────────────────────────────────
            $this->safe(fn () => $this->scheduleSection($appts, $day)),
            $this->safe(fn () => $this->safetySection($appts)),
            $this->safe(fn () => $this->moneySection($appts)),
            $this->safe(fn () => $this->opportunitiesSection($appts, $branchId, $day)),
            // ── Yesterday (H2) ───────────────────────────────────────────────
            $this->safe(fn () => $this->yesterdaySection($branchId, $yesterday)),
            $this->safe(fn () => $this->failuresSection($branchId, $yesterday)),
            // ── Operations (H2) ──────────────────────────────────────────────
            $this->safe(fn () => $this->tasksSection($day)),
            $this->safe(fn () => $this->labSection($day)),
            $this->safe(fn () => $this->stockSection()),
            $this->safe(fn () => $this->newPatientsSection($branchId, $day)),
        ]));

        return ['date' => $day->toDateString(), 'sections' => $sections];
    }

    /** Render the briefing as plain text (for the command and the chat tool). */
    public function render(?int $branchId = null, ?string $date = null): string
    {
        $data  = $this->build($branchId, $date);
        $when  = Carbon::parse($data['date'])->format('l, d M Y');

        $out = ["DAILY HUDDLE — {$when}", str_repeat('=', 40)];

        foreach ($data['sections'] as $s) {
            $out[] = '';
            $head = $s['headline'] ? " ({$s['headline']})" : '';
            $out[] = "▸ {$s['title']}{$head}";
            foreach ($s['lines'] as $line) {
                $out[] = "   {$line}";
            }
        }

        return implode("\n", $out);
    }

    // ── Sections ───────────────────────────────────────────────────────────

    protected function scheduleSection(Collection $appts, Carbon $day): array
    {
        $active = $appts->whereNotIn('status', ['cancelled', 'no_show']);

        $lines = [];
        if ($active->isEmpty()) {
            $lines[] = 'No appointments booked.';
            return ['title' => "Today's Schedule", 'headline' => '0 booked', 'lines' => $lines];
        }

        $byDoctor = $active->groupBy(fn ($a) => $a->doctor->name ?? 'Unassigned')
            ->map->count()->sortDesc();

        foreach ($byDoctor as $doc => $n) {
            $lines[] = "- {$doc}: {$n}";
        }

        $times = $active->filter(fn ($a) => $a->appointment_time)
            ->sortBy('appointment_time');
        if ($times->isNotEmpty()) {
            $first = Carbon::parse($times->first()->appointment_time)->format('H:i');
            $last  = Carbon::parse($times->last()->appointment_time)->format('H:i');
            $lines[] = "First: {$first} · Last: {$last}";
        }

        $cancelled = $appts->where('status', 'cancelled')->count();
        $noShow    = $appts->where('status', 'no_show')->count();
        if ($cancelled || $noShow) {
            $lines[] = "Already cancelled/no-show today: {$cancelled} cancelled, {$noShow} no-show";
        }

        return [
            'title'    => "Today's Schedule",
            'headline' => $active->count() . ' appointments',
            'lines'    => $lines,
        ];
    }

    protected function safetySection(Collection $appts): array
    {
        $patients = $this->uniquePatients($appts);
        $lines = [];

        foreach ($patients as $p) {
            $flags = [];
            if (!empty($p->medical_alert)) {
                $flags[] = trim($p->medical_alert);
            }
            if (is_array($p->allergies) && count($p->allergies)) {
                $flags[] = 'allergies: ' . implode(', ', $p->allergies);
            }
            if (is_array($p->medical_conditions) && count($p->medical_conditions)) {
                $flags[] = implode(', ', $p->medical_conditions);
            }
            if ($flags) {
                $lines[] = "⚠ {$p->name}: " . implode(' · ', $flags);
            }
        }

        if (empty($lines)) {
            return ['title' => 'Patient Safety Flags', 'headline' => 'all clear', 'lines' => ["No medical alerts among today's patients."]];
        }

        return ['title' => 'Patient Safety Flags', 'headline' => count($lines) . ' to note', 'lines' => $lines];
    }

    protected function moneySection(Collection $appts): array
    {
        $patients = $this->uniquePatients($appts)
            ->filter(fn ($p) => (float) $p->outstanding_balance > 0)
            ->sortByDesc('outstanding_balance');

        if ($patients->isEmpty()) {
            return ['title' => 'Money to Collect', 'headline' => 'nothing outstanding', 'lines' => ["No outstanding balances among today's patients."]];
        }

        $total = $patients->sum(fn ($p) => (float) $p->outstanding_balance);
        $lines = $patients->map(fn ($p) => "- {$p->name}: " . $this->money($p->outstanding_balance))->values()->all();

        return [
            'title'    => 'Money to Collect',
            'headline' => $this->money($total) . ' across ' . $patients->count(),
            'lines'    => $lines,
        ];
    }

    protected function opportunitiesSection(Collection $appts, ?int $branchId, Carbon $day): array
    {
        $patients = $this->uniquePatients($appts);
        $ids      = $patients->pluck('id')->all();
        $lines    = [];

        // Pending (un-accepted) treatment plans for today's patients.
        if (!empty($ids)) {
            $pending = TreatmentPlan::whereIn('patient_id', $ids)
                ->whereNull('accepted_at')
                ->get(['id', 'patient_id', 'plan_name', 'total'])
                ->groupBy('patient_id');

            foreach ($patients as $p) {
                $plans = $pending->get($p->id);
                if ($plans && $plans->count()) {
                    $value = $this->money($plans->sum('total'));
                    $lines[] = "- {$p->name}: {$plans->count()} pending plan(s), {$value}";
                }
            }
        }

        // Recalls / follow-ups due today (any patient, not only those booked).
        $recalls = Patient::query()
            ->where(function ($q) use ($day) {
                $q->whereDate('next_recall_date', $day)
                  ->orWhereDate('follow_up_date', $day);
            })
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->limit(15)->get(['id', 'name', 'next_recall_date', 'follow_up_date']);

        foreach ($recalls as $r) {
            $lines[] = "↻ Recall/follow-up due: {$r->name}";
        }

        if (empty($lines)) {
            return ['title' => 'Treatment Opportunities', 'headline' => 'none today', 'lines' => ['No pending plans or recalls flagged for today.']];
        }

        return ['title' => 'Treatment Opportunities', 'headline' => count($lines) . ' opportunities', 'lines' => $lines];
    }

    // ── Yesterday & operations (H2) ────────────────────────────────────────

    protected function yesterdaySection(?int $branchId, Carbon $yest): array
    {
        $lines = [];

        // Visits completed yesterday + money collected on them.
        $visits    = \App\Models\TreatmentVisit::whereDate('visit_date', $yest)
            ->where('status', 'completed')->get(['amount_paid', 'cost']);
        $visitCount = $visits->count();
        $visitMoney = (float) $visits->sum('amount_paid');
        $lines[] = "Visits completed: {$visitCount}" . ($visitCount ? " (collected " . $this->money($visitMoney) . " on visits)" : '');

        // Total collections yesterday from the finance ledger (all sources).
        $collected = $visitMoney;
        if (class_exists(\App\Models\Finance\FinanceTransaction::class)) {
            $collected = (float) \App\Models\Finance\FinanceTransaction::where('type', 'income')
                ->where('status', 'active')
                ->whereDate('transaction_date', $yest)
                ->sum('amount');
            $lines[] = "Total collections (all sources): " . $this->money($collected);
        }

        // Appointments that completed yesterday.
        $done = Appointment::whereDate('appointment_date', $yest)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', 'done')->count();
        $lines[] = "Appointments completed: {$done}";

        return [
            'title'    => "Yesterday's Flow",
            'headline' => $this->money($collected) . ' collected',
            'lines'    => $lines,
        ];
    }

    protected function failuresSection(?int $branchId, Carbon $yest): array
    {
        $appts = Appointment::whereDate('appointment_date', $yest)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereIn('status', ['no_show', 'cancelled'])
            ->with('patient:id,name')->get();

        if ($appts->isEmpty()) {
            return ['title' => "Yesterday's Failures", 'headline' => 'clean day', 'lines' => ['No no-shows or cancellations yesterday.']];
        }

        $noShow    = $appts->where('status', 'no_show');
        $cancelled = $appts->where('status', 'cancelled');
        $lines = [];

        foreach ($noShow as $a) {
            $lines[] = "✗ No-show: " . ($a->patient->name ?? 'Unknown') . " — consider a recall call";
        }
        foreach ($cancelled as $a) {
            $lines[] = "⊘ Cancelled: " . ($a->patient->name ?? 'Unknown');
        }

        return [
            'title'    => "Yesterday's Failures",
            'headline' => "{$noShow->count()} no-show, {$cancelled->count()} cancelled",
            'lines'    => $lines,
        ];
    }

    protected function tasksSection(Carbon $day): array
    {
        if (!class_exists(\App\Models\Task::class)) {
            return [];
        }

        $callbackCats = ['call', 'whatsapp', 'follow_up'];

        // Phase 3: visibleToReception() hides Automation-record (System) tasks
        // from the Huddle report once tasks.human_system_split is on.
        $overdue = \App\Models\Task::where('status', 'pending')
            ->visibleToReception()
            ->whereDate('due_date', '<', $day)
            ->orderBy('due_date')->limit(10)->get(['title', 'category', 'due_date']);

        $dueToday = \App\Models\Task::where('status', 'pending')
            ->visibleToReception()
            ->whereDate('due_date', $day)->get(['title', 'category']);

        $lines = [];
        if ($overdue->isNotEmpty()) {
            $lines[] = "Overdue ({$overdue->count()}):";
            foreach ($overdue as $t) {
                $tag = in_array($t->category, $callbackCats, true) ? "📞 " : "";
                $lines[] = "  - {$tag}{$t->title} (" . optional($t->due_date)->format('d M') . ")";
            }
        }
        if ($dueToday->isNotEmpty()) {
            $calls = $dueToday->whereIn('category', $callbackCats)->count();
            $lines[] = "Due today: {$dueToday->count()}" . ($calls ? " (incl. {$calls} call/message)" : '');
        }

        if (empty($lines)) {
            return ['title' => 'Tasks & Callbacks', 'headline' => 'nothing pending', 'lines' => ['No overdue or due-today tasks.']];
        }

        return ['title' => 'Tasks & Callbacks', 'headline' => "{$overdue->count()} overdue", 'lines' => $lines];
    }

    protected function labSection(Carbon $day): array
    {
        if (!class_exists(\App\Models\LabCase::class)) {
            return [];
        }

        $base = \App\Models\LabCase::whereNotIn('status', ['complete', 'rejected'])
            ->whereNull('final_received_date');

        $overdue = (clone $base)->whereDate('expected_return_date', '<', $day)
            ->with('patient:id,name')->limit(10)->get();
        $dueToday = (clone $base)->whereDate('expected_return_date', $day)
            ->with('patient:id,name')->get();

        if ($overdue->isEmpty() && $dueToday->isEmpty()) {
            return ['title' => 'Lab Cases', 'headline' => 'none due', 'lines' => ['No lab cases due or overdue.']];
        }

        $lines = [];
        foreach ($overdue as $c) {
            $lines[] = "⏰ OVERDUE: {$c->case_number} — " . ($c->patient->name ?? '');
        }
        foreach ($dueToday as $c) {
            $lines[] = "Due today: {$c->case_number} — " . ($c->patient->name ?? '');
        }

        return ['title' => 'Lab Cases', 'headline' => "{$overdue->count()} overdue, {$dueToday->count()} due today", 'lines' => $lines];
    }

    protected function stockSection(): array
    {
        if (!class_exists(\App\Models\Inventory\InventoryItem::class)) {
            return [];
        }

        $low = \App\Models\Inventory\InventoryItem::where('is_active', true)->get()
            ->filter(fn ($i) => (bool) ($i->is_low_stock ?? false))
            ->take(15);

        if ($low->isEmpty()) {
            return ['title' => 'Inventory Alerts', 'headline' => 'all stocked', 'lines' => ['No items below reorder level.']];
        }

        $lines = $low->map(fn ($i) => "🔻 {$i->product_name} (qty {$i->total_stock}, min {$i->minimum_qty})")->values()->all();

        return ['title' => 'Inventory Alerts', 'headline' => $low->count() . ' low', 'lines' => $lines];
    }

    protected function newPatientsSection(?int $branchId, Carbon $day): array
    {
        $new = Patient::whereDate('created_at', $day)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->limit(15)->get(['name', 'patient_id']);

        if ($new->isEmpty()) {
            return ['title' => 'New Patients', 'headline' => 'none yet', 'lines' => ['No new registrations today.']];
        }

        $lines = $new->map(fn ($p) => "+ {$p->name} ({$p->patient_id})")->all();

        return ['title' => 'New Patients', 'headline' => $new->count() . ' registered', 'lines' => $lines];
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    protected function todaysAppointments(?int $branchId, Carbon $day): Collection
    {
        return Appointment::query()
            ->whereDate('appointment_date', $day)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->with(['patient', 'doctor:id,name'])
            ->orderBy('appointment_time')
            ->get();
    }

    /** Unique patients across today's appointments. */
    protected function uniquePatients(Collection $appts): Collection
    {
        return $appts->pluck('patient')->filter()->unique('id')->values();
    }

    /** Run a section builder, returning null if it throws (graceful degrade). */
    protected function safe(callable $fn): ?array
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function money($value): string
    {
        return 'Rs. ' . number_format((float) $value, 0);
    }
}
