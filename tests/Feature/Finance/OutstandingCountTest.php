<?php

namespace Tests\Feature\Finance;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Patient;
use App\Models\User;
use App\Services\Analytics\ReportMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A-1 — the outstanding COUNT must describe the same invoices as the
 * outstanding RUPEES standing next to it.
 *
 * The Finance dashboard counted `status in ('draft','partial')` with no
 * balance filter. Invoice::deriveStatus() returns 'draft' whenever paid <= 0,
 * and a fully wallet-settled invoice has total 0 and paid 0 — so it sat in
 * that count as an open bill with nothing owed on it.
 *
 * MEASURED ON PRODUCTION 2026-09-12: 13 counted, 3 of them zero-balance.
 * The rupee figure (a SUM, to which a zero row contributes zero) was always
 * right. Only the count lied — and it lied in the direction that makes the
 * clinic look like it is chasing more open bills than it is.
 *
 * This test exists because the fix is one WHERE clause, which is exactly the
 * kind of thing a later refactor drops without noticing.
 */
class OutstandingCountTest extends TestCase
{
    use RefreshDatabase;

    private const PARTIAL_BALANCE = 3000.0;   // 5000 billed, 2000 paid
    private const DRAFT_BALANCE   = 4000.0;   // 4000 billed, nothing paid

    private function patient(string $name): Patient
    {
        return Patient::create([
            'name'      => $name,
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ]);
    }

    private function invoiceFor(Patient $patient, float $amount, array $extra = []): Invoice
    {
        $invoice = Invoice::create(array_merge([
            'invoice_number' => Invoice::nextNumber(),
            'patient_id'     => $patient->id,
            'invoice_date'   => '2026-09-10',
            'status'         => 'draft',
        ], $extra));

        InvoiceItem::create([
            'invoice_id'  => $invoice->id,
            'description' => 'Treatment',
            'unit_price'  => $amount,
            'qty'         => 1,
            'net_amount'  => $amount,
            'gst_pct'     => 0,
            'gst_amount'  => 0,
            'total'       => $amount,
        ]);

        return $invoice;
    }

    /**
     * Four invoices, only TWO of which anyone is actually owed money on.
     * Seeded hostile on purpose: the two that must NOT count both carry
     * status 'draft', so a query that reads status alone cannot pass.
     */
    private function seedFourInvoices(): void
    {
        $staff = User::factory()->create();

        // 1. PARTIAL — 5000 billed, 2000 paid. Counts.
        $partial = $this->invoiceFor($this->patient('A1 Partial'), 5000.0);
        InvoicePayment::create([
            'invoice_id'   => $partial->id,
            'patient_id'   => $partial->patient_id,
            'amount'       => 2000.0,
            'payment_mode' => 'cash',
            'payment_date' => '2026-09-10',
            'created_by'   => $staff->id,
        ]);
        $partial->recalculate();

        // 2. DRAFT, nothing paid — 4000 owed. Counts.
        $this->invoiceFor($this->patient('A1 Unpaid'), 4000.0)->recalculate();

        // 3. WALLET-SETTLED — 3000 billed, wholly covered by patient credit.
        //    total 0, paid 0 => deriveStatus() calls it 'draft'. Owes nothing.
        $wallet = $this->invoiceFor($this->patient('A1 WalletSettled'), 3000.0);
        $wallet->update(['wallet_applied' => 3000.0]);
        $wallet->recalculate();

        // 4. CANCELLED — never a receivable under any reading.
        $cancelled = $this->invoiceFor($this->patient('A1 Cancelled'), 9000.0);
        $cancelled->recalculate();
        $cancelled->update(['status' => 'cancelled']);
    }

    public function test_a_zero_balance_invoice_is_not_counted_as_outstanding(): void
    {
        $this->seedFourInvoices();

        $metrics = app(ReportMetricsService::class);

        $this->assertSame(2, $metrics->outstandingCount(),
            'only the partial and the unpaid invoice are owed money');
        $this->assertSame(
            self::PARTIAL_BALANCE + self::DRAFT_BALANCE,
            $metrics->outstanding(),
            'the rupee figure is unchanged by the fix'
        );
    }

    /** The wallet-settled invoice really does sit in the old status filter. */
    public function test_the_wallet_settled_invoice_is_still_status_draft(): void
    {
        $this->seedFourInvoices();

        $draftsWithNothingOwed = Invoice::whereIn('status', ['draft', 'partial'])
            ->where('balance_due', '<=', 0)
            ->count();

        $this->assertSame(1, $draftsWithNothingOwed,
            'if this is 0 the fixture no longer reproduces the production bug');
    }

    /** Count and sum must never be able to describe different sets again. */
    public function test_count_and_sum_describe_the_same_invoices(): void
    {
        $this->seedFourInvoices();

        $metrics = app(ReportMetricsService::class);

        $rows = Invoice::whereIn('status', ['draft', 'partial'])
            ->where('balance_due', '>', 0)
            ->pluck('balance_due');

        $this->assertSame($rows->count(), $metrics->outstandingCount());
        $this->assertSame((float) $rows->sum(), $metrics->outstanding());
    }

    /** A cancelled invoice is not a receivable, however much it was billed for. */
    public function test_a_cancelled_invoice_is_never_outstanding(): void
    {
        $this->seedFourInvoices();

        $this->assertSame(2, app(ReportMetricsService::class)->outstandingCount());
        $this->assertFalse(
            Invoice::where('status', 'cancelled')->where('balance_due', '>', 0)->exists()
            && app(ReportMetricsService::class)->outstandingCount() > 2
        );
    }
}
