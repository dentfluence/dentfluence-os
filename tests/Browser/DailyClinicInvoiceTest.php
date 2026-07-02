<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Patient;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Tests\DuskTestCase;
use Laravel\Dusk\Browser;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Daily Clinic Helper — Test 5: Create an Invoice
 * ─────────────────────────────────────────────────────────────────────────
 *
 *  WHAT THIS CHECKS (plain language):
 *  Opens the New Invoice screen for a patient, adds one line item with a
 *  price, saves, and confirms the invoice was created (you land on the
 *  invoice page AND a row exists in the database).
 *
 *  NOTE: Recording a payment is intentionally NOT auto-tested here, because
 *  payments create linked receipts/final-bills that are unsafe to auto-delete
 *  from a live database. That step will be added once a separate, disposable
 *  test database is set up (planned for go-live).
 *
 *  Creates its own throwaway patient and removes it (and the invoice) in
 *  tearDown so real records are untouched.
 */
class DailyClinicInvoiceTest extends DuskTestCase
{
    protected function tearDown(): void
    {
        // Foreign-key checks off so we can clean up regardless of table order.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $patientIds = Patient::where('last_name', 'DuskBill')->pluck('id');
        if ($patientIds->isNotEmpty()) {
            $invoiceIds = Invoice::whereIn('patient_id', $patientIds)->pluck('id');
            DB::table('invoice_items')->whereIn('invoice_id', $invoiceIds)->delete();
            Invoice::whereIn('id', $invoiceIds)->forceDelete();
            Patient::whereIn('id', $patientIds)->forceDelete();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    public function test_an_invoice_can_be_created(): void
    {
        $user = User::where('email', env('CRAWL_EMAIL'))->first();
        if (! $user) {
            $this->markTestSkipped('No user matching CRAWL_EMAIL found.');
        }

        $patient = Patient::create([
            'first_name' => 'DuskTest',
            'last_name'  => 'DuskBill',
            'name'       => 'DuskTest DuskBill',
            'gender'     => 'male',
            'phone'      => '9000000004',
            'branch_id'  => $user->branch_id ?? 1,
            'created_by' => $user->id,
        ]);
        $patientId = $patient->id;

        $this->browse(function (Browser $browser) use ($user, $patientId) {
            $browser->loginAs($user)
                    ->visit('/billing/create?patient_id=' . $patientId)
                    ->waitFor('@bill-add-item')
                    ->click('@bill-add-item')
                    ->waitFor('input[name="items[0][description]"]')
                    ->type('input[name="items[0][description]"]', 'Consultation Fee')
                    ->type('input[name="items[0][unit_price]"]', '500')
                    ->click('@bill-save')
                    // Saving redirects to the invoice page, where the
                    // "Record Payment" button (@pay-open) is shown.
                    ->waitFor('@pay-open', 15);
        });

        $this->assertDatabaseHas('invoices', ['patient_id' => $patientId]);
    }
}
