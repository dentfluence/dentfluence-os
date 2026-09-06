<?php

namespace Tests\Feature\Finance;

use App\Models\Finance\FinanceTransaction;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Patient;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Analytics\ReportMetricsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * W-5 / G-32 — the Huddle Report's money, and the FLOW vs STOCK rule.
 *
 * The CEO's model, in his words (6 Sep 2026): an invoice is raised when the
 * treatment starts; an advance goes into the WALLET against an advance
 * receipt; the invoice debits the wallet; whatever is billed and neither paid
 * nor covered by wallet stays OUTSTANDING. Wallet must always be spent before
 * new money is taken, and both stocks should sit at zero.
 *
 * Two rulings are pinned here because both were broken on production:
 *
 *  1. STOCKS DO NOT FOLLOW THE DATE FILTER. "Outstanding jopryant payment yet
 *     nahi topryant constant rahila pahije." Outstanding and wallet credit are
 *     positions, not periods. Billed and received are periods.
 *
 *  2. AN ADVANCE IS NOT REVENUE. The report used to sum finance_transactions,
 *     which on 6 Sep read Rs 4,11,412 against the canonical Rs 3,58,412 for the
 *     same 30 days. The entire Rs 53,000 gap was seven pre-G1 rows that booked
 *     wallet advance deposits as income. The InvoicePayment side matched to the
 *     rupee — the arithmetic was never wrong, the source table was.
 *
 * Also pinned: patientCreditHeld() reads balance_patient_credit ONLY. Its two
 * neighbours are live traps — balance_permanent is the pre-U8 name of the same
 * pot, and balance_total is written as promotional + permanent, so patient
 * credit is not in it.
 */
class MoneyStockAndFlowTest extends TestCase
{
    use RefreshDatabase;

    private const IN_RANGE  = '2026-08-15';
    private const PRE_RANGE = '2026-07-10';

    private function august(): array
    {
        return [
            Carbon::parse('2026-08-01')->startOfDay(),
            Carbon::parse('2026-08-31')->endOfDay(),
        ];
    }

    private function metrics(): ReportMetricsService
    {
        return app(ReportMetricsService::class);
    }

    private function makePatient(string $name): Patient
    {
        return Patient::create([
            'name'      => $name,
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ]);
    }

    /** An invoice for $amount on $date, with $paid received against it. */
    private function billAndCollect(Patient $p, string $date, float $amount, float $paid): Invoice
    {
        $staff = User::factory()->create();

        $invoice = Invoice::create([
            'invoice_number' => Invoice::nextNumber(),
            'patient_id'     => $p->id,
            'invoice_date'   => $date,
            'status'         => 'draft',
        ]);

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
        $invoice->recalculate();

        if ($paid > 0) {
            InvoicePayment::create([
                'invoice_id'   => $invoice->id,
                'patient_id'   => $p->id,
                'amount'       => $paid,
                'payment_mode' => 'cash',
                'payment_date' => $date,
                'created_by'   => $staff->id,
            ]);
            $invoice->refresh();
            $invoice->recalculate();
        }

        return $invoice->refresh();
    }

    /* ─────────────────────────────────────────────────────────────────────
       1. The wallet card must read ONE column, and it is not the obvious one
       ───────────────────────────────────────────────────────────────────── */

    public function test_patient_credit_held_reads_only_the_patient_credit_pot(): void
    {
        $p = $this->makePatient('Wallet Patient');

        // Deliberately hostile: every neighbouring column carries a DIFFERENT
        // number, so reading the wrong one cannot accidentally pass.
        Wallet::create([
            'patient_id'             => $p->id,
            'balance_promotional'    => 5000,
            'balance_permanent'      => 9999,   // pre-U8 name of the same pot — a trap
            'balance_patient_credit' => 20000,  // the only cash-backed liability
            'balance_total'          => 14999,  // promotional + permanent — credit is NOT in it
        ]);

        $this->assertSame(
            20000.0,
            $this->metrics()->patientCreditHeld(),
            'wallet card must read balance_patient_credit, never balance_total or balance_permanent'
        );
    }

    public function test_promotional_credit_is_never_counted_as_money_owed_back(): void
    {
        $p = $this->makePatient('Promo Only Patient');

        Wallet::create([
            'patient_id'             => $p->id,
            'balance_promotional'    => 75000,  // a marketing commitment, may expire
            'balance_permanent'      => 0,
            'balance_patient_credit' => 0,
            'balance_total'          => 75000,
        ]);

        $this->assertSame(
            0.0,
            $this->metrics()->patientCreditHeld(),
            'promotional credit was never money the clinic received — it is not a liability'
        );
    }

    /* ─────────────────────────────────────────────────────────────────────
       2. Flow moves with the date filter. Stock does not.
       ───────────────────────────────────────────────────────────────────── */

    public function test_flows_follow_the_date_filter_but_stocks_do_not(): void
    {
        $p = $this->makePatient('Range Patient');

        // July: billed 40,000, received 10,000  → 30,000 still owed
        $this->billAndCollect($p, self::PRE_RANGE, 40000, 10000);
        // August: billed 60,000, received 20,000 → 40,000 still owed
        $this->billAndCollect($p, self::IN_RANGE, 60000, 20000);

        $m = $this->metrics();
        [$from, $to] = $this->august();

        // FLOW — August only sees August.
        $this->assertSame(60000.0, $m->billed($from, $to),    'billed is a flow');
        $this->assertSame(20000.0, $m->collected($from, $to), 'received is a flow');

        // STOCK — the whole position, both months, regardless of the filter.
        $this->assertSame(
            70000.0,
            $m->outstanding(),
            'outstanding is a stock: it stays put until money arrives, whatever range is on screen'
        );
    }

    public function test_outstanding_takes_no_date_range_at_all(): void
    {
        // Structural, not numeric: the moment someone adds a range parameter to
        // outstanding(), the stock rule is gone and this fails loudly.
        $method = new \ReflectionMethod(ReportMetricsService::class, 'outstanding');
        $names  = array_map(fn ($p) => $p->getName(), $method->getParameters());

        $this->assertSame(['branchId'], $names,
            'outstanding() must take only a branch scope — never a date range');

        $method = new \ReflectionMethod(ReportMetricsService::class, 'patientCreditHeld');
        $names  = array_map(fn ($p) => $p->getName(), $method->getParameters());

        $this->assertSame(['branchId'], $names,
            'patientCreditHeld() must take only a branch scope — never a date range');
    }

    /* ─────────────────────────────────────────────────────────────────────
       3. The G-32 bug itself: an advance can never reach "received"
       ───────────────────────────────────────────────────────────────────── */

    public function test_a_wallet_advance_booked_as_income_cannot_reach_received(): void
    {
        $p = $this->makePatient('Advance Patient');

        $this->billAndCollect($p, self::IN_RANGE, 60000, 20000);

        // Exactly the shape of the seven legacy production rows: an advance
        // deposit written into finance_transactions as type=income.
        FinanceTransaction::create([
            'type'             => 'income',
            'direction'        => 'credit',
            'source_type'      => \App\Models\WalletTransaction::class,
            'source_id'        => 1,
            'amount'           => 53000,
            'net_amount'       => 53000,
            'payment_mode'     => 'cash',
            'status'           => 'active',
            'patient_id'       => $p->id,
            'transaction_date' => self::IN_RANGE,
            'notes'            => 'Advance deposit to wallet',
        ]);

        [$from, $to] = $this->august();

        $this->assertSame(
            20000.0,
            $this->metrics()->collected($from, $to),
            'an advance is the patient\'s money — it must never be counted as collected revenue'
        );
    }

    /* ─────────────────────────────────────────────────────────────────────
       4. The breakdown can never argue with the total above it
       ───────────────────────────────────────────────────────────────────── */

    public function test_payment_mode_breakdown_always_sums_to_received(): void
    {
        $p     = $this->makePatient('Mode Patient');
        $staff = User::factory()->create();

        $invoice = $this->billAndCollect($p, self::IN_RANGE, 60000, 20000); // cash 20,000

        InvoicePayment::create([
            'invoice_id'   => $invoice->id,
            'patient_id'   => $p->id,
            'amount'       => 15000,
            'payment_mode' => 'upi',
            'payment_date' => self::IN_RANGE,
            'created_by'   => $staff->id,
        ]);

        [$from, $to] = $this->august();
        $m = $this->metrics();

        $total = $m->collected($from, $to);
        $modes = $m->collectionsByMode($from, $to);

        $this->assertSame(35000.0, $total, 'cash 20,000 + upi 15,000');
        $this->assertSame(2, $modes->count(), 'two payment modes used');
        $this->assertEqualsWithDelta(
            $total,
            (float) $modes->sum('total'),
            0.001,
            'the breakdown is the same query as the total — it can never disagree'
        );
    }
}
