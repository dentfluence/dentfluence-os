<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Patient;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Tests\DuskTestCase;
use Laravel\Dusk\Browser;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Daily Clinic Helper — Test 7: Add Wallet Credit
 * ─────────────────────────────────────────────────────────────────────────
 *
 *  WHAT THIS CHECKS (plain language):
 *  Opens the wallet credit form for a patient, adds a permanent credit of
 *  Rs 500, saves, and confirms a wallet transaction was created.
 *
 *  Permanent (not promotional) credit is used so no expiry date is required.
 *  Creates its own throwaway patient and removes it (and the wallet
 *  transaction) in tearDown.
 */
class DailyClinicWalletTest extends DuskTestCase
{
    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $patientIds = Patient::where('last_name', 'DuskWallet')->pluck('id');
        if ($patientIds->isNotEmpty()) {
            WalletTransaction::whereIn('patient_id', $patientIds)->forceDelete();
            Patient::whereIn('id', $patientIds)->forceDelete();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    public function test_wallet_credit_can_be_added(): void
    {
        $user = User::where('email', env('CRAWL_EMAIL'))->first();
        if (! $user) {
            $this->markTestSkipped('No user matching CRAWL_EMAIL found.');
        }

        $patient = Patient::create([
            'first_name' => 'DuskTest',
            'last_name'  => 'DuskWallet',
            'name'       => 'DuskTest DuskWallet',
            'gender'     => 'male',
            'phone'      => '9000000006',
            'branch_id'  => $user->branch_id ?? 1,
            'created_by' => $user->id,
        ]);
        $patientId = $patient->id;

        $this->browse(function (Browser $browser) use ($user, $patientId) {
            $browser->loginAs($user)
                    ->visit('/finance/wallets/' . $patientId . '/credit')
                    ->waitFor('#type_perm')
                    // Choose "permanent" credit (no expiry needed).
                    ->script("var r=document.getElementById('type_perm');r.checked=true;r.dispatchEvent(new Event('change',{bubbles:true}));");

            $browser->type('input[name="amount"]', '500')
                    ->press('Add Credit')
                    ->pause(3000);
        });

        $this->assertDatabaseHas('wallet_transactions', ['patient_id' => $patientId]);
    }
}
