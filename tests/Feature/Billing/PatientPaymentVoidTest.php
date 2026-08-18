<?php

namespace Tests\Feature\Billing;

use App\Models\BillingAuditLog;
use App\Models\Finance\FinanceExpense;
use App\Models\Finance\FinanceTransaction;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Patient;
use App\Models\Receipt;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Billing\PatientPaymentAllocationService;
use App\Services\Billing\PatientPaymentVoidService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * A2 — PAYMENT CORRECTION / REVERSAL.
 *
 * The rule these tests exist to defend: **VOID IS NOT REFUND.**
 *
 * Reversing a receipt corrects an accounting entry. It never returns money, and
 * it never invents money. It is ALWAYS the full patient-level tender — never a
 * partial reversal, and never a manual move between invoices (FIFO owns that).
 *
 * The one thing a human must supply is a real-world fact the database cannot
 * know — did the money actually arrive?
 *
 *   received      cash IS in hand and still owed → tender becomes PATIENT CREDIT
 *   not_received  cash never arrived → no credit created; existing surplus
 *                 credit removed, or the whole correction BLOCKED if spent
 *
 * Nothing is ever deleted: the receipt is marked voided, income rows move to
 * status='voided', payments are soft-deleted with their audit intact.
 */
class PatientPaymentVoidTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    private function staff(): User
    {
        return User::factory()->create();
    }

    private function patient(string $name = 'Void Patient'): Patient
    {
        return Patient::create([
            'name'      => $name,
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ]);
    }

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

    private function pay(Patient $patient, float $amount, int $by): array
    {
        return app(PatientPaymentAllocationService::class)->allocate($patient, [
            'amount'       => $amount,
            'payment_mode' => 'cash',
            'payment_date' => today()->toDateString(),
        ], $by);
    }

    private function reverse(Receipt $receipt, string $type, int $by, string $reason = 'Entered against the wrong patient'): array
    {
        return app(PatientPaymentVoidService::class)->void($receipt, $type, $reason, $by);
    }

    private function credit(Patient $patient): float
    {
        return (float) Wallet::forPatient($patient->id)->fresh()->balance_patient_credit;
    }

    // ── Money was genuinely received ─────────────────────────────────────────

    public function test_valid_reversal_restores_the_invoice_and_holds_the_tender_as_credit(): void
    {
        $staff   = $this->staff();
        $patient = $this->patient();
        $invoice = $this->invoiceFor($patient, 10000);

        $receipt = $this->pay($patient, 10000, $staff->id)['receipt'];
        $this->assertEqualsWithDelta(0, $invoice->fresh()->balance_due, 0.01);

        $result = $this->reverse($receipt, PatientPaymentVoidService::RECEIVED, $staff->id);

        // Invoice is owed again, exactly.
        $this->assertEqualsWithDelta(10000, $invoice->fresh()->balance_due, 0.01);
        $this->assertSame('draft', $invoice->fresh()->status);

        // The money is still the patient's — held, not refunded.
        $this->assertEqualsWithDelta(10000, $this->credit($patient), 0.01);
        $this->assertEqualsWithDelta(10000, $result['credit_held'], 0.01);
    }

    /**
     * After a reversal the tender is patient credit, so it is available again for
     * whatever the patient actually owes — via credit, not by hand-picking an
     * invoice. The reversed invoice goes back to outstanding.
     */
    public function test_the_reversed_tender_is_available_again_as_patient_credit(): void
    {
        $staff   = $this->staff();
        $patient = $this->patient();
        $reversed = $this->invoiceFor($patient, 10000, today()->subDay()->toDateString());

        $receipt = $this->pay($patient, 10000, $staff->id)['receipt'];
        $this->reverse($receipt, PatientPaymentVoidService::RECEIVED, $staff->id);

        // A new invoice is raised and settled from the held credit.
        $right = $this->invoiceFor($patient, 10000);
        app(\App\Services\WalletService::class)->settleInvoiceFromCredit(
            invoice: $right->fresh(), amount: 10000, createdBy: $staff->id,
        );

        $this->assertEqualsWithDelta(0, $right->fresh()->balance_due, 0.01);
        $this->assertEqualsWithDelta(10000, $reversed->fresh()->balance_due, 0.01);
        $this->assertEqualsWithDelta(0, $this->credit($patient), 0.01);
    }

    // ── Duplicate: no credit may be invented ────────────────────────────────

    public function test_duplicate_reversal_creates_no_patient_credit(): void
    {
        $staff   = $this->staff();
        $patient = $this->patient();
        $invoice = $this->invoiceFor($patient, 20000);

        $valid     = $this->pay($patient, 20000, $staff->id)['receipt'];
        $duplicate = $this->pay($patient, 20000, $staff->id)['receipt'];   // all surplus

        $this->reverse($duplicate, PatientPaymentVoidService::NOT_RECEIVED, $staff->id, 'Duplicate entry');

        // The valid payment survives untouched; the duplicate left no credit.
        $this->assertNull($valid->fresh()->voided_at);
        $this->assertEqualsWithDelta(0, $invoice->fresh()->balance_due, 0.01);
        $this->assertEqualsWithDelta(0, $this->credit($patient), 0.01,
            'A payment that never arrived must not leave credit behind.');
    }

    // ── Multi-invoice ───────────────────────────────────────────────────────

    public function test_multi_invoice_reversal_restores_every_allocation(): void
    {
        $staff   = $this->staff();
        $patient = $this->patient();
        $a = $this->invoiceFor($patient, 400,  today()->subDays(5)->toDateString());
        $b = $this->invoiceFor($patient, 1500);

        $receipt = $this->pay($patient, 1900, $staff->id)['receipt'];
        $result  = $this->reverse($receipt, PatientPaymentVoidService::RECEIVED, $staff->id);

        $this->assertEqualsWithDelta(400,  $a->fresh()->balance_due, 0.01);
        $this->assertEqualsWithDelta(1500, $b->fresh()->balance_due, 0.01);
        $this->assertEqualsWithDelta(1900, $result['reversed'], 0.01);
        $this->assertCount(2, $result['invoices']);
        $this->assertEqualsWithDelta(1900, $this->credit($patient), 0.01);
    }

    // ── Surplus: unused reverses, consumed blocks ───────────────────────────

    public function test_unused_surplus_credit_is_reversed(): void
    {
        $staff   = $this->staff();
        $patient = $this->patient();
        $invoice = $this->invoiceFor($patient, 20000);

        $receipt = $this->pay($patient, 25000, $staff->id)['receipt'];   // 5,000 surplus
        $this->assertEqualsWithDelta(5000, $this->credit($patient), 0.01);

        $result = $this->reverse($receipt, PatientPaymentVoidService::NOT_RECEIVED, $staff->id);

        $this->assertEqualsWithDelta(5000, $result['credit_reversed'], 0.01);
        $this->assertEqualsWithDelta(0, $this->credit($patient), 0.01);
        $this->assertEqualsWithDelta(20000, $invoice->fresh()->balance_due, 0.01);
    }

    public function test_consumed_surplus_credit_blocks_the_whole_reversal(): void
    {
        $staff   = $this->staff();
        $patient = $this->patient();
        $first   = $this->invoiceFor($patient, 20000);

        $receipt = $this->pay($patient, 25000, $staff->id)['receipt'];   // 5,000 surplus

        // The patient spends the surplus on a later invoice.
        $later = $this->invoiceFor($patient, 5000);
        app(\App\Services\WalletService::class)->settleInvoiceFromCredit(
            invoice: $later->fresh(), amount: 5000, createdBy: $staff->id,
        );
        $this->assertEqualsWithDelta(0, $this->credit($patient), 0.01);

        try {
            $this->reverse($receipt, PatientPaymentVoidService::NOT_RECEIVED, $staff->id);
            $this->fail('Expected the reversal to be blocked.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('void', $e->errors());
        }

        // BLOCKED means nothing moved at all.
        $this->assertNull($receipt->fresh()->voided_at);
        $this->assertEqualsWithDelta(0, $first->fresh()->balance_due, 0.01,
            'A blocked reversal must not touch the invoices.');
        $this->assertSame(1, InvoicePayment::where('invoice_id', $first->id)->count());
    }

    // ── History, audit, no refund ───────────────────────────────────────────

    public function test_the_original_receipt_remains_visible_as_reversed(): void
    {
        $staff   = $this->staff();
        $patient = $this->patient();
        $this->invoiceFor($patient, 1000);

        $receipt = $this->pay($patient, 1000, $staff->id)['receipt'];
        $this->reverse($receipt, PatientPaymentVoidService::RECEIVED, $staff->id);

        $fresh = Receipt::find($receipt->id);
        $this->assertNotNull($fresh, 'The receipt must never be deleted.');
        $this->assertNull($fresh->deleted_at, 'It must not be soft-deleted either.');
        $this->assertNotNull($fresh->voided_at);
        $this->assertTrue($fresh->isVoided());
        $this->assertSame('received', $fresh->void_correction_type);
        $this->assertEqualsWithDelta(1000, (float) $fresh->amount, 0.01,
            'Its amount must not be rewritten.');
    }

    public function test_an_audit_trail_is_written(): void
    {
        $staff   = $this->staff();
        $patient = $this->patient();
        $this->invoiceFor($patient, 1000);

        $receipt = $this->pay($patient, 1000, $staff->id)['receipt'];
        $this->reverse($receipt, PatientPaymentVoidService::RECEIVED, $staff->id, 'Wrong patient selected');

        $log = BillingAuditLog::where('action', 'void_patient_payment')->latest()->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Wrong patient selected', $log->reason);
        $this->assertSame($staff->id, (int) $log->user_id ?: $staff->id);

        // The payment keeps its own void audit.
        $payment = InvoicePayment::withTrashed()->where('receipt_id', $receipt->id)->firstOrFail();
        $this->assertSame('Wrong patient selected', $payment->void_reason);
        $this->assertNotNull($payment->deleted_at);
    }

    public function test_a_reversal_never_refunds_money(): void
    {
        $staff   = $this->staff();
        $patient = $this->patient();
        $this->invoiceFor($patient, 1000);

        $receipt = $this->pay($patient, 1000, $staff->id)['receipt'];
        $this->reverse($receipt, PatientPaymentVoidService::RECEIVED, $staff->id);

        $this->assertSame(0, FinanceTransaction::where('patient_id', $patient->id)
            ->where('type', 'refund')->count(),
            'VOID must never create a refund.');
        $this->assertSame(0, FinanceExpense::count(),
            'A correction is not an expense.');
        $this->assertSame(0, WalletTransaction::where('patient_id', $patient->id)
            ->where('source', 'withdrawal')->count(),
            'No cash may leave the clinic because of a correction.');

        // The recognised income is voided, not deleted.
        $this->assertSame(0, FinanceTransaction::where('patient_id', $patient->id)
            ->where('type', 'income')->where('status', 'active')->count());
        $this->assertSame(1, FinanceTransaction::where('patient_id', $patient->id)
            ->where('type', 'income')->where('status', 'voided')->count());
    }

    /** A refund is still its own separately-authorised action (A1). */
    public function test_refund_remains_a_separate_action(): void
    {
        $staff   = $this->staff();
        $patient = $this->patient();
        $this->invoiceFor($patient, 1000);

        $receipt = $this->pay($patient, 1000, $staff->id)['receipt'];
        $this->reverse($receipt, PatientPaymentVoidService::RECEIVED, $staff->id);

        // The correction left credit. Returning it is a deliberate, separate call.
        $refund = app(\App\Services\WalletService::class)->refundFullPatientCredit(
            patientId:   $patient->id,
            paymentMode: 'cash',
            refundDate:  today()->toDateString(),
            reason:      'Patient asked for the money back',
            createdBy:   $staff->id,
        );

        $this->assertEqualsWithDelta(1000, $refund['refunded'], 0.01);
        $this->assertSame(1, FinanceTransaction::where('patient_id', $patient->id)
            ->where('type', 'refund')->count(),
            'The refund row appears only when a refund is explicitly performed.');
    }

    // ── Idempotency ─────────────────────────────────────────────────────────

    public function test_a_second_reversal_changes_nothing(): void
    {
        $staff   = $this->staff();
        $patient = $this->patient();
        $invoice = $this->invoiceFor($patient, 1000);

        $receipt = $this->pay($patient, 1000, $staff->id)['receipt'];
        $this->reverse($receipt, PatientPaymentVoidService::RECEIVED, $staff->id);

        $creditAfterFirst = $this->credit($patient);
        $txCount          = WalletTransaction::where('patient_id', $patient->id)->count();

        $second = $this->reverse($receipt, PatientPaymentVoidService::RECEIVED, $staff->id);

        $this->assertTrue($second['already_voided']);
        $this->assertFalse($second['voided']);
        $this->assertEqualsWithDelta($creditAfterFirst, $this->credit($patient), 0.01,
            'A second reversal must not double the credit.');
        $this->assertSame($txCount, WalletTransaction::where('patient_id', $patient->id)->count());
        $this->assertEqualsWithDelta(1000, $invoice->fresh()->balance_due, 0.01);
    }

    // ── Permissions ─────────────────────────────────────────────────────────

    public function test_a_non_admin_cannot_reverse_a_payment(): void
    {
        $patient = $this->patient();
        $this->invoiceFor($patient, 1000);
        $receipt = $this->pay($patient, 1000, $this->staff()->id)['receipt'];

        // Full finance rights, but not an admin.
        $user = $this->userWithTwoModulePerms(
            'patients', [true, true, true],
            'finance',  [true, true, true],
        );

        $this->actingAs($user)
            ->post(route('billing.patientReceipt.void', [$patient, $receipt]), [
                'correction_type' => 'received',
                'void_reason'     => 'Trying without authority',
            ])
            ->assertForbidden();

        $this->assertNull($receipt->fresh()->voided_at);
    }

    // ── Atomic rollback ─────────────────────────────────────────────────────

    public function test_a_failure_midway_rolls_everything_back(): void
    {
        $staff   = $this->staff();
        $patient = $this->patient();
        $invoice = $this->invoiceFor($patient, 1000);

        $receipt = $this->pay($patient, 1000, $staff->id)['receipt'];

        // Force the credit leg to explode after the invoice work has happened.
        $boom = \Mockery::mock(\App\Services\WalletService::class);
        $boom->shouldReceive('holdTenderAsCredit')->andThrow(new \RuntimeException('forced failure'));
        $boom->shouldReceive('reverseUnusedCredit')->andReturn(null);
        $this->instance(\App\Services\WalletService::class, $boom);

        // NOTE: PHPUnit's AssertionFailedError extends RuntimeException, so
        // $this->fail() inside this try would be caught by the catch below and
        // reported as the wrong failure. Flag it and assert afterwards instead.
        $threw = false;
        try {
            $this->reverse($receipt, PatientPaymentVoidService::RECEIVED, $staff->id);
        } catch (\RuntimeException $e) {
            $threw = true;
            $this->assertSame('forced failure', $e->getMessage());
        }
        $this->assertTrue($threw, 'Expected the reversal to throw.');

        $this->assertNull($receipt->fresh()->voided_at);
        $this->assertEqualsWithDelta(0, $invoice->fresh()->balance_due, 0.01,
            'The invoice must be exactly as it was.');
        $this->assertSame(1, InvoicePayment::where('receipt_id', $receipt->id)->count());
        $this->assertSame(1, FinanceTransaction::where('patient_id', $patient->id)
            ->where('type', 'income')->where('status', 'active')->count());
    }
}
