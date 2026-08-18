<?php

namespace Tests\Feature\Billing;

use App\Models\FinalBill;
use App\Models\Finance\FinanceTransaction;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Patient;
use App\Models\Receipt;
use App\Models\User;
use App\Services\Billing\ReceiptRestoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * RECEIPT RESTORE — data-integrity regression cover.
 *
 * Real bug at Tulip (INV-2026-00075 / RCP-2026-00064): a receipt was voided with
 * no refund, correctly leaving the invoice outstanding. Restoring it from Trash
 * brought the ₹1,500 credit back to the ledger but left the invoice Unpaid.
 *
 * Cause: voiding touches SIX records; restore ran
 * `Receipt::onlyTrashed()->findOrFail($id)->restore()` — one of them. The ledger
 * reads receipts; invoice.paid_amount reads invoice_payments. One was restored.
 *
 * The invariant: restore reinstates the EXACT pre-void state and creates NO new
 * financial record. Where it cannot do that, it refuses and logs.
 */
class ReceiptRestoreTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    /** Voiding is admin-only, and the route group needs finance access. */
    private function adminUser(): User
    {
        $user = $this->userWithModulePerm('finance', true, true, true);
        $user->update(['role' => 'admin']);

        return $user->fresh();
    }

    private function patient(string $name = 'Restore Patient'): Patient
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

    /** The real production paths: pay, then void with no refund. */
    private function payInvoice(User $user, Invoice $invoice, float $amount): Receipt
    {
        $this->actingAs($user)->post(route('billing.payment', $invoice), [
            'amount'       => $amount,
            'payment_mode' => 'cash',
            'payment_date' => today()->toDateString(),
        ])->assertRedirect();

        return Receipt::where('invoice_id', $invoice->id)->latest('id')->firstOrFail();
    }

    private function voidReceipt(User $user, Invoice $invoice, Receipt $receipt): void
    {
        $this->actingAs($user)->post(route('billing.receipt.void', [$invoice, $receipt]), [
            'void_reason'        => 'Recorded against the wrong invoice',
            'void_refund_method' => 'no_refund',
        ])->assertRedirect();
    }

    private function restore(int $receiptId, ?int $userId): array
    {
        return app(ReceiptRestoreService::class)->restore($receiptId, $userId);
    }

    // ── A + B + C: the reported bug, end to end ─────────────────────────────

    public function test_A_invoice_is_paid_after_the_receipt_is_recorded(): void
    {
        $user    = $this->adminUser();
        $patient = $this->patient();
        $invoice = $this->invoiceFor($patient, 1500);

        $this->payInvoice($user, $invoice, 1500);

        $this->assertEqualsWithDelta(0, $invoice->fresh()->balance_due, 0.01);
        $this->assertSame('paid', $invoice->fresh()->status);
    }

    public function test_B_voiding_the_receipt_makes_the_invoice_outstanding(): void
    {
        $user    = $this->adminUser();
        $patient = $this->patient();
        $invoice = $this->invoiceFor($patient, 1500);
        $receipt = $this->payInvoice($user, $invoice, 1500);

        $this->voidReceipt($user, $invoice, $receipt);

        $this->assertEqualsWithDelta(1500, $invoice->fresh()->balance_due, 0.01);
        $this->assertNotNull($receipt->fresh()->deleted_at);
        $this->assertNotNull(InvoicePayment::withTrashed()
            ->where('invoice_id', $invoice->id)->firstOrFail()->deleted_at,
            'The void must also soft-delete the payment.');
    }

    public function test_C_restoring_the_receipt_makes_the_invoice_paid_again(): void
    {
        $user    = $this->adminUser();
        $patient = $this->patient();
        $invoice = $this->invoiceFor($patient, 1500);
        $receipt = $this->payInvoice($user, $invoice, 1500);
        $this->voidReceipt($user, $invoice, $receipt);

        $result = $this->restore($receipt->id, $user->id);

        // The reported bug: this used to stay at 1,500 outstanding.
        $this->assertEqualsWithDelta(0, $invoice->fresh()->balance_due, 0.01,
            'A restored receipt must put the invoice back to paid.');
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertEqualsWithDelta(1500, $invoice->fresh()->paid_amount, 0.01);
        $this->assertTrue($result['restored']);
        $this->assertSame(1, $result['payments_restored']);
        $this->assertNull(Receipt::find($receipt->id)->deleted_at);
    }

    // ── D. Exactly one credit ───────────────────────────────────────────────

    public function test_D_exactly_one_receipt_credit_exists_after_restore(): void
    {
        $user    = $this->adminUser();
        $patient = $this->patient();
        $invoice = $this->invoiceFor($patient, 1500);
        $receipt = $this->payInvoice($user, $invoice, 1500);
        $this->voidReceipt($user, $invoice, $receipt);
        $this->restore($receipt->id, $user->id);

        $this->assertSame(1, Receipt::where('patient_id', $patient->id)->count(),
            'One receipt, not two.');
        $this->assertEqualsWithDelta(1500,
            (float) Receipt::where('patient_id', $patient->id)->sum('amount'), 0.01);
        $this->assertEqualsWithDelta(1500, $invoice->fresh()->paid_amount, 0.01,
            'Paid amount must be 1,500 — never 3,000.');
    }

    // ── E + F. Restore creates nothing ──────────────────────────────────────

    public function test_E_restore_creates_no_new_finance_transaction(): void
    {
        $user    = $this->adminUser();
        $patient = $this->patient();
        $invoice = $this->invoiceFor($patient, 1500);
        $receipt = $this->payInvoice($user, $invoice, 1500);
        $this->voidReceipt($user, $invoice, $receipt);

        $before = FinanceTransaction::count();

        $this->restore($receipt->id, $user->id);

        $this->assertSame($before, FinanceTransaction::count(),
            'Restore must reactivate the existing finance row, never create one.');

        // The income row is active again; the void compensating row is now voided.
        $this->assertSame(1, FinanceTransaction::where('patient_id', $patient->id)
            ->where('type', 'income')->where('status', 'active')->count());
        $this->assertSame(0, FinanceTransaction::where('patient_id', $patient->id)
            ->where('type', 'refund')->where('status', 'active')->count());
    }

    public function test_F_restore_creates_no_new_invoice_payment(): void
    {
        $user    = $this->adminUser();
        $patient = $this->patient();
        $invoice = $this->invoiceFor($patient, 1500);
        $receipt = $this->payInvoice($user, $invoice, 1500);
        $this->voidReceipt($user, $invoice, $receipt);

        $before = InvoicePayment::withTrashed()->count();

        $this->restore($receipt->id, $user->id);

        $this->assertSame($before, InvoicePayment::withTrashed()->count(),
            'Restore must reinstate the original payment, never add one.');
        $this->assertSame(1, InvoicePayment::where('invoice_id', $invoice->id)->count());

        // Its void audit is cleared — the payment is live again.
        $payment = InvoicePayment::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertNull($payment->void_reason);
        $this->assertNull($payment->voided_by);
    }

    /** The Final Bill is restored, not re-minted. */
    public function test_F2_the_final_bill_is_restored_rather_than_duplicated(): void
    {
        $user    = $this->adminUser();
        $patient = $this->patient();
        $invoice = $this->invoiceFor($patient, 1500);
        $receipt = $this->payInvoice($user, $invoice, 1500);

        $billsAfterPayment = FinalBill::where('invoice_id', $invoice->id)->count();

        $this->voidReceipt($user, $invoice, $receipt);
        $this->restore($receipt->id, $user->id);

        $this->assertSame($billsAfterPayment,
            FinalBill::where('invoice_id', $invoice->id)->count(),
            'No second Final Bill may be generated by a restore.');
    }

    // ── G. Idempotent ───────────────────────────────────────────────────────

    public function test_G_restoring_twice_changes_nothing(): void
    {
        $user    = $this->adminUser();
        $patient = $this->patient();
        $invoice = $this->invoiceFor($patient, 1500);
        $receipt = $this->payInvoice($user, $invoice, 1500);
        $this->voidReceipt($user, $invoice, $receipt);
        $this->restore($receipt->id, $user->id);

        $financeCount = FinanceTransaction::count();
        $paymentCount = InvoicePayment::withTrashed()->count();

        $second = $this->restore($receipt->id, $user->id);

        $this->assertTrue($second['already_active']);
        $this->assertFalse($second['restored']);
        $this->assertSame($financeCount, FinanceTransaction::count());
        $this->assertSame($paymentCount, InvoicePayment::withTrashed()->count());
        $this->assertEqualsWithDelta(1500, $invoice->fresh()->paid_amount, 0.01);
    }

    // ── H. Never fabricate ──────────────────────────────────────────────────

    public function test_H_restore_refuses_when_the_payment_record_is_missing(): void
    {
        $user    = $this->adminUser();
        $patient = $this->patient();
        $invoice = $this->invoiceFor($patient, 1500);
        $receipt = $this->payInvoice($user, $invoice, 1500);
        $this->voidReceipt($user, $invoice, $receipt);

        // Simulate genuinely missing allocation data.
        //
        // Order matters: receipts.invoice_payment_id is declared
        // ->constrained('invoice_payments')->cascadeOnDelete(), so force-deleting
        // the payment while the pointer is still set makes MySQL cascade the
        // delete onto the receipt row itself — destroying the very record under
        // test. Detach the receipt FIRST, then drop the payment.
        //
        // NOTE: receipt_id lives on invoice_payments, not on receipts. Only the
        // receipt's own back-pointer needs clearing here.
        Receipt::withTrashed()->whereKey($receipt->id)
            ->update(['invoice_payment_id' => null]);
        InvoicePayment::withTrashed()->where('invoice_id', $invoice->id)->forceDelete();

        $financeBefore = FinanceTransaction::count();
        $paymentBefore = InvoicePayment::withTrashed()->count();

        $threw = false;
        try {
            $this->restore($receipt->id, $user->id);
        } catch (ValidationException $e) {
            $threw = true;
            $this->assertArrayHasKey('restore', $e->errors());
        }
        $this->assertTrue($threw, 'Restore must refuse rather than fabricate.');

        // Nothing invented, nothing restored.
        $this->assertSame($financeBefore, FinanceTransaction::count());
        $this->assertSame($paymentBefore, InvoicePayment::withTrashed()->count());
        $this->assertNotNull(Receipt::withTrashed()->find($receipt->id)->deleted_at,
            'A refused restore must leave the receipt in trash.');
        $this->assertEqualsWithDelta(1500, $invoice->fresh()->balance_due, 0.01);
    }

    /** A void that actually refunded money must not be restorable. */
    public function test_H2_a_void_that_refunded_money_cannot_be_restored(): void
    {
        $user    = $this->adminUser();
        $patient = $this->patient();
        $invoice = $this->invoiceFor($patient, 1500);
        $receipt = $this->payInvoice($user, $invoice, 1500);

        $this->actingAs($user)->post(route('billing.receipt.void', [$invoice, $receipt]), [
            'void_reason'        => 'Patient asked for the money back',
            'void_refund_method' => 'cash',
        ])->assertRedirect();

        $threw = false;
        try {
            $this->restore($receipt->id, $user->id);
        } catch (ValidationException $e) {
            $threw = true;
            $this->assertArrayHasKey('restore', $e->errors());
        }
        $this->assertTrue($threw,
            'Money left the clinic — restoring would re-recognise revenue that does not exist.');

        $this->assertNotNull(Receipt::withTrashed()->find($receipt->id)->deleted_at);
        $this->assertEqualsWithDelta(1500, $invoice->fresh()->balance_due, 0.01);
    }
}
