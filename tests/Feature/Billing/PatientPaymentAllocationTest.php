<?php

namespace Tests\Feature\Billing;

use App\Models\Finance\FinanceTransaction;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Patient;
use App\Models\Receipt;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Billing\PatientPaymentAllocationService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * PATIENT-LEVEL PAYMENT ALLOCATION
 *
 * One tender, allocated oldest-first across the patient's open invoices, with
 * the surplus becoming Patient Credit through the existing U8 advance path.
 *
 * The invariants that must never break:
 *
 *   Revenue is ONLY what actually settled an invoice.
 *   The surplus is a liability, never income, and never counted twice.
 *   The patient performed ONE payment, so the patient gets ONE receipt.
 *   Promotional credit and existing Patient Credit are untouched by a cash tender.
 *   All or nothing: a failure anywhere leaves the ledger exactly as it was.
 */
class PatientPaymentAllocationTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    private function staff(): User
    {
        return User::factory()->create();
    }

    private function patient(string $name = 'Allocation Patient'): Patient
    {
        return Patient::create([
            'name'      => $name,
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ]);
    }

    private function allocator(): PatientPaymentAllocationService
    {
        return app(PatientPaymentAllocationService::class);
    }

    /** A plain invoice for $amount, dated $date, with no discounts of any kind. */
    private function invoiceFor(Patient $patient, float $amount, ?string $date = null): Invoice
    {
        $invoice = Invoice::create([
            'invoice_number' => Invoice::nextNumber(),
            'patient_id'     => $patient->id,
            'invoice_date'   => $date ?? today()->toDateString(),
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

    /** Record a part-payment directly so an invoice starts life partially paid. */
    private function partPay(Invoice $invoice, float $amount): void
    {
        InvoicePayment::create([
            'invoice_id'   => $invoice->id,
            'patient_id'   => $invoice->patient_id,
            'amount'       => $amount,
            'payment_mode' => 'cash',
            'payment_date' => today()->toDateString(),
        ]);
        $invoice->recalculate();
    }

    private function pay(Patient $patient, float $amount, string $mode = 'cash'): array
    {
        return $this->allocator()->allocate($patient, [
            'amount'       => $amount,
            'payment_mode' => $mode,
            'payment_date' => today()->toDateString(),
        ], $this->staff()->id);
    }

    private function income(Patient $patient): float
    {
        return (float) FinanceTransaction::where('patient_id', $patient->id)
            ->where('type', 'income')->sum('amount');
    }

    private function advance(Patient $patient): float
    {
        return (float) FinanceTransaction::where('patient_id', $patient->id)
            ->where('type', 'advance')->sum('amount');
    }

    private function patientCredit(Patient $patient): float
    {
        return (float) Wallet::forPatient($patient->id)->fresh()->balance_patient_credit;
    }

    // ── A. One invoice, exact payment ────────────────────────────────────────

    public function test_A_single_invoice_exact_payment_settles_it_with_no_credit(): void
    {
        $patient = $this->patient();
        $invoice = $this->invoiceFor($patient, 1000);

        $result = $this->pay($patient, 1000);

        $this->assertEqualsWithDelta(1000, $result['settled'], 0.01);
        $this->assertEqualsWithDelta(0, $result['surplus'], 0.01);
        $this->assertEqualsWithDelta(0, $invoice->fresh()->balance_due, 0.01);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertEqualsWithDelta(0, $this->patientCredit($patient), 0.01,
            'An exact payment must not create patient credit.');
        $this->assertEqualsWithDelta(1000, $this->income($patient), 0.01);
        $this->assertEqualsWithDelta(0, $this->advance($patient), 0.01);
    }

    // ── B. One invoice, underpayment ─────────────────────────────────────────

    public function test_B_single_invoice_underpayment_leaves_a_balance(): void
    {
        $patient = $this->patient();
        $invoice = $this->invoiceFor($patient, 1000);

        $result = $this->pay($patient, 400);

        $this->assertEqualsWithDelta(400, $result['settled'], 0.01);
        $this->assertEqualsWithDelta(0, $result['surplus'], 0.01);
        $this->assertEqualsWithDelta(600, $invoice->fresh()->balance_due, 0.01);
        $this->assertSame('partial', $invoice->fresh()->status);
        $this->assertEqualsWithDelta(0, $this->patientCredit($patient), 0.01);
        $this->assertEqualsWithDelta(400, $this->income($patient), 0.01);
    }

    // ── C. One invoice, overpayment → surplus becomes Patient Credit ─────────

    public function test_C_single_invoice_overpayment_surplus_becomes_patient_credit(): void
    {
        $patient = $this->patient();
        $invoice = $this->invoiceFor($patient, 1000);

        $result = $this->pay($patient, 1200);

        $this->assertEqualsWithDelta(1000, $result['settled'], 0.01);
        $this->assertEqualsWithDelta(200, $result['surplus'], 0.01);
        $this->assertEqualsWithDelta(0, $invoice->fresh()->balance_due, 0.01);

        // Only the settlement is revenue. The surplus is a liability.
        $this->assertEqualsWithDelta(1000, $this->income($patient), 0.01,
            'Only the settled amount may be recognised as income.');
        $this->assertEqualsWithDelta(200, $this->advance($patient), 0.01,
            'The surplus must be booked as an advance, not income.');
        $this->assertEqualsWithDelta(200, $this->patientCredit($patient), 0.01);
    }

    // ── D. Multiple invoices — oldest first ──────────────────────────────────

    public function test_D_payment_settles_the_oldest_invoice_first(): void
    {
        $patient = $this->patient();
        $old     = $this->invoiceFor($patient, 1000, today()->subDays(30)->toDateString());
        $new     = $this->invoiceFor($patient, 1500, today()->toDateString());

        // Enough for the old invoice and a bite of the new one.
        $result = $this->pay($patient, 1200);

        $this->assertEqualsWithDelta(0, $old->fresh()->balance_due, 0.01,
            'The oldest invoice must be settled first.');
        $this->assertEqualsWithDelta(1300, $new->fresh()->balance_due, 0.01);

        $this->assertSame(
            [$old->invoice_number, $new->invoice_number],
            array_column($result['allocations'], 'invoice_number'),
            'Allocation order must be oldest invoice_date first.'
        );
    }

    // ── E. The headline scenario ─────────────────────────────────────────────

    public function test_E_old_balance_plus_current_invoice_plus_surplus_credit(): void
    {
        $patient = $this->patient();

        $a = $this->invoiceFor($patient, 1000, today()->subDays(20)->toDateString());
        $this->partPay($a, 600);                 // 400 outstanding
        $b = $this->invoiceFor($patient, 1500);  // 1,500 outstanding

        $result = $this->pay($patient, 2500);

        $this->assertEqualsWithDelta(0, $a->fresh()->balance_due, 0.01);
        $this->assertEqualsWithDelta(0, $b->fresh()->balance_due, 0.01);
        $this->assertEqualsWithDelta(0, $result['outstanding_after'], 0.01);
        $this->assertEqualsWithDelta(600, $result['surplus'], 0.01);
        $this->assertEqualsWithDelta(600, $this->patientCredit($patient), 0.01);

        $this->assertSame(
            [400.0, 1500.0],
            array_map('floatval', array_column($result['allocations'], 'amount')),
            '400 to the old invoice, 1,500 to the current one.'
        );

        // Revenue for THIS tender is 1,900 (the 600 part-payment is separate and
        // was never mirrored to finance by the test helper).
        $this->assertEqualsWithDelta(1900, $this->income($patient), 0.01);
        $this->assertEqualsWithDelta(600, $this->advance($patient), 0.01);
    }

    // ── F. Same date → deterministic id ordering ─────────────────────────────

    public function test_F_invoices_on_the_same_date_are_allocated_in_id_order(): void
    {
        $patient = $this->patient();
        $date    = today()->subDays(5)->toDateString();

        $first  = $this->invoiceFor($patient, 500, $date);
        $second = $this->invoiceFor($patient, 500, $date);

        $result = $this->pay($patient, 500);

        $this->assertEqualsWithDelta(0, $first->fresh()->balance_due, 0.01,
            'With equal dates the lower id must be paid first.');
        $this->assertEqualsWithDelta(500, $second->fresh()->balance_due, 0.01);
        $this->assertCount(1, $result['allocations']);
    }

    // ── G. Payment smaller than total outstanding ────────────────────────────

    public function test_G_payment_smaller_than_total_outstanding_leaves_a_balance(): void
    {
        $patient = $this->patient();
        $a = $this->invoiceFor($patient, 1000, today()->subDays(10)->toDateString());
        $b = $this->invoiceFor($patient, 1000);

        $result = $this->pay($patient, 1500);

        $this->assertEqualsWithDelta(1500, $result['settled'], 0.01);
        $this->assertEqualsWithDelta(0, $result['surplus'], 0.01);
        $this->assertEqualsWithDelta(500, $result['outstanding_after'], 0.01);
        $this->assertEqualsWithDelta(0, $a->fresh()->balance_due, 0.01);
        $this->assertEqualsWithDelta(500, $b->fresh()->balance_due, 0.01);
        $this->assertEqualsWithDelta(0, $this->patientCredit($patient), 0.01);
    }

    // ── H. Payment exactly equal to total outstanding ────────────────────────

    public function test_H_payment_equal_to_total_outstanding_clears_everything(): void
    {
        $patient = $this->patient();
        $a = $this->invoiceFor($patient, 1000, today()->subDays(10)->toDateString());
        $b = $this->invoiceFor($patient, 1500);

        $result = $this->pay($patient, 2500);

        $this->assertEqualsWithDelta(2500, $result['settled'], 0.01);
        $this->assertEqualsWithDelta(0, $result['surplus'], 0.01);
        $this->assertEqualsWithDelta(0, $result['outstanding_after'], 0.01);
        $this->assertSame('paid', $a->fresh()->status);
        $this->assertSame('paid', $b->fresh()->status);
        $this->assertEqualsWithDelta(0, $this->patientCredit($patient), 0.01,
            'Paying exactly the outstanding total must not create credit.');
        $this->assertEqualsWithDelta(2500, $this->income($patient), 0.01);
    }

    // ── I. Payment greater than total outstanding ────────────────────────────

    public function test_I_payment_greater_than_total_outstanding_creates_credit(): void
    {
        $patient = $this->patient();
        $a = $this->invoiceFor($patient, 1000, today()->subDays(10)->toDateString());
        $b = $this->invoiceFor($patient, 1500);

        $result = $this->pay($patient, 3000);

        $this->assertEqualsWithDelta(2500, $result['settled'], 0.01);
        $this->assertEqualsWithDelta(500, $result['surplus'], 0.01);
        $this->assertEqualsWithDelta(500, $this->patientCredit($patient), 0.01);
        $this->assertEqualsWithDelta(2500, $this->income($patient), 0.01);
        $this->assertEqualsWithDelta(500, $this->advance($patient), 0.01);
        $this->assertSame('paid', $a->fresh()->status);
        $this->assertSame('paid', $b->fresh()->status);
    }

    // ── J. Existing Patient Credit stays separate ────────────────────────────

    public function test_J_existing_patient_credit_is_not_consumed_or_double_counted(): void
    {
        $patient = $this->patient();

        // Patient already has 1,000 of credit from an earlier advance.
        app(WalletService::class)->receiveAdvance(
            patient:     $patient,
            amount:      1000,
            paymentMode: 'cash',
            paymentDate: today()->toDateString(),
            createdBy:   $this->staff()->id,
        );

        $invoice = $this->invoiceFor($patient, 800);

        $result = $this->pay($patient, 800);

        // The cash tender settled the invoice. The stored credit is untouched:
        // allocation never silently spends money the patient already handed over.
        $this->assertEqualsWithDelta(0, $invoice->fresh()->balance_due, 0.01);
        $this->assertEqualsWithDelta(1000, $this->patientCredit($patient), 0.01,
            'A cash tender must not consume pre-existing patient credit.');
        $this->assertEqualsWithDelta(0, $result['surplus'], 0.01);

        // 1,000 advance + 800 income. The advance is never re-counted as revenue.
        $this->assertEqualsWithDelta(800, $this->income($patient), 0.01);
        $this->assertEqualsWithDelta(1000, $this->advance($patient), 0.01);
    }

    // ── K. Promotional credit untouched ──────────────────────────────────────

    public function test_K_promotional_credit_is_untouched_by_allocation(): void
    {
        $patient = $this->patient();

        app(WalletService::class)->credit(
            patientId:  $patient->id,
            amount:     500,
            creditType: 'promotional',
            expiryDate: today()->addMonth()->toDateString(),
            notes:      'Campaign',
            createdBy:  $this->staff()->id,
        );

        $before = (float) Wallet::forPatient($patient->id)->fresh()->balance_promotional;
        $this->assertEqualsWithDelta(500, $before, 0.01);

        $this->invoiceFor($patient, 1000);
        $this->pay($patient, 1200);

        $this->assertEqualsWithDelta(500,
            (float) Wallet::forPatient($patient->id)->fresh()->balance_promotional, 0.01,
            'Promotional credit is a concession and must never be touched by a cash tender.');

        // The surplus landed in patient credit, not in the promotional bucket.
        $this->assertEqualsWithDelta(200, $this->patientCredit($patient), 0.01);
    }

    // ── L. Cancelled invoices are skipped ────────────────────────────────────

    public function test_L_cancelled_invoices_are_skipped(): void
    {
        $patient   = $this->patient();
        $cancelled = $this->invoiceFor($patient, 900, today()->subDays(30)->toDateString());
        $cancelled->update(['status' => 'cancelled']);

        $live = $this->invoiceFor($patient, 600);

        $result = $this->pay($patient, 600);

        $this->assertEqualsWithDelta(900, $cancelled->fresh()->balance_due, 0.01,
            'A cancelled invoice must never receive an allocation.');
        $this->assertEqualsWithDelta(0, $live->fresh()->balance_due, 0.01);
        $this->assertSame([$live->invoice_number],
            array_column($result['allocations'], 'invoice_number'));
        $this->assertSame(0, InvoicePayment::where('invoice_id', $cancelled->id)->count());
    }

    // ── M. Atomic rollback ───────────────────────────────────────────────────

    public function test_M_a_failure_mid_allocation_rolls_everything_back(): void
    {
        $patient = $this->patient();
        $a = $this->invoiceFor($patient, 1000, today()->subDays(10)->toDateString());
        $b = $this->invoiceFor($patient, 500);

        // Force the surplus leg to explode AFTER both invoices have been paid.
        $boom = \Mockery::mock(WalletService::class);
        $boom->shouldReceive('receiveAdvance')->andThrow(new \RuntimeException('forced failure'));
        $this->instance(WalletService::class, $boom);

        try {
            $this->pay($patient, 2000); // 1,500 settles both, 500 surplus -> boom
            $this->fail('Expected the allocation to throw.');
        } catch (\RuntimeException $e) {
            $this->assertSame('forced failure', $e->getMessage());
        }

        // Nothing may survive: no payments, no receipts, no finance rows, and
        // both invoices exactly as they were.
        $this->assertSame(0, InvoicePayment::where('patient_id', $patient->id)->count(),
            'A failed allocation must not leave payment rows behind.');
        $this->assertSame(0, Receipt::where('patient_id', $patient->id)->count());
        $this->assertSame(0, FinanceTransaction::where('patient_id', $patient->id)->count());
        $this->assertEqualsWithDelta(1000, $a->fresh()->balance_due, 0.01);
        $this->assertEqualsWithDelta(500,  $b->fresh()->balance_due, 0.01);
    }

    // ── N. The existing single-invoice path still works ──────────────────────

    public function test_N_existing_single_invoice_record_payment_still_works(): void
    {
        $user    = $this->userWithModulePerm('finance', true, true, true);
        $patient = $this->patient();
        $invoice = $this->invoiceFor($patient, 1000);

        $this->actingAs($user)
            ->post(route('billing.payment', $invoice), [
                'amount'       => 400,
                'payment_mode' => 'cash',
                'payment_date' => today()->toDateString(),
            ])
            ->assertRedirect();

        $invoice->refresh();
        $this->assertEqualsWithDelta(600, $invoice->balance_due, 0.01);
        $this->assertSame('partial', $invoice->status);

        // Still exactly one per-invoice RCP- receipt, unchanged by this feature.
        $receipt = Receipt::where('patient_id', $patient->id)->first();
        $this->assertNotNull($receipt);
        $this->assertStringStartsWith('RCP-', $receipt->receipt_number);
        $this->assertSame($invoice->id, $receipt->invoice_id);
    }

    // ── O. One tender → one consolidated receipt ─────────────────────────────

    public function test_O_one_tender_produces_exactly_one_consolidated_receipt(): void
    {
        $patient = $this->patient();
        $this->invoiceFor($patient, 1000, today()->subDays(10)->toDateString());
        $this->invoiceFor($patient, 1500);

        $result = $this->pay($patient, 3000);   // settles both, 500 surplus

        $receipts = Receipt::where('patient_id', $patient->id)->get();

        $this->assertCount(1, $receipts,
            'One payment by the patient must produce one receipt — not one per invoice, '
            . 'and not a separate ADV- slip for the surplus.');

        $receipt = $receipts->first();
        $this->assertSame($result['receipt']->id, $receipt->id);
        $this->assertStringStartsWith('PAY-', $receipt->receipt_number);
        $this->assertEqualsWithDelta(3000, $receipt->amount, 0.01,
            'The receipt is for the full amount the patient handed over.');
        $this->assertNull($receipt->invoice_id);
        $this->assertTrue($receipt->isAllocation());

        // ...but internally there is still one payment row per invoice.
        $this->assertSame(2, InvoicePayment::where('patient_id', $patient->id)->count());

        // The breakdown on the receipt explains where the money went.
        $b = $receipt->allocation_breakdown;
        $this->assertEqualsWithDelta(3000, $b['tender'], 0.01);
        $this->assertEqualsWithDelta(2500, $b['settled'], 0.01);
        $this->assertEqualsWithDelta(500,  $b['patient_credit'], 0.01);
        $this->assertCount(2, $b['invoices']);
    }

    // ── P. Finance totals — surplus is never revenue ─────────────────────────

    public function test_P_finance_totals_are_correct_and_surplus_is_not_revenue(): void
    {
        $patient = $this->patient();
        $a = $this->invoiceFor($patient, 1000, today()->subDays(10)->toDateString());
        $this->partPay($a, 600);                 // 400 outstanding
        $this->invoiceFor($patient, 1500);       // 1,500 outstanding

        $this->pay($patient, 2500);

        $income  = $this->income($patient);
        $advance = $this->advance($patient);

        // Cash in through THIS tender: 2,500. Revenue recognised: 1,900.
        // Liability created: 600. 1,900 + 600 = 2,500 — every rupee accounted
        // for exactly once.
        $this->assertEqualsWithDelta(1900, $income, 0.01);
        $this->assertEqualsWithDelta(600, $advance, 0.01);
        $this->assertEqualsWithDelta(2500, $income + $advance, 0.01,
            'Cash in must equal revenue + liability. No rupee counted twice, none lost.');

        // The surplus must not be sitting in an income row anywhere.
        $this->assertSame(0, FinanceTransaction::where('patient_id', $patient->id)
            ->where('type', 'income')->where('amount', 600)->count(),
            'The surplus must never appear as income.');

        // Income rows mirror the invoice payments one-for-one.
        $this->assertSame(2, FinanceTransaction::where('patient_id', $patient->id)
            ->where('type', 'income')->count());
    }

    // ── Q. The new route, end to end ─────────────────────────────────────────
    // Not in the required list, but the route, the validation rules and the
    // controller wiring are otherwise untested — and a service that works
    // behind a broken route helps nobody.

    public function test_Q_patient_payment_route_allocates_end_to_end(): void
    {
        $user    = $this->userWithModulePerm('finance', true, true, true);
        $patient = $this->patient();

        $a = $this->invoiceFor($patient, 1000, today()->subDays(20)->toDateString());
        $this->partPay($a, 600);                 // 400 outstanding
        $b = $this->invoiceFor($patient, 1500);

        $this->actingAs($user)
            ->post(route('billing.patientPayment', $patient), [
                'amount'       => 2500,
                'payment_mode' => 'cash',
                'payment_date' => today()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertEqualsWithDelta(0, $a->fresh()->balance_due, 0.01);
        $this->assertEqualsWithDelta(0, $b->fresh()->balance_due, 0.01);
        $this->assertEqualsWithDelta(600, $this->patientCredit($patient), 0.01);
        $this->assertCount(1, Receipt::where('patient_id', $patient->id)->get());
    }

    /** EMI is not a simple tender and must be refused by this path. */
    public function test_Q2_emi_is_rejected_in_allocation_mode(): void
    {
        $user    = $this->userWithModulePerm('finance', true, true, true);
        $patient = $this->patient();
        $this->invoiceFor($patient, 1000);

        $this->actingAs($user)
            ->post(route('billing.patientPayment', $patient), [
                'amount'       => 1000,
                'payment_mode' => 'emi',
                'payment_date' => today()->toDateString(),
            ])
            ->assertSessionHasErrors('payment_mode');

        $this->assertSame(0, InvoicePayment::where('patient_id', $patient->id)->count());
    }
}
