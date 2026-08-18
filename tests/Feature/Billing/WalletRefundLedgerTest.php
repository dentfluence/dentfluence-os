<?php

namespace Tests\Feature\Billing;

use App\Models\Finance\FinanceExpense;
use App\Models\Finance\FinanceTransaction;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\Receipt;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * A1 read-path — a wallet refund must be VISIBLE on the patient's Billing Ledger.
 *
 * The accounting was already right; the patient's account simply didn't show it.
 * The ledger walked invoices and receipts only, so money going back OUT of the
 * wallet left no trace on the page staff actually look at.
 *
 * Source of truth is the WalletTransaction the refund service already wrote
 * (direction=debit, source='withdrawal'). Nothing is created for display, and
 * receipt VOIDS — which share FinanceTransaction type='refund' — are excluded so
 * one refund can only ever produce one debit.
 *
 *   Advance   50,500 → ledger CREDIT  (clinic holds the patient's money)
 *   Refund    50,500 → ledger DEBIT   (clinic gave it back)
 *   Balance        0
 */
class WalletRefundLedgerTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    /** Billing tab needs patients:view (route group) AND finance:view (tab guard). */
    private function staffUser(): User
    {
        return $this->userWithTwoModulePerms(
            'patients', [true, true, true],
            'finance',  [true, true, true],
        );
    }

    private function patient(string $name = 'Refund Ledger Patient'): Patient
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

    private function advance(Patient $patient, float $amount, int $by): void
    {
        $this->wallet()->receiveAdvance(
            patient:     $patient,
            amount:      $amount,
            paymentMode: 'cash',
            paymentDate: today()->toDateString(),
            createdBy:   $by,
        );
    }

    private function refund(Patient $patient, int $by): array
    {
        return $this->wallet()->refundFullPatientCredit(
            patientId:   $patient->id,
            paymentMode: 'cash',
            refundDate:  today()->toDateString(),
            reason:      'Treatment cancelled',
            createdBy:   $by,
        );
    }

    private function ledger(Patient $patient, User $user)
    {
        return $this->actingAs($user)->get(route('patients.tab', [$patient, 'billing']));
    }

    private function reference(WalletTransaction $tx): string
    {
        return 'REF-' . str_pad((string) $tx->id, 6, '0', STR_PAD_LEFT);
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

    // ── A. The refund shows as a ledger DEBIT ────────────────────────────────

    public function test_A_refund_appears_in_the_billing_ledger_as_a_debit(): void
    {
        $user    = $this->staffUser();
        $patient = $this->patient();

        $this->advance($patient, 50500, $user->id);
        $result = $this->refund($patient, $user->id);
        $ref    = $this->reference($result['transaction']);

        $res = $this->ledger($patient, $user)->assertOk();

        $res->assertSee($ref, false);

        // 50,500 credited by the advance, 50,500 debited by the refund -> nil.
        // If the refund were missing (the bug) the balance would read 50,500 CR.
        $res->assertSee('NIL', false);
    }

    // ── B. The refund document is reachable from the ledger ──────────────────

    public function test_B_refund_reference_links_to_its_document(): void
    {
        $user    = $this->staffUser();
        $patient = $this->patient();

        $this->advance($patient, 50500, $user->id);
        $tx = $this->refund($patient, $user->id)['transaction'];

        $this->ledger($patient, $user)
            ->assertOk()
            ->assertSee(route('finance.wallets.credit-note', [$patient, $tx]), false);
    }

    /** The document itself renders as a Refund Voucher, not a receipt. */
    public function test_B2_the_refund_document_renders(): void
    {
        $user    = $this->staffUser();
        $patient = $this->patient();

        $this->advance($patient, 50500, $user->id);
        $tx = $this->refund($patient, $user->id)['transaction'];

        $this->actingAs($user)
            ->get(route('finance.wallets.credit-note', [$patient, $tx]))
            ->assertOk()
            ->assertSee('Refund Voucher', false)
            ->assertSee($this->reference($tx), false);
    }

    // ── C. Exactly once ──────────────────────────────────────────────────────

    public function test_C_the_refund_appears_exactly_once(): void
    {
        $user    = $this->staffUser();
        $patient = $this->patient();

        $this->advance($patient, 50500, $user->id);
        $tx = $this->refund($patient, $user->id)['transaction'];

        $content = $this->ledger($patient, $user)->assertOk()->getContent();

        $this->assertSame(1, substr_count($content, $this->reference($tx)),
            'One refund must produce exactly one ledger row.');
        $this->assertSame(1, WalletTransaction::where('patient_id', $patient->id)
            ->where('direction', 'debit')->count());
    }

    // ── D. No revenue ────────────────────────────────────────────────────────

    public function test_D_refund_does_not_increase_income(): void
    {
        $user    = $this->staffUser();
        $patient = $this->patient();

        $this->advance($patient, 50500, $user->id);
        $this->refund($patient, $user->id);

        $this->assertSame(0, FinanceTransaction::where('patient_id', $patient->id)
            ->where('type', 'income')->count());
    }

    // ── E. No expense ────────────────────────────────────────────────────────

    public function test_E_refund_does_not_create_an_expense(): void
    {
        $user    = $this->staffUser();
        $patient = $this->patient();

        $this->advance($patient, 50500, $user->id);
        $this->refund($patient, $user->id);

        $this->assertSame(0, FinanceExpense::count());
        $this->assertSame(1, FinanceTransaction::where('patient_id', $patient->id)
            ->where('type', 'refund')->count());
    }

    // ── F. Patient credit is reduced ─────────────────────────────────────────

    public function test_F_refund_reduces_patient_credit_to_zero(): void
    {
        $user    = $this->staffUser();
        $patient = $this->patient();

        $this->advance($patient, 50500, $user->id);
        $this->refund($patient, $user->id);

        $this->assertEqualsWithDelta(0,
            Wallet::forPatient($patient->id)->fresh()->balance_patient_credit, 0.01);
    }

    // ── G. No second refund ──────────────────────────────────────────────────

    public function test_G_a_second_refund_is_refused_and_adds_no_ledger_row(): void
    {
        $user    = $this->staffUser();
        $patient = $this->patient();

        $this->advance($patient, 50500, $user->id);
        $tx = $this->refund($patient, $user->id)['transaction'];

        try {
            $this->refund($patient, $user->id);
            $this->fail('A second refund must be refused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('refund', $e->errors());
        }

        $content = $this->ledger($patient, $user)->assertOk()->getContent();

        $this->assertSame(1, substr_count($content, $this->reference($tx)));
        $this->assertEqualsWithDelta(50500, (float) FinanceTransaction::where('patient_id', $patient->id)
            ->where('type', 'refund')->sum('amount'), 0.01,
            'Total refunded must stay at 50,500 — never doubled.');
    }

    // ── H. Existing ledger behaviour is unchanged ────────────────────────────

    public function test_H_advance_and_invoice_payment_ledger_rows_are_unchanged(): void
    {
        $user    = $this->staffUser();
        $patient = $this->patient();

        // An advance still shows as an ADV- credit...
        $this->advance($patient, 2000, $user->id);
        $adv = Receipt::where('patient_id', $patient->id)
            ->where('receipt_kind', 'advance')->firstOrFail();

        // ...and a normal invoice payment still shows as an RCP- credit.
        $invoice = $this->invoiceFor($patient, 1000);
        $this->actingAs($user)->post(route('billing.payment', $invoice), [
            'amount'       => 1000,
            'payment_mode' => 'cash',
            'payment_date' => today()->toDateString(),
        ])->assertRedirect();

        $rcp = Receipt::where('patient_id', $patient->id)
            ->whereNotNull('invoice_id')->firstOrFail();

        $res = $this->ledger($patient, $user)->assertOk();
        $res->assertSee($adv->receipt_number, false);
        $res->assertSee($rcp->receipt_number, false);
        $res->assertSee(route('billing.receipt', [$invoice, $rcp]), false);

        // Invoice 1,000 debit − (2,000 advance + 1,000 payment) credit = 2,000 CR.
        $this->assertSame(0, WalletTransaction::where('patient_id', $patient->id)
            ->where('direction', 'debit')->count(),
            'Nothing in this flow may create a refund row.');
    }
}
