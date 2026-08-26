<?php

namespace Tests\Feature\Finance;

use App\Models\Finance\FinanceTransaction;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Patient;
use App\Models\User;
use App\Services\Analytics\ReportMetricsService;
use App\Services\Huddle\HuddleBoardApiService;
use App\Services\Huddle\HuddleService;
use App\Services\WalletService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * G-03 — the Huddle must not carry its own definition of "collected".
 *
 * Both Huddle surfaces used to sum `finance_transactions`, which also carries
 * advances and wallet top-ups. Every other surface sums `invoice_payments`
 * through ReportMetricsService. On any day with an advance the morning Huddle
 * and the Reports page therefore showed the owner two different numbers for
 * the same day — the exact defect ReportMetricsService was created to end.
 *
 * The seeded day deliberately contains the two receipts that used to break
 * parity: a cash ADVANCE (cash in, but a liability — not a collection) and a
 * WALLET SETTLEMENT (a real tender against an invoice — a collection).
 */
class HuddleCollectionsParityTest extends TestCase
{
    use RefreshDatabase;

    /** Wednesday, so ClinicFlowRange resolves "yesterday" to a single day. */
    private const HUDDLE_DAY = '2026-08-19';
    private const MONEY_DAY  = '2026-08-18';

    private const CASH_PAYMENT   = 5000.0;
    private const UPI_PAYMENT    = 3000.0;
    private const WALLET_SETTLED = 2000.0;
    private const ADVANCE        = 10000.0;

    /** What the canonical definition must produce: tenders against invoices only. */
    private const EXPECTED_COLLECTED = 10000.0; // 5000 + 3000 + 2000

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse(self::HUDDLE_DAY . ' 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function patient(): Patient
    {
        return Patient::create([
            'name'      => 'G03 Patient',
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ]);
    }

    private function invoiceFor(Patient $patient, float $amount): Invoice
    {
        $invoice = Invoice::create([
            'invoice_number' => Invoice::nextNumber(),
            'patient_id'     => $patient->id,
            'invoice_date'   => self::MONEY_DAY,
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

        return $invoice->fresh();
    }

    /** One clinic day: two ordinary receipts, one advance, one wallet settlement. */
    private function seedTheDay(): Patient
    {
        $staff   = User::factory()->create();
        $patient = $this->patient();

        $invoice = $this->invoiceFor($patient, 12000);

        InvoicePayment::create([
            'invoice_id'   => $invoice->id,
            'patient_id'   => $patient->id,
            'amount'       => self::CASH_PAYMENT,
            'payment_mode' => 'cash',
            'payment_date' => self::MONEY_DAY,
            'created_by'   => $staff->id,
        ]);

        InvoicePayment::create([
            'invoice_id'   => $invoice->id,
            'patient_id'   => $patient->id,
            'amount'       => self::UPI_PAYMENT,
            'payment_mode' => 'upi',
            'payment_date' => self::MONEY_DAY,
            'created_by'   => $staff->id,
        ]);

        // An advance: cash in the till, but a liability — never a collection.
        app(WalletService::class)->receiveAdvance(
            patient:     $patient,
            amount:      self::ADVANCE,
            paymentMode: 'cash',
            paymentDate: self::MONEY_DAY,
            notes:       null,
            createdBy:   $staff->id,
        );

        // A wallet settlement: a real tender against an invoice — a collection.
        $settled = $this->invoiceFor($patient, self::WALLET_SETTLED);
        app(WalletService::class)->settleInvoiceFromCredit(
            invoice:     $settled,
            amount:      self::WALLET_SETTLED,
            createdBy:   $staff->id,
            paymentDate: self::MONEY_DAY,
        );

        return $patient;
    }

    private function moneyDayRange(): array
    {
        return [
            Carbon::parse(self::MONEY_DAY)->startOfDay(),
            Carbon::parse(self::MONEY_DAY)->endOfDay(),
        ];
    }

    public function test_the_seeded_day_is_one_where_the_two_definitions_actually_disagree(): void
    {
        $this->seedTheDay();
        [$from, $to] = $this->moneyDayRange();

        $ledgerSum = (float) FinanceTransaction::where('type', 'income')
            ->where('status', 'active')
            ->whereBetween('transaction_date', [$from, $to])
            ->sum('amount');

        // If this ever becomes equal, the fixture stopped exercising the bug.
        $this->assertGreaterThan(
            self::EXPECTED_COLLECTED,
            $ledgerSum,
            'finance_transactions must still over-count this day, or the regression guard is meaningless.'
        );
    }

    public function test_huddle_briefing_collections_equal_the_canonical_figure(): void
    {
        $this->seedTheDay();
        [$from, $to] = $this->moneyDayRange();

        $canonical = app(ReportMetricsService::class)->collected($from, $to);
        $this->assertSame(self::EXPECTED_COLLECTED, $canonical);

        $briefing = app(HuddleService::class)->build(null, self::HUDDLE_DAY);
        $flow     = collect($briefing['sections'])
            ->firstWhere('title', "Yesterday's Flow");

        $this->assertNotNull($flow, "Yesterday's Flow section is missing from the briefing.");
        $this->assertStringContainsString('Rs. 10,000', $flow['headline']);
        $this->assertContains('Total collections: Rs. 10,000', $flow['lines']);
    }

    public function test_mobile_huddle_board_collected_today_equals_the_canonical_figure(): void
    {
        $this->seedTheDay();
        [$from, $to] = $this->moneyDayRange();

        $canonical = app(ReportMetricsService::class)->collected($from, $to, 1);
        $board     = app(HuddleBoardApiService::class)->build(1, self::MONEY_DAY);

        $this->assertSame($canonical, (float) $board['kpis']['collected_today']);
        $this->assertSame(self::EXPECTED_COLLECTED, (float) $board['kpis']['collected_today']);
    }

    public function test_no_finance_transaction_reads_remain_in_the_huddle_services(): void
    {
        foreach (glob(app_path('Services/Huddle/*.php')) as $file) {
            $this->assertStringNotContainsString(
                'FinanceTransaction',
                file_get_contents($file),
                basename($file) . ' must read money through ReportMetricsService, not the finance ledger.'
            );
        }
    }
}
