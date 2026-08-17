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
use Tests\TestCase;

/**
 * PROMOTIONAL CREDIT EXPIRY — available balance must exclude lapsed credit.
 *
 * Wallet::recalculate() used to total every promotional row regardless of
 * expiry_date, so a lapsed campaign credit stayed in balance_promotional (and
 * therefore in balance_total, and therefore in every "spendable" cap that reads
 * it) forever. Spending already refused expired credit — only the number lied.
 *
 * The invariant:
 *
 *   balance_promotional == unexpired, unconsumed promotional credit
 *   ...and the expired rows stay in history, untouched.
 */
class PromotionalExpiryTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create();
    }

    private function patient(string $name = 'Promo Patient'): Patient
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

    private function promo(Patient $patient, float $amount, string $expiry): WalletTransaction
    {
        return $this->wallet()->credit(
            patientId:  $patient->id,
            amount:     $amount,
            creditType: 'promotional',
            expiryDate: $expiry,
            notes:      'Campaign',
            createdBy:  $this->staff()->id,
        );
    }

    private function permanent(Patient $patient, float $amount): WalletTransaction
    {
        return $this->wallet()->credit(
            patientId:  $patient->id,
            amount:     $amount,
            creditType: 'permanent',
            expiryDate: null,
            notes:      'Real credit',
            createdBy:  $this->staff()->id,
        );
    }

    private function freshWallet(Patient $patient): Wallet
    {
        return Wallet::forPatient($patient->id)->fresh();
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

    // ── A. Unexpired promotional credit is included ──────────────────────────

    public function test_A_unexpired_promotional_credit_is_available(): void
    {
        $patient = $this->patient();
        $this->promo($patient, 25000, today()->addMonths(4)->toDateString());

        $this->assertEqualsWithDelta(25000, $this->freshWallet($patient)->balance_promotional, 0.01);
    }

    /** A credit expiring TODAY is still spendable — same boundary as expiringCredits(). */
    public function test_A2_credit_expiring_today_is_still_available(): void
    {
        $patient = $this->patient();
        $this->promo($patient, 4000, today()->toDateString());

        $this->assertEqualsWithDelta(4000, $this->freshWallet($patient)->balance_promotional, 0.01,
            'A credit expiring today has not lapsed yet.');
    }

    // ── B. Expired promotional credit is excluded ────────────────────────────

    public function test_B_expired_promotional_credit_is_excluded_from_the_balance(): void
    {
        $patient = $this->patient();
        $this->promo($patient, 18000, today()->subDays(17)->toDateString());   // lapsed
        $this->promo($patient, 25000, today()->addMonths(4)->toDateString());  // live

        $wallet = $this->freshWallet($patient);

        $this->assertEqualsWithDelta(25000, $wallet->balance_promotional, 0.01,
            'Expired promotional credit must not count towards the available balance.');
        $this->assertEqualsWithDelta(25000, $wallet->balance_total, 0.01);
    }

    // ── C. Expired credit remains in history ─────────────────────────────────

    public function test_C_expired_credit_remains_visible_in_history(): void
    {
        $patient = $this->patient();
        $expired = $this->promo($patient, 18000, today()->subDays(17)->toDateString());
        $this->promo($patient, 25000, today()->addMonths(4)->toDateString());

        $row = WalletTransaction::find($expired->id);

        $this->assertNotNull($row, 'An expired credit must never be deleted.');
        $this->assertEqualsWithDelta(18000, (float) $row->amount, 0.01,
            'Its amount must not be rewritten.');
        $this->assertSame(today()->subDays(17)->toDateString(), $row->expiry_date->toDateString(),
            'Its expiry date must not be rewritten.');

        $this->assertSame(2, Wallet::forPatient($patient->id)->transactions()->count(),
            'Both credits stay in the ledger.');
    }

    // ── D. Total = available promotional + real credit ───────────────────────

    public function test_D_total_is_available_promotional_plus_real_credit(): void
    {
        // The exact real-world case that surfaced this bug.
        $patient = $this->patient();
        $this->promo($patient, 18000, '2026-07-31');                            // lapsed
        $this->promo($patient, 25000, today()->addMonths(4)->toDateString());   // live
        $this->permanent($patient, 50500);                                      // real credit

        $wallet = $this->freshWallet($patient);

        $this->assertEqualsWithDelta(25000, $wallet->balance_promotional, 0.01);
        $this->assertEqualsWithDelta(50500, $wallet->balance_permanent, 0.01);
        $this->assertEqualsWithDelta(75500, $wallet->balance_total, 0.01,
            '25,000 available promotional + 50,500 real credit — not 93,500.');
    }

    // ── E. Expired credit cannot pay an invoice ──────────────────────────────

    public function test_E_expired_promotional_credit_cannot_be_used_for_payment(): void
    {
        $patient = $this->patient();
        $this->promo($patient, 18000, today()->subDays(17)->toDateString());
        $invoice = $this->invoiceFor($patient, 5000);

        $consumed = $this->wallet()->debit(
            patientId:  $patient->id,
            amount:     5000,
            invoiceId:  $invoice->id,
            createdBy:  $this->staff()->id,
        );

        $this->assertEqualsWithDelta(0, $consumed, 0.01,
            'Lapsed promotional credit must not settle anything.');
        $this->assertSame(0, WalletTransaction::where('patient_id', $patient->id)
            ->where('direction', 'debit')->count(),
            'No debit row may be written against expired credit.');
        $this->assertEqualsWithDelta(0, $this->freshWallet($patient)->balance_promotional, 0.01);
    }

    /** Live credit still spends normally — the fix must not block valid use. */
    public function test_E2_live_promotional_credit_is_still_spendable(): void
    {
        $patient = $this->patient();
        $this->promo($patient, 18000, today()->subDays(17)->toDateString());  // lapsed
        $this->promo($patient, 6000,  today()->addMonths(2)->toDateString()); // live
        $invoice = $this->invoiceFor($patient, 5000);

        $consumed = $this->wallet()->debit(
            patientId:  $patient->id,
            amount:     5000,
            invoiceId:  $invoice->id,
            createdBy:  $this->staff()->id,
        );

        $this->assertEqualsWithDelta(5000, $consumed, 0.01);
        $this->assertEqualsWithDelta(1000, $this->freshWallet($patient)->balance_promotional, 0.01,
            '6,000 live minus 5,000 spent. The lapsed 18,000 never enters the sum.');
    }

    /**
     * Spending that came out of a lot which has since lapsed must not be charged
     * against a still-live lot. This is why the balance replays the ledger by
     * debit date instead of doing SUM(unexpired) - SUM(debits).
     */
    public function test_F_spending_from_a_lot_that_later_lapsed_does_not_hit_the_live_lot(): void
    {
        $patient = $this->patient();

        // Both live today, so the debit legitimately draws on the earlier lot.
        $this->promo($patient, 18000, today()->addDays(2)->toDateString());
        $this->promo($patient, 25000, today()->addMonths(4)->toDateString());

        $invoice = $this->invoiceFor($patient, 5000);
        $this->wallet()->debit(
            patientId: $patient->id,
            amount:    5000,
            invoiceId: $invoice->id,
            createdBy: $this->staff()->id,
        );

        // 18,000 - 5,000 spent = 13,000 still live, + 25,000 = 38,000.
        $this->assertEqualsWithDelta(38000, $this->freshWallet($patient)->balance_promotional, 0.01);

        // Let the calendar move past the earlier lot's expiry. Its unspent 13,000
        // dies with it — and so does the 5,000 that came out of it. The live lot
        // must still be worth its full 25,000.
        $this->travelTo(today()->addDays(5));

        $this->assertEqualsWithDelta(25000, $this->freshWallet($patient)->balance_promotional, 0.01,
            'Spending from a lapsed lot must not be deducted from a live one.');

        $this->travelBack();
    }
}
