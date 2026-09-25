<?php

namespace Tests\Feature\Billing;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\User;
use App\Services\Billing\ReceiptVoidService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * INT-04 (security audit 24 Sep 2026) — Trash "Restore" on a cancelled
 * invoice brought back a zombie: still cancelled, payments voided, stock and
 * credit already returned. A cancel is now final.
 */
class CancelledInvoiceStaysCancelledTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    private function admin(): User
    {
        $user = $this->userWithModulePerm('finance', true, true, true);
        $user->update(['role' => 'admin', 'role_id' => \App\Models\Role::where('slug', \App\Models\Role::ADMIN)->value('id')]);

        return $user->fresh();
    }

    private function invoice(): Invoice
    {
        $patient = Patient::create(['name' => 'Trash Patient', 'phone' => '9' . random_int(100000000, 999999999), 'branch_id' => 1]);
        $invoice = Invoice::create([
            'invoice_number' => Invoice::nextNumber(), 'patient_id' => $patient->id,
            'invoice_date' => today()->toDateString(), 'status' => 'draft',
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'description' => 'Treatment', 'unit_price' => 500,
            'qty' => 1, 'net_amount' => 500, 'gst_pct' => 0, 'gst_amount' => 0, 'total' => 500,
        ]);
        $invoice->recalculate();

        return $invoice->fresh();
    }

    public function test_a_cancelled_invoice_cannot_be_restored(): void
    {
        $user = $this->admin();
        $this->actingAs($user);
        $invoice = $this->invoice();
        app(ReceiptVoidService::class)->cancelInvoice($invoice, 'no_refund', 'Wrong patient', $user->id);

        $this->post(route('finance.income.trash.invoice.restore', $invoice->id))->assertRedirect();

        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
    }

    public function test_a_trashed_invoice_that_was_not_cancelled_can_still_be_restored(): void
    {
        $user = $this->admin();
        $invoice = $this->invoice();
        $invoice->delete();

        $this->actingAs($user)->post(route('finance.income.trash.invoice.restore', $invoice->id))->assertRedirect();

        $this->assertNotSoftDeleted('invoices', ['id' => $invoice->id]);
    }
}
