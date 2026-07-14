<?php

namespace App\Services\Analytics;

use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use Carbon\Carbon;

/**
 * ReportMetricsService — ONE definition for the money/appointment numbers
 * every report surface shows. Created 2026-07-14 because three surfaces
 * computed "collections" from three different tables (web reports:
 * InvoicePayment; huddle report: FinanceTransaction; mobile API: Receipt)
 * and two different "outstanding" filters — so web and mobile could show a
 * dentist different totals for the same period.
 *
 * Canonical definitions (source of truth = web main Reports page):
 *   collected    = InvoicePayment.amount summed over payment_date
 *   outstanding  = Invoice(status in draft,partial).balance_due
 *   appointments done = status 'done' ('completed' does not exist on
 *                   appointments; treatment_visits DOES use 'completed')
 *
 * All methods take an optional $branchId — web (single-clinic pages) passes
 * null; the mobile API passes the caller's branch.
 */
class ReportMetricsService
{
    /**
     * Resolve ?period=7|30|90|365|custom(&from&to) into [from, to] —
     * exactly the web ReportsController::index() range logic.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function resolveRange(?string $period, ?string $from = null, ?string $to = null): array
    {
        $period = $period ?: '30';

        if ($period === 'custom') {
            return [
                Carbon::parse($from ?: now()->subDays(30)->toDateString())->startOfDay(),
                Carbon::parse($to ?: now()->toDateString())->endOfDay(),
            ];
        }

        return [
            now()->subDays((int) $period)->startOfDay(),
            now()->endOfDay(),
        ];
    }

    /** Money collected in the range — canonical table: invoice_payments. */
    public function collected(Carbon $from, Carbon $to, ?int $branchId = null): float
    {
        return (float) $this->paymentsQuery($branchId)
            ->whereBetween('payment_date', [$from, $to])
            ->sum('amount');
    }

    /** Total receivables right now — canonical filter: draft + partial. */
    public function outstanding(?int $branchId = null): float
    {
        return (float) Invoice::whereIn('status', ['draft', 'partial'])
            ->when($branchId, fn ($q) => $q->whereHas(
                'patient', fn ($p) => $p->where('branch_id', $branchId)
            ))
            ->sum('balance_due');
    }

    /** Appointments completed in the range (status 'done'). */
    public function appointmentsDone(Carbon $from, Carbon $to, ?int $branchId = null): int
    {
        return Appointment::whereBetween('appointment_date', [$from, $to])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', 'done')
            ->count();
    }

    /**
     * Daily collections series (oldest → newest), one row per day in the
     * range including zero days. Same source table as collected().
     *
     * @return array<int, array{date: string, label: string, amount: float}>
     */
    public function collectionsSeries(Carbon $from, Carbon $to, ?int $branchId = null): array
    {
        $rows = $this->paymentsQuery($branchId)
            ->whereBetween('payment_date', [$from, $to])
            ->selectRaw('DATE(payment_date) as d, SUM(amount) as total')
            ->groupBy('d')
            ->pluck('total', 'd');

        $series = [];
        $cursor = $from->copy()->startOfDay();
        $end    = $to->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $series[] = [
                'date'   => $key,
                'label'  => $cursor->format('D'),
                'amount' => (float) ($rows[$key] ?? 0),
            ];
            $cursor->addDay();
        }

        return $series;
    }

    private function paymentsQuery(?int $branchId)
    {
        return InvoicePayment::query()
            ->when($branchId, fn ($q) => $q->whereHas(
                'invoice.patient', fn ($p) => $p->where('branch_id', $branchId)
            ));
    }
}
