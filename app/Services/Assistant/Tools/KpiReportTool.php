<?php

namespace App\Services\Assistant\Tools;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * KpiReportTool — clinic reports & KPIs for the owner/manager. Read-only.
 * Computes common metrics for a period: collections, new patients, appointment
 * stats, no-show rate, treatment acceptance, outstanding balance, pending lab.
 */
class KpiReportTool implements AssistantTool
{
    public function name(): string
    {
        return 'get_report';
    }

    public function description(): string
    {
        return 'Get clinic reports and KPIs. metric options: summary, collections, new_patients, '
             . 'appointments, no_show_rate, treatment_acceptance, outstanding, pending_lab. '
             . 'period options: today, this_week, this_month, last_month. Use for "how much did we collect this month", '
             . '"no-show rate this week", "give me a summary".';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'metric' => [
                    'type' => 'string',
                    'enum' => ['summary', 'collections', 'new_patients', 'appointments', 'no_show_rate', 'treatment_acceptance', 'outstanding', 'pending_lab'],
                    'description' => 'Which KPI. Use "summary" for the key ones together.',
                ],
                'period' => [
                    'type' => 'string',
                    'enum' => ['today', 'this_week', 'this_month', 'last_month'],
                    'description' => 'Time range. Defaults to this_month.',
                ],
            ],
            'required' => [],
        ];
    }

    public function category(): string
    {
        return 'read';
    }

    public function run(array $args, User $user): array
    {
        $metric = in_array($args['metric'] ?? '', ['summary', 'collections', 'new_patients', 'appointments', 'no_show_rate', 'treatment_acceptance', 'outstanding', 'pending_lab'], true)
            ? $args['metric'] : 'summary';
        $period = in_array($args['period'] ?? '', ['today', 'this_week', 'this_month', 'last_month'], true)
            ? $args['period'] : 'this_month';

        [$from, $to, $label] = $this->range($period);
        $branch = $user->branch_id ?? null;

        $metrics = $metric === 'summary'
            ? ['collections', 'new_patients', 'appointments', 'no_show_rate', 'treatment_acceptance', 'outstanding']
            : [$metric];

        $lines = [];
        foreach ($metrics as $m) {
            $lines[] = $this->compute($m, $from, $to, $branch);
        }

        return [
            'summary' => "Report ({$metric}, {$label})",
            'content' => "Report — {$label}:\n" . implode("\n", array_map(fn ($l) => "- {$l}", $lines)),
        ];
    }

    protected function compute(string $m, Carbon $from, Carbon $to, ?int $branch): string
    {
        switch ($m) {
            case 'collections':
                $sum = 0.0;
                if (class_exists(\App\Models\Finance\FinanceTransaction::class)) {
                    $sum = (float) \App\Models\Finance\FinanceTransaction::where('type', 'income')
                        ->where('status', 'active')
                        ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
                        ->sum('amount');
                }
                return "Collections: " . $this->money($sum);

            case 'new_patients':
                $n = Patient::whereBetween('created_at', [$from, $to])
                    ->when($branch, fn ($q) => $q->where('branch_id', $branch))->count();
                return "New patients: {$n}";

            case 'appointments':
                $base = Appointment::whereBetween('appointment_date', [$from->toDateString(), $to->toDateString()])
                    ->when($branch, fn ($q) => $q->where('branch_id', $branch));
                $total = (clone $base)->count();
                $done  = (clone $base)->where('status', 'done')->count();
                $cancelled = (clone $base)->where('status', 'cancelled')->count();
                return "Appointments: {$total} booked, {$done} completed, {$cancelled} cancelled";

            case 'no_show_rate':
                $base = Appointment::whereBetween('appointment_date', [$from->toDateString(), $to->toDateString()])
                    ->when($branch, fn ($q) => $q->where('branch_id', $branch));
                $total   = (clone $base)->count();
                $noShow  = (clone $base)->where('status', 'no_show')->count();
                $rate    = $total ? round($noShow / $total * 100, 1) : 0;
                return "No-show rate: {$rate}% ({$noShow} of {$total})";

            case 'treatment_acceptance':
                $base = TreatmentPlan::whereBetween('created_at', [$from, $to]);
                $total    = (clone $base)->count();
                $accepted = (clone $base)->whereNotNull('accepted_at')->count();
                $rate     = $total ? round($accepted / $total * 100, 1) : 0;
                return "Treatment acceptance: {$rate}% ({$accepted} of {$total} plans accepted)";

            case 'outstanding':
                $sum = (float) Patient::when($branch, fn ($q) => $q->where('branch_id', $branch))
                    ->sum('outstanding_balance');
                return "Total outstanding (now): " . $this->money($sum);

            case 'pending_lab':
                $n = 0;
                if (class_exists(\App\Models\LabCase::class)) {
                    $n = \App\Models\LabCase::whereNotIn('status', ['complete', 'rejected'])->count();
                }
                return "Pending lab cases (now): {$n}";
        }
        return '';
    }

    /** @return array{0:Carbon,1:Carbon,2:string} */
    protected function range(string $period): array
    {
        return match ($period) {
            'today'      => [today()->startOfDay(), today()->endOfDay(), 'today'],
            'this_week'  => [now()->startOfWeek(), now()->endOfWeek(), 'this week'],
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth(), 'last month'],
            default      => [now()->startOfMonth(), now()->endOfMonth(), 'this month'],
        };
    }

    protected function money($value): string
    {
        return 'Rs. ' . number_format((float) $value, 0);
    }
}
