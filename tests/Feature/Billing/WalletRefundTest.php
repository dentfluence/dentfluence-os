<?php

namespace Tests\Feature\Billing;

use App\Models\Finance\FinanceExpense;
use App\Models\Finance\FinanceTransaction;
use App\Models\Patient;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * A1 — WALLET REFUND SAFETY (frozen business rule, Tulip Dental 2026-08-17).
 *
 * Wallet refunds are ALL-OR-NOTHING and cover patient-funded credit only.
 *
 * The bug this replaces: the controller validated the requested amount against
 * balance_permanent while WalletService::withdraw() capped at
 * balance_patient_credit. A clinic-funded gift inflated the former, so
 * withdraw() returned 0.0, the closure returned early — no wallet row, no
 * finance row, no audit row — and the success message still reported the
 * REQUESTED amount. Cash left the drawer with nothing on the books.
 *
 * The invariants:
 *
 *   Refundable == balance_patient_credit, never promotional or clinic-funded.
 *   A partial request is REJECTED, never silently rounded up to the full amount.
 *   The amount reported == the amount actually refunded.
 *   All four records exist, or none do.
 *   Revenue impact 0. Expense impact 0. It is a liability reversal.
 */
class WalletRefundTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create();
    }

    private function patient(string $name = 'Refund Patient'): Patient
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

    /** Patient hands over cash → patient-funded credit (U8 funding='patient'). */
    private function advance(Patient $patient, float $amount, ?int $by = null): void
    {
        $this->wallet()->receiveAdvance(
            patient:     $patient,
            amount:      $amount,
            paymentMode: 'cash',
            paymentDate: today()->toDateString(),
            createdBy:   $by ?? $this->staff()->id,
        );
    }

    /** Clinic gift → promotional, never the patient's money. */
    private function promo(Patient $patient, float $amount, ?int $by = null): void
    {
        $this->wallet()->credit(
            patientId:  $patient->id,
            amount:     $amount,
            creditType: 'promotional',
            expiryDate: today()->addMonths(6)->toDateString(),
            notes:      'Campaign',
            createdBy:  $by ?? $this->staff()->id,
        );
    }

    private function refund(Patient $patient, ?float $expected = null, ?int $by = null): array
    {
        return $this->wallet()->refundFullPatientCredit(
            patientId:      $patient->id,
            paymentMode:    'cash',
            refundDate:     today()->toDateString(),
            reason:         'Treatment cancelled',
            createdBy:      $by ?? $this->staff()->id,
            expectedAmount: $expected,
        );
    }

    private function freshWallet(Patient $patient): Wallet
    {
        return Wallet::forPatient($patient->id)->fresh();
    }

    private function debits(Patient $patient): int
    {
        return WalletTransaction::where('patient_id', $patient->id)
            ->where('direction', 'debit')->count();
    }

    // ── A. Full refund succeeds ──────────────────────────────────────────────

    public function test_A_full_refund_of_patient_funded_credit_succeeds(): void
    {
        $patient = $this->patient();
        $this->advance($patient, 10000);

        $result = $this->refund($patient);

        $this->assertEqualsWithDelta(10000, $result['refunded'], 0.01);
        $this->assertEqualsWithDelta(0, $this->freshWallet($patient)->balance_patient_credit, 0.01);
    }

    // ── B. Partial refund is rejected ────────────────────────────────────────

    public function test_B_a_partial_refund_request_is_rejected(): void
    {
        $patient = $this->patient();
        $this->advance($patient, 10000);

        try {
            $this->refund($patient, expected: 5000);
            $this->fail('A partial refund must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }

        // Rejected means REJECTED — not quietly upgraded to a full refund.
        $this->assertEqualsWithDelta(10000, $this->freshWallet($patient)->balance_patient_credit, 0.01,
            'A rejected partial refund must leave the balance untouched.');
        $this->assertSame(0, $this->debits($patient));
        $this->assertSame(0, FinanceTransaction::where('patient_id', $patient->id)
            ->where('type', 'refund')->count());
    }

    // ── C. Only patient-funded credit is refundable ──────────────────────────

    public function test_C_promotional_credit_is_excluded_from_the_refundable_amount(): void
    {
        $patient = $this->patient();
        $this->advance($patient, 10000);
        $this->promo($patient, 5000);

        $result = $this->refund($patient);

        $this->assertEqualsWithDelta(10000, $result['refunded'], 0.01,
            'Refundable is patient-funded credit only — the 5,000 promotional must not be paid out.');
    }

    // ── D. Promotional-only wallet cannot be refunded ────────────────────────

    public function test_D_a_promotional_only_wallet_refund_is_rejected(): void
    {
        $patient = $this->patient();
        $this->promo($patient, 5000);

        $this->expectException(ValidationException::class);
        $this->refund($patient);
    }

    // ── E. Balances after a refund ───────────────────────────────────────────

    public function test_E_after_refund_patient_credit_is_zero_and_promotional_is_unchanged(): void
    {
        $patient = $this->patient();
        $this->advance($patient, 10000);
        $this->promo($patient, 5000);

        $this->refund($patient);

        $wallet = $this->freshWallet($patient);
        $this->assertEqualsWithDelta(0, $wallet->balance_patient_credit, 0.01);
        $this->assertEqualsWithDelta(5000, $wallet->balance_promotional, 0.01,
            'Promotional credit must survive a refund untouched.');
    }

    // ── F. The records a refund must create ──────────────────────────────────

    public function test_F_refund_creates_the_wallet_debit_and_the_finance_record(): void
    {
        $patient = $this->patient();
        $staff   = $this->staff();
        $this->advance($patient, 10000, $staff->id);

        $result = $this->refund($patient, by: $staff->id);

        $tx = $result['transaction'];
        $this->assertSame('debit', $tx->direction);
        $this->assertSame('withdrawal', $tx->source);
        $this->assertSame(WalletService::FUNDING_PATIENT, $tx->funding,
            'The debit must reduce PATIENT-funded credit, not a concession.');
        $this->assertEqualsWithDelta(10000, (float) $tx->amount, 0.01);

        $ft = $result['finance_transaction'];
        $this->assertSame('refund', $ft->type);
        $this->assertSame('debit', $ft->direction);
        $this->assertEqualsWithDelta(10000, (float) $ft->amount, 0.01);
        $this->assertEqualsWithDelta(10000, (float) $ft->net_amount, 0.01);
        $this->assertSame(WalletTransaction::class, $ft->source_type);
        $this->assertSame($tx->id, (int) $ft->source_id,
            'The finance row must point at the wallet row it mirrors.');
    }

    // ── G. No revenue ────────────────────────────────────────────────────────

    public function test_G_refund_creates_no_revenue(): void
    {
        $patient = $this->patient();
        $this->advance($patient, 10000);

        $this->refund($patient);

        $this->assertSame(0, FinanceTransaction::where('patient_id', $patient->id)
            ->where('type', 'income')->count(),
            'Returning a patient their own money is not income.');
    }

    // ── H. No expense ────────────────────────────────────────────────────────

    public function test_H_refund_creates_no_expense(): void
    {
        $patient = $this->patient();
        $this->advance($patient, 10000);

        $this->refund($patient);

        $this->assertSame(0, FinanceExpense::count(),
            'A refund is a liability reversal, not a clinic expense.');
        $this->assertSame(0, FinanceTransaction::where('patient_id', $patient->id)
            ->where('type', 'expense')->count());
    }

    // ── I. A failed refund mutates nothing ───────────────────────────────────

    public function test_I_a_failed_refund_leaves_no_partial_mutation(): void
    {
        $patient = $this->patient();
        $this->promo($patient, 5000);   // nothing refundable

        $walletTxBefore = WalletTransaction::where('patient_id', $patient->id)->count();

        try {
            $this->refund($patient);
            $this->fail('Expected the refund to be rejected.');
        } catch (ValidationException $e) {
            // expected
        }

        $this->assertSame($walletTxBefore,
            WalletTransaction::where('patient_id', $patient->id)->count(),
            'No wallet row may be written by a failed refund.');
        $this->assertSame(0, FinanceTransaction::where('patient_id', $patient->id)
            ->where('type', 'refund')->count(),
            'No finance row may be written by a failed refund.');
        $this->assertEqualsWithDelta(5000, $this->freshWallet($patient)->balance_promotional, 0.01);
    }

    // ── J. The same credit cannot be refunded twice ──────────────────────────

    public function test_J_a_second_refund_cannot_pay_out_the_same_credit(): void
    {
        $patient = $this->patient();
        $this->advance($patient, 10000);

        $first = $this->refund($patient);
        $this->assertEqualsWithDelta(10000, $first['refunded'], 0.01);

        try {
            $this->refund($patient);
            $this->fail('A second refund must be rejected — the credit is already gone.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('refund', $e->errors());
        }

        $this->assertSame(1, $this->debits($patient),
            'Exactly one withdrawal may exist for one credit.');
        $this->assertSame(1, FinanceTransaction::where('patient_id', $patient->id)
            ->where('type', 'refund')->count());
        $this->assertEqualsWithDelta(10000, (float) FinanceTransaction::where('patient_id', $patient->id)
            ->where('type', 'refund')->sum('amount'), 0.01,
            'Total cash refunded must be 10,000 — never 20,000.');
    }

    // ── K. The books stay consistent ─────────────────────────────────────────

    public function test_K_the_ledger_is_financially_consistent_after_a_refund(): void
    {
        $patient = $this->patient();
        $this->advance($patient, 10000);

        $this->refund($patient);

        $advanceIn  = (float) FinanceTransaction::where('patient_id', $patient->id)
            ->where('type', 'advance')->sum('amount');
        $refundOut  = (float) FinanceTransaction::where('patient_id', $patient->id)
            ->where('type', 'refund')->sum('amount');
        $income     = (float) FinanceTransaction::where('patient_id', $patient->id)
            ->where('type', 'income')->sum('amount');

        // 10,000 in as a liability, 10,000 back out. Net liability zero, net
        // cash zero, revenue zero — the patient's money simply passed through.
        $this->assertEqualsWithDelta(10000, $advanceIn, 0.01);
        $this->assertEqualsWithDelta(10000, $refundOut, 0.01);
        $this->assertEqualsWithDelta(0, $advanceIn - $refundOut, 0.01,
            'Advance liability must net to zero after a full refund.');
        $this->assertEqualsWithDelta(0, $income, 0.01);
        $this->assertEqualsWithDelta(0, $this->freshWallet($patient)->balance_patient_credit, 0.01);
    }

    // ── L. Existing flows are unchanged ──────────────────────────────────────

    public function test_L_existing_wallet_flows_still_behave_as_before(): void
    {
        $patient = $this->patient();
        $staff   = $this->staff();

        // Advance still credits and still books a liability, not income.
        $this->advance($patient, 4000, $staff->id);
        $this->assertEqualsWithDelta(4000, $this->freshWallet($patient)->balance_patient_credit, 0.01);
        $this->assertSame(1, FinanceTransaction::where('patient_id', $patient->id)
            ->where('type', 'advance')->count());

        // Promotional credit still lands in its own bucket, untouched by any of this.
        $this->promo($patient, 1500, $staff->id);
        $this->assertEqualsWithDelta(1500, $this->freshWallet($patient)->balance_promotional, 0.01);

        // withdraw() is untouched — U8 still relies on its capping behaviour.
        $capped = $this->wallet()->withdraw(
            patientId:   $patient->id,
            amount:      99999,
            paymentMode: 'cash',
            notes:       'U8 behaviour check',
            createdBy:   $staff->id,
        );
        $this->assertEqualsWithDelta(4000, $capped, 0.01,
            'withdraw() must still cap at patient credit — U8 tests depend on it.');
        $this->assertEqualsWithDelta(1500, $this->freshWallet($patient)->balance_promotional, 0.01);
    }
}
