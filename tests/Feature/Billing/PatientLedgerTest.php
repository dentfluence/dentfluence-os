<?php

namespace Tests\Feature\Billing;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\Receipt;
use App\Models\User;
use App\Services\Billing\PatientPaymentAllocationService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * PATIENT BILLING LEDGER — read-path regression cover.
 *
 * The ledger used to find receipts ONLY by walking patient -> invoices ->
 * invoice.receipts. Patient-level receipts (PAY- from an allocated tender,
 * ADV- from an advance) carry invoice_id = NULL, so they were unreachable:
 * the credit column silently under-reported, while Collected/Outstanding —
 * which read invoice_payments — were right. The same page disagreed with
 * itself.
 *
 * The invariant these tests defend:
 *
 *   Ledger credit == every non-deleted Receipt for the patient,
 *   invoice-linked or not, each counted exactly once.
 */
class PatientLedgerTest extends TestCase
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

    private function patient(string $name = 'Ledger Patient'): Patient
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

    private function ledger(Patient $patient, User $user)
    {
        return $this->actingAs($user)->get(route('patients.tab', [$patient, 'billing']));
    }

    private function allocate(Patient $patient, float $amount, int $userId): array
    {
        return app(PatientPaymentAllocationService::class)->allocate($patient, [
            'amount'       => $amount,
            'payment_mode' => 'cash',
            'payment_date' => today()->toDateString(),
        ], $userId);
    }

    // ── A. Invoice-linked receipt appears once ───────────────────────────────

    public function test_A_invoice_linked_receipt_appears_once_and_balances(): void
    {
        $user    = $this->staffUser();
        $patient = $this->patient();
        $invoice = $this->invoiceFor($patient, 1000);

        $this->actingAs($user)->post(route('billing.payment', $invoice), [
            'amount'       => 1000,
            'payment_mode' => 'cash',
            'payment_date' => today()->toDateString(),
        ])->assertRedirect();

        $receipt = Receipt::where('patient_id', $patient->id)->firstOrFail();
        $this->assertStringStartsWith('RCP-', $receipt->receipt_number);

        $res = $this->ledger($patient, $user)->assertOk();

        $res->assertSee($receipt->receipt_number, false);

        // Exactly once in the ledger table and once in the Receipts list.
        $this->assertSame(2, substr_count($res->getContent(), $receipt->receipt_number),
            'An invoice-linked receipt must appear once in the ledger and once in the receipts list.');

        // debit 1,000 - credit 1,000 = 0
        $res->assertSee('NIL', false);
    }

    // ── B. PAY- receipt appears in the ledger ────────────────────────────────

    public function test_B_pay_receipt_appears_in_the_ledger(): void
    {
        $user    = $this->staffUser();
        $patient = $this->patient();
        $this->invoiceFor($patient, 1000, today()->subDays(10)->toDateString());
        $this->invoiceFor($patient, 1500);

        $result = $this->allocate($patient, 2500, $user->id);
        $pay    = $result['receipt'];
        $this->assertStringStartsWith('PAY-', $pay->receipt_number);

        $this->ledger($patient, $user)
            ->assertOk()
            ->assertSee($pay->receipt_number, false);
    }

    // ── C. ADV- receipt appears in the ledger ────────────────────────────────

    public function test_C_adv_receipt_appears_in_the_ledger(): void
    {
        $user    = $this->staffUser();
        $patient = $this->patient();
        $this->invoiceFor($patient, 1000);

        app(WalletService::class)->receiveAdvance(
            patient:     $patient,
            amount:      500,
            paymentMode: 'cash',
            paymentDate: today()->toDateString(),
            createdBy:   $user->id,
        );

        $adv = Receipt::where('patient_id', $patient->id)
            ->where('receipt_kind', 'advance')->firstOrFail();
        $this->assertStringStartsWith('ADV-', $adv->receipt_number);

        $this->ledger($patient, $user)
            ->assertOk()
            ->assertSee($adv->receipt_number, false)
            // The real stored number — never a synthetic one built from the
            // WalletTransaction id.
            ->assertDontSee('ADV-' . str_pad((string) $adv->id, 6, '0', STR_PAD_LEFT), false);
    }

    // ── D. PAY- is not double-counted via InvoicePayment + Receipt ───────────

    public function test_D_pay_receipt_is_not_double_counted(): void
    {
        $user    = $this->staffUser();
        $patient = $this->patient();
        $this->invoiceFor($patient, 1000, today()->subDays(10)->toDateString());
        $this->invoiceFor($patient, 1500);

        $this->allocate($patient, 2500, $user->id);

        $res = $this->ledger($patient, $user)->assertOk();

        // Debit 2,500, credit 2,500 -> NIL. If the tender were counted twice
        // (once per InvoicePayment and once for the receipt) the balance would
        // swing to 2,500 CR and render "ADV" instead.
        $res->assertSee('NIL', false);
        $res->assertDontSee('ADV', false);
    }

    // ── E. Balance reaches zero when everything is paid ──────────────────────

    public function test_E_ledger_balance_is_zero_when_invoices_fully_paid(): void
    {
        $user    = $this->staffUser();
        $patient = $this->patient();
        $this->invoiceFor($patient, 900, today()->subDays(3)->toDateString());
        $this->invoiceFor($patient, 1100);

        $result = $this->allocate($patient, 2000, $user->id);
        $this->assertEqualsWithDelta(0, $result['outstanding_after'], 0.01);

        $this->ledger($patient, $user)
            ->assertOk()
            ->assertSee('NIL', false);
    }

    // ── F. Receipts list contains PAY- ───────────────────────────────────────

    public function test_F_receipts_list_contains_the_pay_receipt(): void
    {
        $user    = $this->staffUser();
        $patient = $this->patient();
        $this->invoiceFor($patient, 800);

        $pay = $this->allocate($patient, 800, $user->id)['receipt'];

        $content = $this->ledger($patient, $user)->assertOk()->getContent();

        // Ledger row + receipts list row.
        $this->assertSame(2, substr_count($content, $pay->receipt_number),
            'The PAY- receipt must appear in both the ledger and the receipts list.');
    }

    // ── G. Receipts list contains ADV- ───────────────────────────────────────

    public function test_G_receipts_list_contains_the_adv_receipt(): void
    {
        $user    = $this->staffUser();
        $patient = $this->patient();
        $this->invoiceFor($patient, 600);

        app(WalletService::class)->receiveAdvance(
            patient:     $patient,
            amount:      300,
            paymentMode: 'upi',
            paymentDate: today()->toDateString(),
            createdBy:   $user->id,
        );

        $adv = Receipt::where('patient_id', $patient->id)
            ->where('receipt_kind', 'advance')->firstOrFail();

        $content = $this->ledger($patient, $user)->assertOk()->getContent();

        $this->assertSame(2, substr_count($content, $adv->receipt_number),
            'The ADV- receipt must appear in both the ledger and the receipts list.');
    }

    // ── H. Patient-level receipts use the patient-level route ───────────────

    public function test_H_patient_level_receipt_links_to_the_patient_route(): void
    {
        $user    = $this->staffUser();
        $patient = $this->patient();
        $this->invoiceFor($patient, 700);

        $pay = $this->allocate($patient, 700, $user->id)['receipt'];

        $res = $this->ledger($patient, $user)->assertOk();

        $res->assertSee(route('billing.patientReceipt', [$patient, $pay]), false);

        // It must NEVER be routed through an invoice — that route requires
        // $receipt->invoice and a PAY- receipt has none.
        $this->assertStringNotContainsString(
            '/receipt/' . $pay->id . '"',
            str_replace(route('billing.patientReceipt', [$patient, $pay]), '', $res->getContent()),
            'A patient-level receipt must not be linked through the invoice receipt route.'
        );
    }

    // ── I. Existing invoice-receipt routing is unchanged ─────────────────────

    public function test_I_invoice_receipt_routing_is_unchanged(): void
    {
        $user    = $this->staffUser();
        $patient = $this->patient();
        $invoice = $this->invoiceFor($patient, 1000);

        $this->actingAs($user)->post(route('billing.payment', $invoice), [
            'amount'       => 400,
            'payment_mode' => 'cash',
            'payment_date' => today()->toDateString(),
        ])->assertRedirect();

        $receipt = Receipt::where('patient_id', $patient->id)->firstOrFail();

        $this->ledger($patient, $user)
            ->assertOk()
            ->assertSee(route('billing.receipt', [$invoice, $receipt]), false);
    }
}
