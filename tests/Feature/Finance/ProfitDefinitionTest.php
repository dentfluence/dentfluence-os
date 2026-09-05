<?php

namespace Tests\Feature\Finance;

use App\Models\Finance\FinanceExpense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Patient;
use App\Models\User;
use App\Services\Analytics\ReportMetricsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * W-4 / G-06 — profit must have exactly ONE definition, in one place.
 *
 * Four surfaces used to compute profit: the Finance dashboard KPI, the
 * dashboard's 6-month chart, Analytics > Business Intelligence, and the CA
 * export. Revenue was identical on all four; the entire divergence was a
 * single WHERE clause — only Analytics filtered expenses to payment_status
 * 'paid'. So three screens paired CASH revenue with ACCRUAL expenses, the
 * most pessimistic pairing available, and the owner saw two different profits
 * for the same period depending on which page he opened.
 *
 * The seeded month is the CEO's own worked example from 5 Sep 2026:
 *   billed 10,00,000 · received 7,00,000 · receivable 3,00,000
 *   expenses paid 2,00,000 · expenses still unpaid 1,00,000
 *
 * which must produce exactly two figures and no others:
 *   in hand = 7,00,000 - 2,00,000 = 5,00,000   (received minus paid)
 *   earned  = 10,00,000 - 3,00,000 = 7,00,000  (billed minus all bills)
 *
 * This test exists because "collections" already drifted back to two
 * definitions once after ReportMetricsService was created to end exactly
 * that. A green suite does not stop a definition drifting; a pinned number
 * does.
 */
class ProfitDefinitionTest extends TestCase
{
    use RefreshDatabase;

    private const MONTH_DAY = '2026-08-15';

    private const BILLED          = 1000000.0;
    private const RECEIVED        =  700000.0;
    private const EXPENSE_PAID    =  200000.0;
    private const EXPENSE_UNPAID  =  100000.0;

    private const EXPECT_IN_HAND  =  500000.0;  // 7L - 2L
    private const EXPECT_EARNED   =  700000.0;  // 10L - (2L + 1L)

    private function range(): array
    {
        return [
            Carbon::parse('2026-08-01')->startOfDay(),
            Carbon::parse('2026-08-31')->endOfDay(),
        ];
    }

    /** Named seedTheMonth, not seed: TestCase::seed() is public and final in intent —
     *  redeclaring it private is an illegal visibility reduction and fatals at class load.
     *  php -l cannot catch that; only running the suite can. */
    private function seedTheMonth(): void
    {
        $staff   = User::factory()->create();
        $patient = Patient::create([
            'name'      => 'G06 Patient',
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => Invoice::nextNumber(),
            'patient_id'     => $patient->id,
            'invoice_date'   => self::MONTH_DAY,
            'status'         => 'draft',
        ]);

        InvoiceItem::create([
            'invoice_id'  => $invoice->id,
            'description' => 'Full mouth rehabilitation',
            'unit_price'  => self::BILLED,
            'qty'         => 1,
            'net_amount'  => self::BILLED,
            'gst_pct'     => 0,
            'gst_amount'  => 0,
            'total'       => self::BILLED,
        ]);
        $invoice->recalculate();

        InvoicePayment::create([
            'invoice_id'   => $invoice->id,
            'patient_id'   => $patient->id,
            'amount'       => self::RECEIVED,
            'payment_mode' => 'cash',
            'payment_date' => self::MONTH_DAY,
            'created_by'   => $staff->id,
        ]);

        FinanceExpense::create([
            'title'          => 'Lab bill — settled',
            'expense_date'   => self::MONTH_DAY,
            'amount'         => self::EXPENSE_PAID,
            'total_amount'   => self::EXPENSE_PAID,
            'payment_mode'   => 'cash',
            'payment_status' => 'paid',
        ]);

        FinanceExpense::create([
            'title'          => 'Material bill — not yet paid',
            'expense_date'   => self::MONTH_DAY,
            'amount'         => self::EXPENSE_UNPAID,
            'total_amount'   => self::EXPENSE_UNPAID,
            'payment_mode'   => 'cash',
            'payment_status' => 'unpaid',
        ]);
    }

    public function test_profit_returns_both_bases_and_they_do_not_bleed_into_each_other(): void
    {
        $this->seedTheMonth();
        [$from, $to] = $this->range();

        $m = app(ReportMetricsService::class)->profit($from, $to);

        $this->assertSame(self::BILLED,         $m['billed'],          'billed');
        $this->assertSame(self::RECEIVED,       $m['collected'],       'collected');
        $this->assertSame(self::EXPENSE_PAID,   $m['expenses_paid'],   'expenses paid');
        $this->assertSame(
            self::EXPENSE_PAID + self::EXPENSE_UNPAID,
            $m['expenses_booked'],
            'expenses booked must include the unpaid bill'
        );
        $this->assertSame(self::EXPENSE_UNPAID, $m['expenses_unpaid'], 'expenses unpaid');

        $this->assertSame(self::EXPECT_IN_HAND, $m['cash_profit'],   'in hand = received - paid');
        $this->assertSame(self::EXPECT_EARNED,  $m['earned_profit'], 'earned = billed - all bills');
    }

    /** The pairing that used to be on three screens must never come back. */
    public function test_cash_profit_never_subtracts_unpaid_bills_from_received_money(): void
    {
        $this->seedTheMonth();
        [$from, $to] = $this->range();

        $m = app(ReportMetricsService::class)->profit($from, $to);

        // The old hybrid: received (7L) minus BOOKED expenses (3L) = 4L.
        // It answers no question anyone asks, and it is what the dashboard
        // and the CA export both showed before W-4.
        $this->assertNotSame(400000.0, $m['cash_profit'], 'the cash/accrual hybrid is back');
    }

    /** A cancelled invoice is not revenue on either basis. */
    public function test_cancelled_invoices_are_excluded_from_billed(): void
    {
        $this->seedTheMonth();

        $patient = Patient::first();
        $void = Invoice::create([
            'invoice_number' => Invoice::nextNumber(),
            'patient_id'     => $patient->id,
            'invoice_date'   => self::MONTH_DAY,
            'status'         => 'draft',
        ]);
        InvoiceItem::create([
            'invoice_id'  => $void->id,
            'description' => 'Cancelled plan',
            'unit_price'  => 500000,
            'qty'         => 1,
            'net_amount'  => 500000,
            'gst_pct'     => 0,
            'gst_amount'  => 0,
            'total'       => 500000,
        ]);
        $void->recalculate();
        $void->update(['status' => 'cancelled']);

        [$from, $to] = $this->range();
        $m = app(ReportMetricsService::class)->profit($from, $to);

        $this->assertSame(self::BILLED, $m['billed'], 'a cancelled invoice must not count as billed');
    }

    /** Margins are computed from their own base, never from each other. */
    public function test_each_margin_uses_its_own_denominator(): void
    {
        $this->seedTheMonth();
        [$from, $to] = $this->range();

        $m = app(ReportMetricsService::class)->profit($from, $to);

        $this->assertSame(71.4, $m['cash_margin'],   '5L on 7L received');
        $this->assertSame(70.0, $m['earned_margin'], '7L on 10L billed');
    }
}
