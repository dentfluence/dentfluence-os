<?php

namespace Tests\Feature\Api;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\TreatmentVisit;
use App\Models\TreatmentVisitItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * M-7 / G-33 — GET /patients/{id}/visits used to return 'cost' and 'paid'
 * from columns no migration ever created, so every visit read null. A
 * visit's money is the invoice lines its work items are linked to.
 */
class PatientVisitsMoneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visit_reports_the_billed_total_of_its_linked_invoice_lines(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'branch_id' => 1, 'is_active' => true]);
        $p     = Patient::create(['name' => 'Visit Money', 'phone' => '9111111111', 'branch_id' => 1]);

        $visit = TreatmentVisit::create([
            'patient_id'     => $p->id,
            'doctor_id'      => $admin->id,
            'visit_date'     => today()->toDateString(),
            'treatment_name' => 'RCT 36',
        ]);

        $invoice = Invoice::create([
            'invoice_number' => Invoice::nextNumber(),
            'patient_id'     => $p->id,
            'invoice_date'   => today()->toDateString(),
            'status'         => 'draft',
        ]);
        $line = InvoiceItem::create([
            'invoice_id' => $invoice->id, 'description' => 'RCT', 'unit_price' => 6000, 'qty' => 1,
            'net_amount' => 6000, 'gst_pct' => 0, 'gst_amount' => 0, 'total' => 6000,
        ]);

        TreatmentVisitItem::create([
            'treatment_visit_id' => $visit->id, 'patient_id' => $p->id, 'treatment_name' => 'RCT',
            'tooth_number' => '36', 'billing_status' => 'invoiced', 'invoice_item_id' => $line->id,
        ]);
        TreatmentVisitItem::create([
            'treatment_visit_id' => $visit->id, 'patient_id' => $p->id, 'treatment_name' => 'X-ray',
            'tooth_number' => '36', 'billing_status' => 'pending',
        ]);

        Sanctum::actingAs($admin, ['*']);
        $rows = $this->getJson("/api/v1/patients/{$p->id}/visits")->assertOk()->json('data');

        $this->assertCount(1, $rows);
        $this->assertSame(6000.0, (float) $rows[0]['billed']);
        $this->assertSame('pending', $rows[0]['billing_status'], 'one item still unbilled');
        $this->assertSame(2, $rows[0]['items_count']);
        $this->assertArrayNotHasKey('cost', $rows[0], 'the dead column must not come back');
    }

    public function test_a_visit_with_no_items_reports_zero_and_no_billing_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'branch_id' => 1, 'is_active' => true]);
        $p     = Patient::create(['name' => 'Bare Visit', 'phone' => '9222222222', 'branch_id' => 1]);
        TreatmentVisit::create(['patient_id' => $p->id, 'doctor_id' => $admin->id, 'visit_date' => today()->toDateString(), 'treatment_name' => 'Checkup']);

        Sanctum::actingAs($admin, ['*']);
        $rows = $this->getJson("/api/v1/patients/{$p->id}/visits")->assertOk()->json('data');

        $this->assertSame(0.0, (float) $rows[0]['billed']);
        $this->assertNull($rows[0]['billing_status']);
    }
}
