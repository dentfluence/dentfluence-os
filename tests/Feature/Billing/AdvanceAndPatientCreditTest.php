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
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * U8 — ADVANCE / PATIENT CREDIT (frozen business model, CEO 2026-08-15).
 *
 * The invariants this slice must never break:
 *
 *   An advance is NOT revenue. It is cash in + a liability out.
 *   Patient Credit settles an invoice as a TENDER, never as a discount.
 *   A wallet payment NEVER changes what the patient was charged.
 *
 * Every test below maps to a numbered frozen rule; the rule is cited in the
 * test so a future reader can trace the assertion back to the decision.
 */
class AdvanceAndPatientCreditTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    private function staff(): User
    {
        return User::factory()->create();
    }

    private function patient(string $name = 'U8 Patient'): Patient
    {
        return Patient::create([
            'name'      => $name,
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ]);
    }

    private function wallet(): WalletService
    {
        return app(WalletService::class);
    }

    /** A plain invoice for $amount with no discounts of any kind. */
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

    private function takeAdvance(Patient $patient, float $amount, string $mode = 'cash'): WalletTransaction
    {
        return $this->wallet()->receiveAdvance(
            patient:     $patient,
            amount:      $amount,
            paymentMode: $mode,
            paymentDate: today()->toDateString(),
            notes:       null,
            createdBy:   $this->staff()->id,
        );
    }

    // ── A. ₹10,000 advance ───────────────────────────────────────────────────

    public function test_A_advance_creates_credit_receipt_and_liability_but_no_invoice_and_no_revenue(): void
    {
        $patient = $this->patient();

        $this->takeAdvance($patient, 10000);

        // Rule 4 — no invoice is created by an advance.
        $this->assertSame(0, Invoice::where('patient_id', $patient->id)->count(),
            'An advance must not create an invoice.');

        // Rule 5 — no invoice-payment either: nothing was settled.
        $this->assertSame(0, InvoicePayment::where('patient_id', $patient->id)->count(),
            'An advance must not create an InvoicePayment.');

        // Rule 3 — the patient handed over cash, so they get a document.
        $receipt = Receipt::where('patient_id', $patient->id)->first();
        $this->assertNotNull($receipt, 'An advance must produce a receipt.');
        $this->assertSame('advance', $receipt->receipt_kind);
        $this->assertNull($receipt->invoice_id);
        $this->assertNull($receipt->invoice_payment_id);
        $this->assertEqualsWithDelta(10000, (float) $receipt->amount, 0.01);
        $this->assertStringStartsWith('ADV-', $receipt->receipt_number,
            'CEO decision B4 — advance receipts use their own ADV- series.');

        // Rule 6 — patient credit created, cash-backed.
        $wallet = Wallet::forPatient($patient->id);
        $this->assertEqualsWithDelta(10000, (float) $wallet->balance_patient_credit, 0.01);

        // Rule 7 — 'advance' is a SOURCE on the ledger row, not a second balance.
        $entry = WalletTransaction::where('patient_id', $patient->id)->first();
        $this->assertSame('advance', $entry->source);
        $this->assertSame(WalletService::FUNDING_PATIENT, $entry->funding);

        // Rules 2 + 14 — cash in, liability up. NOT revenue.
        $ft = FinanceTransaction::where('patient_id', $patient->id)->first();
        $this->assertNotNull($ft);
        $this->assertSame('advance', $ft->type,
            'An advance must be booked as a liability, never as income.');
        $this->assertSame(0, FinanceTransaction::where('patient_id', $patient->id)
            ->where('type', 'income')->count(),
            'No revenue may be recognised when an advance is received.');
    }

    // ── B. ₹10,000 advance → ₹6,000 treatment ────────────────────────────────

    public function test_B_patient_credit_settles_an_invoice_as_a_tender_without_reducing_it(): void
    {
        $patient = $this->patient();
        $this->takeAdvance($patient, 10000);

        $invoice   = $this->invoiceFor($patient, 6000);
        $totalBefore = (float) $invoice->total_amount;

        $result = $this->wallet()->settleInvoiceFromCredit(
            invoice: $invoice, amount: 6000, createdBy: $this->staff()->id
        );

        $invoice->refresh();

        // Rule 9 — the invoice is untouched. This is the heart of U8.
        $this->assertEqualsWithDelta(6000, $totalBefore, 0.01);
        $this->assertEqualsWithDelta(6000, (float) $invoice->total_amount, 0.01,
            'A wallet payment must never reduce the invoice total.');
        $this->assertEqualsWithDelta(0, (float) $invoice->wallet_applied, 0.01,
            'Patient credit must never be written to the discount column.');

        // Rule 8 — it is a payment, exactly like cash.
        $this->assertEqualsWithDelta(6000, $result['debited'], 0.01);
        $payment = InvoicePayment::where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($payment);
        $this->assertSame('wallet', $payment->payment_mode);
        $this->assertEqualsWithDelta(6000, (float) $payment->amount, 0.01);
        $this->assertEqualsWithDelta(6000, (float) $invoice->paid_amount, 0.01);
        $this->assertEqualsWithDelta(0, (float) $invoice->balance_due, 0.01);
        $this->assertSame('paid', $invoice->status);

        // Rule 15 — a receipt for the settlement.
        $receipt = Receipt::where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($receipt);
        $this->assertSame('payment', $receipt->receipt_kind);
        $this->assertSame('wallet', $receipt->payment_mode);
        $this->assertEqualsWithDelta(6000, (float) $receipt->amount, 0.01);

        // Remaining credit.
        $this->assertEqualsWithDelta(4000,
            (float) Wallet::forPatient($patient->id)->balance_patient_credit, 0.01);

        // Rule 14 — revenue recognised NOW, on delivery.
        $income = FinanceTransaction::where('patient_id', $patient->id)->where('type', 'income')->get();
        $this->assertCount(1, $income);
        $this->assertEqualsWithDelta(6000, (float) $income->first()->amount, 0.01);
        $this->assertSame('wallet', $income->first()->payment_mode);

        // Rule 15 — no new cash movement. Only the original advance was cash.
        $cashFt = FinanceTransaction::where('patient_id', $patient->id)
            ->where('payment_mode', 'cash')->get();
        $this->assertCount(1, $cashFt, 'Spending credit must not create a second cash movement.');
        $this->assertSame('advance', $cashFt->first()->type);
    }

    // ── C. ₹10,000 advance → ₹10,000 treatment ───────────────────────────────

    public function test_C_full_credit_settlement_zeroes_the_balance_and_pays_the_invoice(): void
    {
        $patient = $this->patient();
        $this->takeAdvance($patient, 10000);

        $invoice = $this->invoiceFor($patient, 10000);

        $this->wallet()->settleInvoiceFromCredit(
            invoice: $invoice, amount: 10000, createdBy: $this->staff()->id
        );

        $invoice->refresh();

        $this->assertEqualsWithDelta(0,
            (float) Wallet::forPatient($patient->id)->balance_patient_credit, 0.01);
        $this->assertSame('paid', $invoice->status);
        $this->assertEqualsWithDelta(10000, (float) $invoice->total_amount, 0.01);
        $this->assertEqualsWithDelta(10000, (float) $invoice->paid_amount, 0.01);

        $payments = InvoicePayment::where('invoice_id', $invoice->id)->get();
        $this->assertCount(1, $payments);
        $this->assertSame('wallet', $payments->first()->payment_mode);

        $this->assertCount(1, Receipt::where('invoice_id', $invoice->id)->get());
    }

    // ── D. advance → treatment → reversal ────────────────────────────────────

    public function test_D_reversing_a_wallet_payment_restores_patient_credit(): void
    {
        $patient = $this->patient();
        $this->takeAdvance($patient, 10000);
        $invoice = $this->invoiceFor($patient, 6000);

        $this->wallet()->settleInvoiceFromCredit(
            invoice: $invoice, amount: 6000, createdBy: $this->staff()->id
        );
        $this->assertEqualsWithDelta(4000,
            (float) Wallet::forPatient($patient->id)->balance_patient_credit, 0.01);

        // Reverse the tender leg.
        $this->wallet()->restorePatientCredit(
            patientId: $patient->id, amount: 6000,
            notes: 'Void test', createdBy: $this->staff()->id, invoiceId: $invoice->id
        );

        // Credit is whole again, and it is still PATIENT money (still refundable).
        $wallet = Wallet::forPatient($patient->id);
        $this->assertEqualsWithDelta(10000, (float) $wallet->balance_patient_credit, 0.01);

        $restored = WalletTransaction::where('patient_id', $patient->id)
            ->where('source', 'refund')->first();
        $this->assertNotNull($restored);
        $this->assertSame(WalletService::FUNDING_PATIENT, $restored->funding);

        // The restored credit is withdrawable, because the clinic really does owe it.
        $out = $this->wallet()->withdraw(
            patientId: $patient->id, amount: 10000, paymentMode: 'cash', createdBy: $this->staff()->id
        );
        $this->assertEqualsWithDelta(10000, $out, 0.01);
    }

    // ── E. Patient Credit never expires ──────────────────────────────────────

    public function test_E_patient_credit_does_not_expire(): void
    {
        $patient = $this->patient();
        $this->takeAdvance($patient, 5000);

        // Backdate the credit far into the past and stamp an expiry that has
        // long gone. Rule 11: none of that may make the patient's own money
        // unusable.
        WalletTransaction::where('patient_id', $patient->id)
            ->where('direction', 'credit')
            ->update([
                'created_at'  => now()->subYears(3),
                'expiry_date' => now()->subYears(2)->toDateString(),
            ]);

        $wallet = Wallet::forPatient($patient->id);
        $wallet->recalculate();
        $this->assertEqualsWithDelta(5000, (float) $wallet->fresh()->balance_patient_credit, 0.01);

        $invoice = $this->invoiceFor($patient, 5000);
        $result  = $this->wallet()->settleInvoiceFromCredit(
            invoice: $invoice, amount: 5000, createdBy: $this->staff()->id
        );

        $this->assertEqualsWithDelta(5000, $result['debited'], 0.01,
            'Patient credit must remain spendable regardless of age or expiry_date.');
    }

    // ── F. Clinic-funded credit is not cash-refundable ───────────────────────

    public function test_F_clinic_funded_credit_is_never_cash_refundable(): void
    {
        $patient = $this->patient();

        // A gift / referral reward — the clinic never received this money.
        $this->wallet()->credit(
            patientId:  $patient->id,
            amount:     2000,
            creditType: 'permanent',
            notes:      'Referral reward',
            createdBy:  $this->staff()->id,
            funding:    WalletService::FUNDING_CLINIC,
        );

        $wallet = Wallet::forPatient($patient->id);
        $this->assertEqualsWithDelta(0, (float) $wallet->balance_patient_credit, 0.01,
            'Clinic-funded credit must never count as patient credit.');

        // Rule 12 — it cannot be withdrawn as cash.
        $out = $this->wallet()->withdraw(
            patientId: $patient->id, amount: 2000, paymentMode: 'cash', createdBy: $this->staff()->id
        );
        $this->assertEqualsWithDelta(0, $out, 0.01,
            'The clinic cannot pay out cash it never received.');

        // Nor can it be tendered as patient credit against an invoice.
        $invoice = $this->invoiceFor($patient, 2000);
        $result  = $this->wallet()->settleInvoiceFromCredit(
            invoice: $invoice, amount: 2000, createdBy: $this->staff()->id
        );
        $this->assertEqualsWithDelta(0, $result['debited'], 0.01,
            'Clinic-funded credit is a concession, not a tender.');
    }

    // ── G + H. 100% wallet payment through the real HTTP payment path ────────

    public function test_G_and_H_full_wallet_payment_with_zero_cash_succeeds_via_http(): void
    {
        // The billing routes sit behind `module:finance`; a bare factory user has
        // no role_id and canAccess() denies it. Build a real finance persona.
        $staff   = $this->userWithModulePerm('finance', true, true, true);
        $patient = $this->patient();
        $this->takeAdvance($patient, 10000);

        $invoice     = $this->invoiceFor($patient, 6000);
        $totalBefore = (float) $invoice->total_amount;

        $response = $this->actingAs($staff)->post(route('billing.payment', $invoice), [
            'amount'       => 0,          // rule 10 — zero cash leg is valid
            'wallet_used'  => 6000,
            'payment_mode' => 'cash',
            'payment_date' => today()->toDateString(),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $invoice->refresh();

        // H — invoice value untouched.
        $this->assertEqualsWithDelta($totalBefore, (float) $invoice->total_amount, 0.01);
        $this->assertEqualsWithDelta(0, (float) $invoice->wallet_applied, 0.01);

        // G — fully settled, with a receipt, and no zero-value cash artefacts.
        $this->assertSame('paid', $invoice->status);
        $payments = InvoicePayment::where('invoice_id', $invoice->id)->get();
        $this->assertCount(1, $payments, 'A zero-value cash payment row must not be created.');
        $this->assertSame('wallet', $payments->first()->payment_mode);

        $receipts = Receipt::where('invoice_id', $invoice->id)->get();
        $this->assertCount(1, $receipts);
        $this->assertEqualsWithDelta(6000, (float) $receipts->first()->amount, 0.01);

        $this->assertEqualsWithDelta(4000,
            (float) Wallet::forPatient($patient->id)->balance_patient_credit, 0.01);
    }

    // ── I. No double counting ────────────────────────────────────────────────

    public function test_I_advance_then_redemption_is_not_counted_twice(): void
    {
        $patient = $this->patient();
        $this->takeAdvance($patient, 10000);
        $invoice = $this->invoiceFor($patient, 6000);
        $this->wallet()->settleInvoiceFromCredit(
            invoice: $invoice, amount: 6000, createdBy: $this->staff()->id
        );

        // Revenue is recognised exactly once, and only for the delivered service.
        $revenue = (float) FinanceTransaction::where('patient_id', $patient->id)
            ->where('type', 'income')->where('status', 'active')->sum('amount');
        $this->assertEqualsWithDelta(6000, $revenue, 0.01,
            'The ₹10,000 advance must never appear as revenue.');

        // Cash is counted exactly once, at deposit.
        $cash = (float) FinanceTransaction::where('patient_id', $patient->id)
            ->where('payment_mode', 'cash')->where('status', 'active')->sum('amount');
        $this->assertEqualsWithDelta(10000, $cash, 0.01);

        // The two are different events, so summing InvoicePayment (what every
        // income report does) can never pick up the advance.
        $viaPayments = (float) InvoicePayment::where('patient_id', $patient->id)->sum('amount');
        $this->assertEqualsWithDelta(6000, $viaPayments, 0.01);

        // Liability is what remains unspent.
        $this->assertEqualsWithDelta(4000,
            (float) Wallet::forPatient($patient->id)->balance_patient_credit, 0.01);
    }

    // ── Rule 16 — the reconciliation invariant ───────────────────────────────

    public function test_patient_credit_balance_reconciles_with_its_ledger(): void
    {
        $patient = $this->patient();
        $this->takeAdvance($patient, 10000);
        $invoice = $this->invoiceFor($patient, 6000);
        $this->wallet()->settleInvoiceFromCredit(
            invoice: $invoice, amount: 6000, createdBy: $this->staff()->id
        );

        $ledger = (float) WalletTransaction::where('patient_id', $patient->id)
            ->where('funding', WalletService::FUNDING_PATIENT)
            ->selectRaw('SUM(CASE WHEN direction="credit" THEN amount ELSE -amount END) as bal')
            ->value('bal');

        $this->assertEqualsWithDelta(
            $ledger,
            (float) Wallet::forPatient($patient->id)->balance_patient_credit,
            0.01,
            'Rule 16 — the patient credit balance must always equal its ledger.'
        );
    }
}
