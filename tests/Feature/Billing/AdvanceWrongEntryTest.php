<?php

namespace Tests\Feature\Billing;

use App\Models\Finance\FinanceTransaction;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\Receipt;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Billing\AdvanceReversalService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * An advance entered by mistake.
 *
 * The operator does one thing: marks it a wrong entry. Underneath, all three
 * records receiveAdvance() wrote must come undone together — wallet credit,
 * cashbook entry and receipt — or the clinic ends up holding money on paper
 * that is not in the drawer.
 */
class AdvanceWrongEntryTest extends TestCase
{
    use RefreshDatabase;

    private function patient(string $name = 'Wrong Entry Patient'): Patient
    {
        return Patient::create([
            'name'      => $name,
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ]);
    }

    private function service(): AdvanceReversalService
    {
        return app(AdvanceReversalService::class);
    }

    private function takeAdvance(Patient $patient, float $amount = 500): WalletTransaction
    {
        return app(WalletService::class)->receiveAdvance(
            patient:     $patient,
            amount:      $amount,
            paymentMode: 'cash',
            paymentDate: now()->toDateString(),
            createdBy:   User::factory()->create()->id,
        );
    }

    public function test_A_a_wrong_advance_clears_the_wallet_the_cashbook_and_the_receipt(): void
    {
        $patient = $this->patient();
        $staff   = User::factory()->create();
        $advance = $this->takeAdvance($patient, 500);

        $result = $this->service()->reverse($advance, 'Entered on the wrong patient', $staff->id);

        $this->assertSame(500.0, $result['reversed']);

        // 1. Wallet is clear, and the original credit is still on record.
        $this->assertSame(0.0, (float) Wallet::forPatient($patient->id)->fresh()->balance_patient_credit);
        $this->assertDatabaseHas('wallet_transactions', ['id' => $advance->id, 'direction' => 'credit']);
        $this->assertSame($advance->id, $result['debit']->reversal_of_transaction_id);

        // 2. Cashbook entry voided, not deleted.
        $this->assertDatabaseHas('finance_transactions', [
            'source_type' => WalletTransaction::class,
            'source_id'   => $advance->id,
            'status'      => 'voided',
        ]);

        // 3. Receipt voided, number preserved.
        $receipt = $result['receipt'];
        $this->assertNotNull($receipt);
        $this->assertNotNull($receipt->fresh()->voided_at);
        $this->assertSame('not_received', $receipt->fresh()->void_correction_type);
        $this->assertDatabaseHas('receipts', ['id' => $receipt->id]);
    }

    public function test_B_no_refund_and_no_expense_is_ever_written(): void
    {
        // A refund would record cash leaving the drawer. None ever entered it.
        $patient = $this->patient();
        $advance = $this->takeAdvance($patient, 500);

        $this->service()->reverse($advance, 'Duplicate entry', User::factory()->create()->id);

        $this->assertSame(0, FinanceTransaction::where('patient_id', $patient->id)
            ->whereIn('type', ['refund', 'expense'])->count());
        $this->assertSame(0, WalletTransaction::where('patient_id', $patient->id)
            ->where('source', 'refund')->count());
    }

    public function test_C_it_cannot_be_done_twice(): void
    {
        $patient = $this->patient();
        $advance = $this->takeAdvance($patient, 500);

        $staff = User::factory()->create();
        $this->service()->reverse($advance, 'Duplicate entry', $staff->id);

        $this->expectException(ValidationException::class);
        $this->service()->reverse($advance->fresh(), 'Trying again', $staff->id);
    }

    public function test_D_an_advance_already_spent_on_an_invoice_is_refused(): void
    {
        // Half-undoing it would leave the wallet and the invoice disagreeing.
        $patient = $this->patient();
        $advance = $this->takeAdvance($patient, 500);

        $invoice = Invoice::create([
            'invoice_number' => Invoice::nextNumber(),
            'patient_id'     => $patient->id,
            'invoice_date'   => today()->toDateString(),
            'status'         => 'draft',
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'description' => 'Treatment',
            'unit_price' => 400, 'qty' => 1, 'net_amount' => 400,
            'gst_pct' => 0, 'gst_amount' => 0, 'total' => 400,
        ]);
        $invoice->recalculate();

        app(WalletService::class)->settleInvoiceFromCredit(
            invoice: $invoice->fresh(),
            amount:  400,
        );

        $this->expectException(ValidationException::class);
        $this->service()->reverse($advance->fresh(), 'Too late', User::factory()->create()->id);
    }

    public function test_F_a_reversed_advance_stops_counting_as_money_collected(): void
    {
        // The ledger keeps the row; the reports must not count it. Otherwise
        // the daily collection figure claims cash that is not in the drawer.
        $patient = $this->patient();
        $advance = $this->takeAdvance($patient, 500);

        $counted = fn () => (float) WalletTransaction::where('source', 'advance')
            ->where('direction', 'credit')
            ->notReversed()
            ->sum('amount');

        $this->assertSame(500.0, $counted());

        $this->service()->reverse($advance, 'Never received', User::factory()->create()->id);

        $this->assertSame(0.0, $counted(), 'A wrong entry must drop out of collections.');
        // ...but the row itself is still on the ledger.
        $this->assertDatabaseHas('wallet_transactions', ['id' => $advance->id, 'direction' => 'credit']);
    }

    public function test_E_only_an_advance_row_can_be_marked_a_wrong_entry(): void
    {
        $patient = $this->patient();

        $gift = app(WalletService::class)->credit(
            patientId: $patient->id, amount: 500, creditType: 'permanent',
            funding: WalletService::FUNDING_CLINIC,
        );

        $this->expectException(ValidationException::class);
        $this->service()->reverse($gift, 'Not an advance', User::factory()->create()->id);
    }
}
