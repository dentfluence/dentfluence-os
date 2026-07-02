<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Patient;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Finance module — Payment automation chain
 * ─────────────────────────────────────────────────────────────────────────
 *
 *  WHAT THIS CHECKS (plain language):
 *  Recording a full payment on an invoice should automatically:
 *    1. create a Receipt,
 *    2. create a finance ledger entry (FinanceTransaction, income), and
 *    3. mark the invoice "paid".
 *
 *  Runs through the real billing endpoints (middleware disabled so we test
 *  the controller logic without needing seeded permissions). Uses the
 *  separate dentfluence_testing DB, rebuilt each run.
 */
class FinancePaymentAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_recording_a_payment_creates_receipt_ledger_and_marks_invoice_paid(): void
    {
        // Disable ONLY the module-permission gate (keep route-model-binding,
        // auth, etc. intact). CSRF is auto-skipped in tests.
        $this->withoutMiddleware(\App\Http\Middleware\CheckModulePermission::class);

        $user = User::factory()->create(['branch_id' => 1]);

        $patient = Patient::create([
            'first_name' => 'Test',
            'last_name'  => 'PayAuto',
            'name'       => 'Test PayAuto',
            'gender'     => 'male',
            'phone'      => '9000000030',
            'branch_id'  => 1,
            'created_by' => $user->id,
        ]);

        // 1. Create an invoice (Rs 500) via the real endpoint.
        $createResp = $this->actingAs($user)->post(route('billing.store'), [
            'patient_id'   => $patient->id,
            'invoice_date' => today()->toDateString(),
            'items'        => [
                ['description' => 'Consultation', 'unit_price' => 500, 'qty' => 1],
            ],
        ]);
        $createResp->assertSessionHasNoErrors();   // DIAG: shows invoice validation errors if any

        $invoice = Invoice::where('patient_id', $patient->id)->latest('id')->first();
        $this->assertNotNull($invoice, 'Invoice was not created by billing.store');
        $this->assertGreaterThan(0, (float) $invoice->total_amount, 'DIAG: invoice total_amount is 0');

        // 2. Record a full cash payment.
        $payResp = $this->actingAs($user)->post(route('billing.payment', $invoice), [
            'amount'       => (float) $invoice->total_amount,
            'payment_mode' => 'cash',
            'payment_date' => today()->toDateString(),
        ]);
        $payResp->assertSessionHasNoErrors();   // DIAG: shows payment validation errors if any

        // 3. The automation chain should have fired.
        $this->assertDatabaseHas('receipts', ['invoice_id' => $invoice->id]);
        $this->assertDatabaseHas('finance_transactions', [
            'patient_id' => $patient->id,
            'type'       => 'income',
        ]);
        $this->assertSame('paid', $invoice->fresh()->status);
    }
}
