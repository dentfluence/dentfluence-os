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
use Illuminate\Support\Facades\Schema;
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

        // The DIRECTION of the disagreement changed on 26-Aug, so this guard
        // was retargeted rather than deleted.
        //
        // It used to be an OVER-count: receiveAdvance() wrote a
        // finance_transactions income row for money that is a liability. U8/A1
        // fixed that at source — an advance is now a wallet credit plus an
        // 'advance' receipt, and no income row at all — so the original
        // assertion started failing for a good reason.
        //
        // What remains is an UNDER-count: ordinary invoice payments never
        // reach finance_transactions at all, so the ledger sees a fraction of
        // the day. Either way the point the fixture exists to make is intact:
        // finance_transactions is not, and must never become, a definition of
        // collections.
        $this->assertNotEquals(
            self::EXPECTED_COLLECTED,
            $ledgerSum,
            'finance_transactions must still disagree with the canonical figure, or this regression guard is meaningless.'
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
        $this->assertContains('Total collections: Rs. 10,000 (3 transactions)', $flow['lines']);
    }

    public function test_yesterdays_flow_reports_visit_activity_as_a_count_only(): void
    {
        $this->seedTheDay();

        $briefing = app(HuddleService::class)->build(null, self::HUDDLE_DAY);
        $flow     = collect($briefing['sections'])->firstWhere('title', "Yesterday's Flow");

        $this->assertNotNull($flow, "Yesterday's Flow section is missing from the briefing.");

        $visitLine = collect($flow['lines'])
            ->first(fn ($l) => str_starts_with($l, 'Visits completed:'));

        $this->assertNotNull($visitLine, 'The visits line must be present.');

        // G-33 (CEO directive, 26-Aug): visit-level money has no canonical
        // source, so the Huddle reports the count and nothing else. If someone
        // ever re-attaches a rupee figure to this line, they have invented a
        // second definition of collections and this test must stop them.
        $this->assertSame('Visits completed: 0', $visitLine);
        $this->assertStringNotContainsString('collected', $visitLine);
    }

    public function test_the_visit_model_does_not_advertise_columns_the_database_lacks(): void
    {
        // The root cause of G-33: TreatmentVisit listed four money columns in
        // $fillable that no migration has ever created, which is precisely why
        // reading them looked reasonable to every reviewer. Billing belongs to
        // the invoice — see TreatmentVisitService::rules().
        $fillable = (new \App\Models\TreatmentVisit)->getFillable();

        foreach (['cost', 'amount_paid', 'payment_mode', 'payment_reference'] as $phantom) {
            $this->assertNotContains(
                $phantom,
                $fillable,
                "TreatmentVisit must not declare '{$phantom}' as fillable - no migration creates it."
            );
            $this->assertFalse(
                Schema::hasColumn('treatment_visits', $phantom),
                "treatment_visits.{$phantom} now exists — if that is deliberate, G-33 needs revisiting."
            );
        }
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
