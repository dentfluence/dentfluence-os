<?php

namespace Tests\Feature\Print;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * V.27 slice 2 — the phone can fetch the SAME document the browser prints,
 * with a token, and only if its role may see that module.
 */
class DocumentPdfApiTest extends TestCase
{
    use RefreshDatabase, BuildsAccessPersonas;

    private function invoice(): Invoice
    {
        $patient = Patient::create([
            'first_name' => 'Pdf', 'last_name' => 'Patient', 'name' => 'Pdf Patient',
            'gender' => 'male', 'phone' => '9000000099', 'branch_id' => 1,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => Invoice::nextNumber(),
            'patient_id'     => $patient->id,
            'invoice_date'   => today()->toDateString(),
            'status'         => 'draft',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'description' => 'Root Canal Treatment',
            'unit_price' => 10000, 'qty' => 1, 'net_amount' => 10000,
            'gst_pct' => 0, 'gst_amount' => 0, 'total' => 10000,
        ]);

        $invoice->recalculate();

        return $invoice->fresh();
    }

    private function fakeGotenberg(): void
    {
        Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.4 invoice', 200)]);
    }

    public function test_a_token_user_with_finance_view_gets_the_invoice_pdf(): void
    {
        $this->fakeGotenberg();
        $invoice = $this->invoice();
        Sanctum::actingAs($this->userWithModulePerm('finance', true, false, false));

        $this->get('/api/v1/documents/invoice/' . $invoice->id . '/pdf')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_without_finance_the_invoice_pdf_is_refused(): void
    {
        $this->fakeGotenberg();
        $invoice = $this->invoice();
        Sanctum::actingAs($this->userWithModulePerm('patients', true, false, false));

        // 403, not 500: an api/* abort used to be swallowed by the catch-all
        // handler, so a refused permission reached the phone as a server error.
        $this->getJson('/api/v1/documents/invoice/' . $invoice->id . '/pdf')
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_an_unknown_document_type_is_not_served(): void
    {
        Sanctum::actingAs($this->userWithModulePerm('finance', true, false, false));

        $this->getJson('/api/v1/documents/payslip/1/pdf')->assertStatus(404);
    }

    public function test_a_guest_gets_nothing(): void
    {
        $invoice = $this->invoice();

        $this->getJson('/api/v1/documents/invoice/' . $invoice->id . '/pdf')->assertStatus(401);
    }
}
