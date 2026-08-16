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
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * G1 — OVERPAYMENT.
 *
 * The invariant: a rupee is EITHER revenue OR a liability. Never both.
 *
 * Before this slice, paying ₹1,200 against a ₹1,000 invoice booked the whole
 * ₹1,200 as income AND created ₹200 of patient credit — the same ₹200 counted
 * twice, once as money earned and once as money owed.
 *
 * Now the tender is split at the point of payment: ₹1,000 settles the invoice
 * (revenue), ₹200 is routed through the U8 advance path (liability). Nothing
 * changes when the payment does not exceed the balance.
 */
class OverpaymentTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->staff = $this->userWithModulePerm('finance', true, true, true);
    }

    private function patient(string $name = 'G1 Patient'): Patient
    {
        return Patient::create([
            'name'      => $name,
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ]);
    }

    private function invoiceFor(Patient $patient, float $amount): Invoice
    {
        $invoice = Invoice::create([
            'invoice_number' => Invoice::nextNumber(),
            'patient_id'     => $patient->id,
            'invoice_date'   => today()->toDateString(),
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

    private function pay(Invoice $invoice, float $amount, array $extra = [])
    {
        return $this->actingAs($this->staff)->post(route('billing.payment', $invoice), array_merge([
            'amount'       => $amount,
            'payment_mode' => 'cash',
            'payment_date' => today()->toDateString(),
        ], $extra));
    }

    private function income(Patient $patient): float
    {
        return (float) FinanceTransaction::where('patient_id', $patient->id)
            ->where('type', 'income')->where('status', 'active')->sum('amount');
    }

    private function advanceBooked(Patient $patient): float
    {
        return (float) FinanceTransaction::where('patient_id', $patient->id)
            ->where('type', 'advance')->where('status', 'active')->sum('amount');
    }

    // ── 1. Exact payment — behaviour must be completely unchanged ────────────

    public function test_1_exact_payment_is_unchanged(): void
    {
        $patient = $this->patient();
        $invoice = $this->invoiceFor($patient, 1000);

        $this->pay($invoice, 1000)->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertEqualsWithDelta(1000, (float) $invoice->paid_amount, 0.01);
        $this->assertEqualsWithDelta(0, (float) $invoice->balance_due, 0.01);

        $payments = InvoicePayment::where('invoice_id', $invoice->id)->get();
        $this->assertCount(1, $payments);
        $this->assertEqualsWithDelta(1000, (float) $payments->first()->amount, 0.01);

        $this->assertEqualsWithDelta(1000, $this->income($patient), 0.01);
        $this->assertEqualsWithDelta(0, $this->advanceBooked($patient), 0.01);
        $this->assertEqualsWithDelta(0,
            (float) Wallet::forPatient($patient->id)->balance_patient_credit, 0.01,
            'An exact payment must not create patient credit.');

        // One receipt, no ADV- document.
        $this->assertCount(1, Receipt::where('patient_id', $patient->id)->get());
    }

    // ── 2. Underpayment — behaviour must be completely unchanged ─────────────

    public function test_2_underpayment_leaves_a_balance_and_creates_no_credit(): void
    {
        $patient = $this->patient();
        $invoice = $this->invoiceFor($patient, 1000);

        $this->pay($invoice, 400)->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertSame('partial', $invoice->status);
        $this->assertEqualsWithDelta(400, (float) $invoice->paid_amount, 0.01);
        $this->assertEqualsWithDelta(600, (float) $invoice->balance_due, 0.01);

        $this->assertEqualsWithDelta(400, $this->income($patient), 0.01);
        $this->assertEqualsWithDelta(0, $this->advanceBooked($patient), 0.01);
        $this->assertEqualsWithDelta(0,
            (float) Wallet::forPatient($patient->id)->balance_patient_credit, 0.01);
    }

    // ── 3. Overpayment — only the settlement is revenue ──────────────────────

    public function test_3_overpayment_recognises_only_the_settled_amount_as_income(): void
    {
        $patient = $this->patient();
        $invoice = $this->invoiceFor($patient, 1000);

        $this->pay($invoice, 1200)->assertSessionHasNoErrors();

        $invoice->refresh();

        // The invoice was settled for exactly what it was worth.
        $this->assertEqualsWithDelta(1000, (float) $invoice->total_amount, 0.01);
        $this->assertEqualsWithDelta(1000, (float) $invoice->paid_amount, 0.01,
            'paid_amount must not exceed the invoice — the surplus is not a payment on it.');
        $this->assertEqualsWithDelta(0, (float) $invoice->balance_due, 0.01);
        $this->assertSame('paid', $invoice->status);

        // The payment row records the settlement, not the whole tender.
        $payment = InvoicePayment::where('invoice_id', $invoice->id)->first();
        $this->assertEqualsWithDelta(1000, (float) $payment->amount, 0.01);

        // ₹1,000 revenue. ₹200 liability. Never both.
        $this->assertEqualsWithDelta(1000, $this->income($patient), 0.01,
            'Only the settled ₹1,000 may be recognised as income.');
        $this->assertEqualsWithDelta(200, $this->advanceBooked($patient), 0.01,
            'The ₹200 surplus must be booked as an advance, not revenue.');
    }

    // ── 4. Overpayment creates patient credit, with its own advance receipt ──

    public function test_4_overpayment_creates_patient_credit(): void
    {
        $patient = $this->patient();
        $invoice = $this->invoiceFor($patient, 1000);

        $this->pay($invoice, 1200)->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(200,
            (float) Wallet::forPatient($patient->id)->balance_patient_credit, 0.01);

        // Two documents, because two different things happened: a settlement
        // and an advance.
        $payment = Receipt::where('patient_id', $patient->id)->where('receipt_kind', 'payment')->get();
        $advance = Receipt::where('patient_id', $patient->id)->where('receipt_kind', 'advance')->get();

        $this->assertCount(1, $payment);
        $this->assertEqualsWithDelta(1000, (float) $payment->first()->amount, 0.01);

        $this->assertCount(1, $advance);
        $this->assertEqualsWithDelta(200, (float) $advance->first()->amount, 0.01);
        $this->assertStringStartsWith('ADV-', $advance->first()->receipt_number);
        $this->assertNull($advance->first()->invoice_id);
    }

    // ── 5. The resulting credit is spendable on a later invoice ──────────────

    public function test_5_credit_from_an_overpayment_pays_a_later_invoice(): void
    {
        $patient = $this->patient();
        $first   = $this->invoiceFor($patient, 1000);
        $this->pay($first, 1200)->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(200,
            (float) Wallet::forPatient($patient->id)->balance_patient_credit, 0.01);

        // A second, smaller invoice settled entirely from that credit.
        $second      = $this->invoiceFor($patient, 200);
        $totalBefore = (float) $second->total_amount;

        $this->pay($second, 0, ['wallet_used' => 200])->assertSessionHasNoErrors();

        $second->refresh();
        $this->assertEqualsWithDelta($totalBefore, (float) $second->total_amount, 0.01,
            'Spending credit must not change the invoice value.');
        $this->assertSame('paid', $second->status);

        $walletPayment = InvoicePayment::where('invoice_id', $second->id)->first();
        $this->assertSame('wallet', $walletPayment->payment_mode);
        $this->assertEqualsWithDelta(200, (float) $walletPayment->amount, 0.01);

        $this->assertEqualsWithDelta(0,
            (float) Wallet::forPatient($patient->id)->balance_patient_credit, 0.01);
    }

    // ── 6. No double counting anywhere in finance ────────────────────────────

    public function test_6_no_double_counting_across_overpayment_and_redemption(): void
    {
        $patient = $this->patient();

        // ₹1,200 cash against a ₹1,000 invoice, then the ₹200 credit spent later.
        $first = $this->invoiceFor($patient, 1000);
        $this->pay($first, 1200)->assertSessionHasNoErrors();

        $second = $this->invoiceFor($patient, 200);
        $this->pay($second, 0, ['wallet_used' => 200])->assertSessionHasNoErrors();

        // Total revenue = the two services actually delivered = 1000 + 200.
        // NOT 1400 (which is what booking the full tender as income would give).
        $this->assertEqualsWithDelta(1200, $this->income($patient), 0.01,
            'Revenue must equal services delivered, not cash tendered.');

        // Cash physically received = ₹1,200, counted exactly once.
        $cash = (float) FinanceTransaction::where('patient_id', $patient->id)
            ->where('payment_mode', 'cash')->where('status', 'active')->sum('amount');
        $this->assertEqualsWithDelta(1200, $cash, 0.01);

        // Income reports sum InvoicePayment — that view must agree with the ledger.
        $viaPayments = (float) InvoicePayment::where('patient_id', $patient->id)->sum('amount');
        $this->assertEqualsWithDelta(1200, $viaPayments, 0.01);

        // Liability is fully discharged; nothing stranded.
        $this->assertEqualsWithDelta(0,
            (float) Wallet::forPatient($patient->id)->balance_patient_credit, 0.01);

        // And the ledger reconciles: cash in == revenue + remaining liability.
        $advance = $this->advanceBooked($patient);
        $this->assertEqualsWithDelta(200, $advance, 0.01);
    }

    // ── Guard: paying against an already-settled invoice is a pure advance ───

    public function test_payment_on_a_settled_invoice_becomes_pure_patient_credit(): void
    {
        $patient = $this->patient();
        $invoice = $this->invoiceFor($patient, 1000);
        $this->pay($invoice, 1000)->assertSessionHasNoErrors();

        // Second payment on an invoice with zero balance.
        $this->pay($invoice, 500)->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertEqualsWithDelta(1000, (float) $invoice->paid_amount, 0.01,
            'A settled invoice must not absorb further payment.');

        $this->assertCount(1, InvoicePayment::where('invoice_id', $invoice->id)->get(),
            'No zero-value payment row may be created.');

        $this->assertEqualsWithDelta(500,
            (float) Wallet::forPatient($patient->id)->balance_patient_credit, 0.01);
        $this->assertEqualsWithDelta(1000, $this->income($patient), 0.01);
        $this->assertEqualsWithDelta(500, $this->advanceBooked($patient), 0.01);
    }
}
