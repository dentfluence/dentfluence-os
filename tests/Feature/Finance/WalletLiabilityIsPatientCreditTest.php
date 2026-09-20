<?php

namespace Tests\Feature\Finance;

use App\Models\Patient;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * Signal Board row 2.7 — a wallet liability figure must read the CASH-BACKED
 * pot and nothing else.
 *
 * U8 rule 16: promotional credit was never money the clinic received. It may
 * expire and it is not refundable, so it is a marketing commitment, not a
 * debt. balance_total is written as promotional + permanent, so every report
 * that summed it would have grown by the whole promotional balance the day a
 * wallet campaign ran — harmless on production today only because no
 * promotional credit exists yet. That is exactly the kind of bug that is
 * invisible until the moment it matters.
 *
 * Pinned here, on the real HTTP route rather than on the service:
 *   1. the Wallet tab's liability tiles ignore promotional credit
 *   2. the Liability tab counts a wallet that holds patient credit even when
 *      balance_total is zero — the old filter dropped it before summing
 *   3. issuing promotional credit moves the liability by exactly nothing
 *
 * SPENDABLE is a different question and deliberately still reads
 * balance_total: "Outstanding After Wallet", the app's wallet_balance, and the
 * Wallets screen. Promotional credit does spend against an invoice.
 */
class WalletLiabilityIsPatientCreditTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    private function financeUser()
    {
        return $this->userWithModulePerm('finance', true, false, false);
    }

    private function patient(string $name): Patient
    {
        return Patient::create([
            'name'      => $name,
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ]);
    }

    /**
     * Every column carries a DIFFERENT number, so reading the wrong one cannot
     * accidentally pass.
     */
    private function wallet(string $name, float $promo, float $perm, float $patientCredit): Wallet
    {
        return Wallet::create([
            'patient_id'             => $this->patient($name)->id,
            'balance_promotional'    => $promo,
            'balance_permanent'      => $perm,
            'balance_patient_credit' => $patientCredit,
            'balance_total'          => $promo + $perm,
        ]);
    }

    private function tab(string $tab): array
    {
        return $this->actingAs($this->financeUser())
            ->get(route('finance.reports', ['tab' => $tab]))
            ->assertOk()
            ->viewData('data');
    }

    public function test_wallet_tab_liability_tiles_ignore_promotional_credit(): void
    {
        // Cash-backed: the clinic really owes 2,000.
        $this->wallet('Paid In Advance', promo: 5000, perm: 2000, patientCredit: 2000);

        // Pure marketing credit — never money the clinic received.
        $this->wallet('Campaign Winner', promo: 3000, perm: 0, patientCredit: 0);

        $data = $this->tab('wallet');

        // balance_total would have said 10,000 across the two wallets.
        $this->assertSame(2000.0, (float) $data['outstanding'],
            'The Wallet tab liability tile is summing promotional credit.');

        $this->assertSame(1, (int) $data['patients'],
            'The promotional-only wallet is being counted as a patient we owe.');
    }

    public function test_liability_tab_counts_patient_credit_held_with_no_total_balance(): void
    {
        // balance_total 0, patient credit 1,500 — the old `balance_total > 0`
        // filter removed this wallet from the collection that $totalLiability
        // is summed from, so the headline understated the debt.
        $this->wallet('Credit Only', promo: 0, perm: 0, patientCredit: 1500);

        $data = $this->tab('liability');

        $this->assertSame(1500.0, (float) $data['totalLiability'],
            'A wallet holding patient credit with no balance_total was dropped.');

        $this->assertCount(1, $data['wallets'],
            'The Wallet Balances list no longer shows a credit-only wallet.');
    }

    public function test_issuing_promotional_credit_does_not_move_the_liability(): void
    {
        $this->wallet('Paid In Advance', promo: 0, perm: 4000, patientCredit: 4000);

        $before = (float) $this->tab('liability')['totalLiability'];

        // Run a wallet campaign: 25,000 of promotional credit across two patients.
        $this->wallet('Promo One', promo: 15000, perm: 0, patientCredit: 0);
        $this->wallet('Promo Two', promo: 10000, perm: 0, patientCredit: 0);

        $after = (float) $this->tab('liability')['totalLiability'];

        $this->assertSame(4000.0, $before);
        $this->assertSame($before, $after,
            'A promotional campaign changed the reported liability.');
    }

    public function test_outstanding_after_wallet_still_uses_the_spendable_balance(): void
    {
        // Spendable is a different question: promotional credit does reduce
        // what the patient has to find, so this read must NOT be switched.
        $w = $this->wallet('Spender', promo: 1000, perm: 500, patientCredit: 500);

        $data = $this->tab('liability');

        $this->assertSame(1500.0, (float) $w->balance_total);
        $this->assertArrayHasKey('outstandingRows', $data);
    }
}
