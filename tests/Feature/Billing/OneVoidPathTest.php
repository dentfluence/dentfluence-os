<?php

namespace Tests\Feature\Billing;

use App\Models\Finance\FinanceTransaction;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\Receipt;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * INT-01 / INT-02 / INT-03 (security audit 24 Sep 2026) — every way money
 * leaves an invoice now runs through ReceiptVoidService.
 *
 *   INT-01  refund-to-wallet is the patient's own money → patient-funded.
 *   INT-02  a wallet-tender receipt goes back as patient credit whatever
 *           refund method is chosen (it never came in as cash).
 *   INT-03  the legacy Cancel route is gone; Delete refuses an invoice
 *           that has payments.
 */
class OneVoidPathTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    private function admin(): User
    {
        $user = $this->userWithModulePerm('finance', true, true, true);
        $user->update(['role' => 'admin', 'role_id' => \App\Models\Role::where('slug', \App\Models\Role::ADMIN)->value('id')]);

        return $user->fresh();
    }

    private function invoice(float $amount = 1000): Invoice
    {
        $patient = Patient::create(['name' => 'Void Patient', 'phone' => '9' . random_int(100000000, 999999999), 'branch_id' => 1]);
        $invoice = Invoice::create([
            'invoice_number' => Invoice::nextNumber(),
            'patient_id'     => $patient->id,
            'invoice_date'   => today()->toDateString(),
            'status'         => 'draft',
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'description' => 'Treatment', 'unit_price' => $amount,
            'qty' => 1, 'net_amount' => $amount, 'gst_pct' => 0, 'gst_amount' => 0, 'total' => $amount,
        ]);
        $invoice->recalculate();

        return $invoice->fresh();
    }

    private function pay(User $user, Invoice $invoice, float $amount): Receipt
    {
        $this->actingAs($user)->post(route('billing.payment', $invoice), [
            'amount' => $amount, 'payment_mode' => 'cash', 'payment_date' => today()->toDateString(),
        ])->assertRedirect();

        return Receipt::where('invoice_id', $invoice->id)->latest('id')->firstOrFail();
    }

    private function refundCredits(Invoice $invoice)
    {
        return WalletTransaction::where('patient_id', $invoice->patient_id)
            ->where('direction', 'credit')->get();
    }

    public function test_void_refund_to_wallet_is_patient_funded(): void
    {
        $user = $this->admin();
        $invoice = $this->invoice();
        $receipt = $this->pay($user, $invoice, 1000);

        $this->actingAs($user)->post(route('billing.receipt.void', [$invoice, $receipt]), [
            'void_reason' => 'Patient changed plan', 'void_refund_method' => 'wallet',
        ])->assertRedirect();

        $credit = $this->refundCredits($invoice)->sole();
        $this->assertSame('patient', $credit->funding);
        $this->assertEquals(1000, (float) $credit->amount);
    }

    public function test_cancel_refund_to_wallet_is_patient_funded(): void
    {
        $user = $this->admin();
        $invoice = $this->invoice();
        $this->pay($user, $invoice, 400);

        $this->actingAs($user)->post(route('billing.cancelWithReason', $invoice), [
            'cancelled_reason' => 'Treatment not done', 'cancel_refund_method' => 'wallet',
        ])->assertRedirect();

        $credit = $this->refundCredits($invoice)->sole();
        $this->assertSame('patient', $credit->funding);
        $this->assertEquals(400, (float) $credit->amount);
        $this->assertSoftDeleted('invoices', ['id' => $invoice->id, 'status' => 'cancelled']);
    }

    public function test_cancel_gives_a_wallet_tender_back_as_credit_even_when_cash_is_picked(): void
    {
        $user = $this->admin();
        $invoice = $this->invoice();
        $receipt = $this->pay($user, $invoice, 600);
        $receipt->forceFill(['payment_mode' => 'wallet'])->save(); // this leg was paid from patient credit

        $this->actingAs($user)->post(route('billing.cancelWithReason', $invoice), [
            'cancelled_reason' => 'Treatment not done', 'cancel_refund_method' => 'cash',
        ])->assertRedirect();

        $credit = $this->refundCredits($invoice)->sole();
        $this->assertSame('patient', $credit->funding);
        $this->assertEquals(600, (float) $credit->amount);

        $refund = FinanceTransaction::where('source_type', Receipt::class)->where('source_id', $receipt->id)
            ->where('type', 'refund')->sole();
        $this->assertSame('wallet', $refund->payment_mode); // no cash paid out for credit
    }

    public function test_cancel_with_no_refund_voids_payments_and_income(): void
    {
        $user = $this->admin();
        $invoice = $this->invoice();
        $receipt = $this->pay($user, $invoice, 300);

        $this->actingAs($user)->post(route('billing.cancelWithReason', $invoice), [
            'cancelled_reason' => 'Entered twice', 'cancel_refund_method' => 'no_refund',
        ])->assertRedirect();

        $this->assertSoftDeleted('receipts', ['id' => $receipt->id]);
        $this->assertSame(0, $this->refundCredits($invoice)->count());
        $this->assertSame(0, FinanceTransaction::where('source_type', \App\Models\InvoicePayment::class)
            ->where('status', 'active')->where('patient_id', $invoice->patient_id)->count());
    }

    public function test_legacy_cancel_route_is_gone(): void
    {
        $user = $this->admin();
        $invoice = $this->invoice();
        $receipt = $this->pay($user, $invoice, 500);

        $this->actingAs($user)->post('/billing/' . $invoice->id . '/cancel')->assertNotFound();

        $this->assertNotSame('cancelled', $invoice->fresh()->status);
        $this->assertNotSoftDeleted('receipts', ['id' => $receipt->id]);
    }

    public function test_delete_refuses_an_invoice_that_has_payments(): void
    {
        $user = $this->admin();
        $invoice = $this->invoice();
        $receipt = $this->pay($user, $invoice, 500); // part-paid

        $this->actingAs($user)->post(route('billing.deleteAuth', $invoice), [
            'reason' => 'Wrong patient', 'password' => 'password',
        ])->assertRedirect();

        $this->assertNotSoftDeleted('invoices', ['id' => $invoice->id]);
        $this->assertNotSoftDeleted('receipts', ['id' => $receipt->id]);
    }
}
