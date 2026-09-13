<?php

namespace Tests\Feature\Finance;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * A-2 — patient credit is applied BY DEFAULT on the payment form.
 *
 * MEASURED ON PRODUCTION 2026-09-12: two patients held Rs 44,450 of their own
 * money while owing Rs 73,800 — 77% of the clinic's entire receivable was owed
 * by patients who had already paid. The machinery to spend that credit was
 * built and correct (WalletService::settleInvoiceFromCredit, and the HTTP path
 * is already covered by AdvanceAndPatientCreditTest case G/H). What was wrong
 * was the DEFAULT: the box on the form opened at 0, so credit was spent only
 * when reception remembered to spend it — and the side panel, the form she
 * actually opens from the patient profile, had no credit box at all.
 *
 * So these cases pin the default and the second screen. Both numbers are
 * rendered server-side, which is why they can be asserted on the HTML instead
 * of being left to JavaScript.
 *
 * 🪤 The third case is the load-bearing one: PROMOTIONAL credit must never be
 * auto-applied. It carries per-treatment eligibility (eligiblePromoBalance())
 * and it was never the patient's money — U8 rules 11 and 12.
 */
class WalletFirstPrefillTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    /** Billing routes sit behind module:finance; a bare factory user is denied. */
    private function frontDesk(): User
    {
        return $this->userWithModulePerm('finance', true, true, true);
    }

    private function patient(string $name): Patient
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

    /** Cash across the counter — funding 'patient', the refundable kind. */
    private function takeAdvance(Patient $patient, float $amount, User $staff): void
    {
        app(WalletService::class)->receiveAdvance(
            patient:     $patient,
            amount:      $amount,
            paymentMode: 'cash',
            paymentDate: today()->toDateString(),
            notes:       null,
            createdBy:   $staff->id,
        );
    }

    public function test_credit_is_prefilled_and_the_cash_box_drops_by_the_same_amount(): void
    {
        $staff   = $this->frontDesk();
        $patient = $this->patient('A2 Credit Holder');
        $invoice = $this->invoiceFor($patient, 8000.0);
        $this->takeAdvance($patient, 5000.0, $staff);

        $html = $this->actingAs($staff)
            ->get(route('billing.show', $invoice))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/name="wallet_used".{0,240}?value="5000"/s',
            $html,
            'the credit box must open carrying the credit, not 0'
        );
        $this->assertMatchesRegularExpression(
            '/id="pmtAmount".{0,240}?value="3000"/s',
            $html,
            'cash to collect must already be balance minus credit'
        );
    }

    public function test_the_prefill_never_exceeds_what_is_owed(): void
    {
        $staff   = $this->frontDesk();
        $patient = $this->patient('A2 Over-credited');
        $invoice = $this->invoiceFor($patient, 8000.0);
        $this->takeAdvance($patient, 20000.0, $staff);

        $html = $this->actingAs($staff)
            ->get(route('billing.show', $invoice))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/name="wallet_used".{0,240}?value="8000"/s',
            $html,
            'capped at balance_due, never at the full credit'
        );
        $this->assertMatchesRegularExpression(
            '/id="pmtAmount".{0,240}?value="0"/s',
            $html,
            'a bill covered entirely by credit has no cash leg'
        );
    }

    /** THE GUARD: promotional credit is the clinic's gift, not the patient's money. */
    public function test_promotional_credit_is_never_offered_or_prefilled(): void
    {
        $staff   = $this->frontDesk();
        $patient = $this->patient('A2 Promo Only');
        $invoice = $this->invoiceFor($patient, 8000.0);

        app(WalletService::class)->credit(
            patientId:  $patient->id,
            amount:     5000.0,
            creditType: 'promotional',
            expiryDate: '2027-01-01',
            notes:      'A-2 promotional',
            createdBy:  $staff->id,
        );

        $html = $this->actingAs($staff)
            ->get(route('billing.show', $invoice))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(
            'Use Patient Credit',
            $html,
            'a wallet holding only promotional credit must not offer to spend it'
        );
        $this->assertMatchesRegularExpression(
            '/id="pmtAmount".{0,240}?value="8000"/s',
            $html,
            'and the cash box must still ask for the whole bill'
        );
    }

    /** The side panel is the form reception opens from the patient profile. */
    public function test_the_side_panel_offers_the_same_credit(): void
    {
        $staff   = $this->frontDesk();
        $patient = $this->patient('A2 Panel');
        $invoice = $this->invoiceFor($patient, 8000.0);
        $this->takeAdvance($patient, 5000.0, $staff);

        $html = $this->actingAs($staff)
            ->get(route('billing.panel', $invoice))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Use Patient Credit', $html,
            'the panel could not apply credit at all before A-2');
        $this->assertMatchesRegularExpression(
            '/name="wallet_used".{0,240}?value="5000"/s',
            $html,
            'same default as the full page — the two screens must not disagree'
        );
    }
}
