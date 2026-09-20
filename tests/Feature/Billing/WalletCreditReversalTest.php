<?php

namespace Tests\Feature\Billing;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Reversing a wallet credit that was added by mistake.
 *
 * THE RULE BEING LOCKED: there is no delete. A mistaken credit is cancelled by
 * a linked debit; both rows survive, the reason survives, and anything that has
 * already been spent cannot be quietly erased.
 */
class WalletCreditReversalTest extends TestCase
{
    use RefreshDatabase;

    private function patient(string $name = 'Reversal Patient'): Patient
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

    /** A plain invoice for $amount — debit() requires a real one to bill against. */
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

    /** Spend $amount of promotional credit against a fresh invoice. */
    private function spendPromo(Patient $patient, float $amount): void
    {
        $this->wallet()->debit(
            patientId: $patient->id,
            amount:    $amount,
            invoiceId: $this->invoiceFor($patient, $amount)->id,
        );
    }

    public function test_A_a_mistaken_clinic_credit_is_cancelled_by_a_linked_debit_not_a_delete(): void
    {
        $patient = $this->patient();
        $staff   = User::factory()->create();

        $credit = $this->wallet()->credit(
            patientId:  $patient->id,
            amount:     2000,
            creditType: 'permanent',
            notes:      'Goodwill',
            createdBy:  $staff->id,
            funding:    WalletService::FUNDING_CLINIC,
        );

        $debit = $this->wallet()->reverseCreditEntry($credit, 'Entered on the wrong patient', $staff->id);

        // The original is still there. Nothing was deleted.
        $this->assertDatabaseHas('wallet_transactions', ['id' => $credit->id, 'direction' => 'credit']);

        $this->assertSame('debit', $debit->direction);
        $this->assertSame('credit_reversal', $debit->source);
        $this->assertSame($credit->id, $debit->reversal_of_transaction_id);
        $this->assertSame(2000.0, (float) $debit->amount);
        $this->assertStringContainsString('Entered on the wrong patient', $debit->notes);

        $this->assertSame(0.0, (float) Wallet::forPatient($patient->id)->fresh()->balance_permanent);
        $this->assertTrue($credit->fresh()->isReversed());
    }

    public function test_B_the_same_credit_cannot_be_reversed_twice(): void
    {
        $patient = $this->patient();
        $credit  = $this->wallet()->credit(
            patientId: $patient->id, amount: 500, creditType: 'permanent',
            funding: WalletService::FUNDING_CLINIC,
        );

        $this->wallet()->reverseCreditEntry($credit, 'Duplicate entry');

        $this->expectException(ValidationException::class);
        $this->wallet()->reverseCreditEntry($credit->fresh(), 'Trying again');
    }

    public function test_C_a_promotional_credit_already_spent_cannot_be_reversed(): void
    {
        $patient = $this->patient();

        $credit = $this->wallet()->credit(
            patientId:  $patient->id,
            amount:     1000,
            creditType: 'promotional',
            expiryDate: now()->addMonth()->toDateString(),
            funding:    WalletService::FUNDING_CLINIC,
        );

        // Spend it against an invoice.
        $this->spendPromo($patient, 1000);

        $this->expectException(ValidationException::class);
        $this->wallet()->reverseCreditEntry($credit->fresh(), 'Too late');
    }

    public function test_D_a_spent_lot_cannot_hide_behind_another_patients_unspent_credit(): void
    {
        // The wallet-wide-balance trap: this wallet still holds 5,000 of OTHER
        // promotional credit, so a naive balance check would wave the reversal
        // of the already-spent 2,000 lot straight through.
        $patient = $this->patient();

        $spent = $this->wallet()->credit(
            patientId: $patient->id, amount: 2000, creditType: 'promotional',
            expiryDate: now()->addMonth()->toDateString(), funding: WalletService::FUNDING_CLINIC,
        );
        $this->spendPromo($patient, 2000);

        $this->wallet()->credit(
            patientId: $patient->id, amount: 5000, creditType: 'promotional',
            expiryDate: now()->addMonths(2)->toDateString(), funding: WalletService::FUNDING_CLINIC,
        );

        $this->expectException(ValidationException::class);
        $this->wallet()->reverseCreditEntry($spent->fresh(), 'Should be refused');
    }

    public function test_E_patient_cash_credit_is_never_reversible_it_must_be_refunded(): void
    {
        $patient = $this->patient();

        $this->wallet()->receiveAdvance(
            patient:     $patient,
            amount:      3000,
            paymentMode: 'cash',
            paymentDate: now()->toDateString(),
            // receiveAdvance() writes a BillingAuditLog row, whose user id is
            // typed int — it cannot be called without a user.
            createdBy:   User::factory()->create()->id,
            createReceipt: false,
        );

        $advance = WalletTransaction::where('patient_id', $patient->id)
            ->where('direction', 'credit')
            ->where('funding', WalletService::FUNDING_PATIENT)
            ->firstOrFail();

        $this->assertFalse($advance->isReversible());

        $this->expectException(ValidationException::class);
        $this->wallet()->reverseCreditEntry($advance, 'Should be refused');
    }

    public function test_F2_an_expired_promotional_credit_is_not_reversible(): void
    {
        // Expiry already removed it from the balance. Reversing it now would
        // post a debit against money the wallet no longer holds.
        $patient = $this->patient();

        $credit = $this->wallet()->credit(
            patientId: $patient->id, amount: 1000, creditType: 'promotional',
            expiryDate: now()->addDay()->toDateString(), funding: WalletService::FUNDING_CLINIC,
        );

        // Age it past its expiry.
        $credit->forceFill(['expiry_date' => now()->subDay()->toDateString()])->save();

        $this->assertFalse($credit->fresh()->isReversible());

        $this->expectException(ValidationException::class);
        $this->wallet()->reverseCreditEntry($credit->fresh(), 'Should be refused');
    }

    public function test_E2_an_advance_links_to_its_receipt_so_the_ledger_can_point_at_it(): void
    {
        // A mistaken advance is undone by voiding its ADV- receipt, which
        // reverses the wallet credit, the cashbook entry and the receipt
        // together. The ledger can only send someone there if the link exists.
        $patient = $this->patient();

        $tx = $this->wallet()->receiveAdvance(
            patient:     $patient,
            amount:      3000,
            paymentMode: 'cash',
            paymentDate: now()->toDateString(),
            createdBy:   User::factory()->create()->id,
        );

        $receipt = $tx->fresh()->advanceReceipt;

        $this->assertNotNull($receipt, 'The advance receipt must be linked to its wallet credit.');
        $this->assertSame('advance', $receipt->receipt_kind);
        $this->assertSame(3000.0, (float) $receipt->amount);
        $this->assertNull($receipt->invoice_id);
    }

    public function test_F_an_invoice_debit_row_is_not_reversible_from_the_ledger(): void
    {
        $patient = $this->patient();
        $this->wallet()->credit(
            patientId: $patient->id, amount: 1000, creditType: 'promotional',
            expiryDate: now()->addMonth()->toDateString(), funding: WalletService::FUNDING_CLINIC,
        );
        $this->spendPromo($patient, 400);

        $debit = WalletTransaction::where('patient_id', $patient->id)
            ->where('direction', 'debit')->firstOrFail();

        $this->assertFalse($debit->isReversible());
    }
}
