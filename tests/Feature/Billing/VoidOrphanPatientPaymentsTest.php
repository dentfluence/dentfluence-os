<?php

namespace Tests\Feature\Billing;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Patient;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/** One-off repair for PAY- receipts left live after an invoice cancel (S1, 25 Sep 2026). */
class VoidOrphanPatientPaymentsTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    private function admin(): User
    {
        $user = $this->userWithModulePerm('finance', true, true, true);
        $user->update(['role' => 'admin', 'role_id' => \App\Models\Role::where('slug', \App\Models\Role::ADMIN)->value('id')]);

        return $user->fresh();
    }

    private function paidByPatientPayment(User $user): Receipt
    {
        $patient = Patient::create(['name' => 'Orphan Patient', 'phone' => '9' . random_int(100000000, 999999999), 'branch_id' => 1]);
        $invoice = Invoice::create([
            'invoice_number' => Invoice::nextNumber(), 'patient_id' => $patient->id,
            'invoice_date' => today()->toDateString(), 'status' => 'draft',
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'description' => 'Treatment', 'unit_price' => 100,
            'qty' => 1, 'net_amount' => 100, 'gst_pct' => 0, 'gst_amount' => 0, 'total' => 100,
        ]);
        $invoice->recalculate();

        $this->actingAs($user)->post(route('billing.patientPayment', $patient), [
            'amount' => 50, 'payment_mode' => 'cash', 'payment_date' => today()->toDateString(),
        ])->assertRedirect();

        return Receipt::where('patient_id', $patient->id)->latest('id')->firstOrFail();
    }

    public function test_only_receipts_whose_allocations_are_all_reversed_are_voided(): void
    {
        $user   = $this->admin();
        $orphan = $this->paidByPatientPayment($user);
        $live   = $this->paidByPatientPayment($user);

        // Simulate the old cancel: allocation + its income reversed, receipt left live.
        foreach (InvoicePayment::where('receipt_id', $orphan->id)->get() as $p) {
            \App\Models\Finance\FinanceTransaction::where('source_type', InvoicePayment::class)
                ->where('source_id', $p->id)->update(['status' => 'voided']);
            $p->delete();
        }

        $this->artisan('billing:void-orphan-payments')->assertSuccessful();
        $this->assertNull($orphan->fresh()->voided_at); // report only

        $this->artisan('billing:void-orphan-payments', ['--apply' => true, '--by' => $user->id])->assertSuccessful();

        $this->assertNotNull($orphan->fresh()->voided_at);
        $this->assertNull($live->fresh()->voided_at);
    }
}
