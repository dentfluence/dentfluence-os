<?php

namespace Tests\Feature\Billing;

use App\Http\Middleware\CheckModulePermission;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Treatment;
use App\Models\TreatmentCategory;
use App\Models\TreatmentVisit;
use App\Models\TreatmentVisitItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\TreatmentVisits\Concerns\BuildsVisitFixtures;
use Tests\TestCase;

/**
 * T-1 Slice B — the join between the work that was done and the money charged.
 *
 * Slice A gave the work line its treatment_id. On its own that changed no
 * report, because the invoice line was still built from a description string
 * and nothing ever recorded which work line became which invoice line.
 *
 * Two separate defects, measured before this suite was written:
 *
 *   1. billing/form.blade.php called addRow(label, price, tooth, visitItemId)
 *      and left the 5th argument off, so every pre-filled row posted a blank
 *      treatment_id. Billed work therefore landed in the "Uncategorised"
 *      revenue bucket even when the visit knew exactly what it was.
 *
 *   2. treatment_visit_items.invoice_item_id had NO WRITER AT ALL. The column
 *      has existed since the table was created; BillingController only ever
 *      bulk-set billing_status. Doctor-wise revenue, the invoice.created
 *      notification and the patient-facing "billed" flag all read it.
 *
 * The link is deliberately carried per row rather than as the old flat
 * visit_item_ids[] array: a flat array can say "these work lines were billed"
 * but never "this work line became that line", which is the only form of the
 * answer a revenue report can use.
 */
class VisitToInvoiceLinkTest extends TestCase
{
    use RefreshDatabase;
    use BuildsVisitFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(CheckModulePermission::class);
    }

    /** Instance, never static — RefreshDatabase rolls the row back per test. */
    private ?TreatmentCategory $category = null;

    private function makeTreatment(string $name = 'Zirconia Crown'): Treatment
    {
        $this->category ??= TreatmentCategory::create(['name' => 'Ops', 'billing_basis' => 'gross']);

        return Treatment::create([
            'treatment_category_id'    => $this->category->id,
            'name'                     => $name,
            'default_duration_minutes' => 30,
            'default_price'            => 5000,
            'gst_pct'                  => 0,
            'is_active'                => true,
        ]);
    }

    private function makeVisitItem($patient, ?Treatment $treatment = null): TreatmentVisitItem
    {
        $visit = TreatmentVisit::create([
            'patient_id' => $patient->id,
            'visit_date' => now()->format('Y-m-d'),
            'status'     => 'completed',
        ]);

        return TreatmentVisitItem::create([
            'treatment_visit_id' => $visit->id,
            'patient_id'         => $patient->id,
            'treatment_id'       => $treatment?->id,
            'treatment_name'     => $treatment?->name ?? 'Zirconia Crown',
            'suggested_price'    => 5000,
            'billing_status'     => 'pending',
        ]);
    }

    private function invoicePayload($patient, array $items): array
    {
        return [
            'patient_id'   => $patient->id,
            'invoice_date' => now()->format('Y-m-d'),
            'items'        => $items,
        ];
    }

    private function line(array $overrides = []): array
    {
        return array_merge([
            'description' => 'Zirconia Crown',
            'unit_price'  => 5000,
            'qty'         => 1,
        ], $overrides);
    }

    // ── 1. the master id reaches the invoice line ───────────────────────────

    public function test_an_invoice_line_stores_the_treatment_master_id(): void
    {
        $user      = $this->makeUser();
        $patient   = $this->makePatient();
        $treatment = $this->makeTreatment();

        $this->actingAs($user)
            ->post(route('billing.store'), $this->invoicePayload($patient, [
                $this->line(['treatment_id' => $treatment->id]),
            ]))
            ->assertRedirect();

        $this->assertSame(
            $treatment->id,
            InvoiceItem::firstOrFail()->treatment_id,
            'Without this the line is a description string and revenue has no category.'
        );
    }

    // ── 2. the work line learns which invoice line it became ────────────────

    public function test_saving_an_invoice_links_the_work_line_to_the_invoice_line(): void
    {
        $user      = $this->makeUser();
        $patient   = $this->makePatient();
        $treatment = $this->makeTreatment();
        $visitItem = $this->makeVisitItem($patient, $treatment);

        $this->actingAs($user)
            ->post(route('billing.store'), $this->invoicePayload($patient, [
                $this->line([
                    'treatment_id'  => $treatment->id,
                    'visit_item_id' => $visitItem->id,
                ]),
            ]))
            ->assertRedirect();

        $invoiceItem = InvoiceItem::firstOrFail();

        $this->assertSame(
            $invoiceItem->id,
            $visitItem->fresh()->invoice_item_id,
            'invoice_item_id had no writer at all before T-1 Slice B.'
        );
    }

    // ── 3. an id from the browser is not trusted across patients ────────────

    public function test_a_work_line_from_another_patient_is_never_linked(): void
    {
        $user      = $this->makeUser();
        $patientA  = $this->makePatient();
        $patientB  = $this->makePatient();
        $treatment = $this->makeTreatment();

        // Belongs to B; posted on A's invoice. `exists:treatment_visit_items,id`
        // passes on its own, which is exactly why the patient check exists.
        $foreign = $this->makeVisitItem($patientB, $treatment);

        $this->actingAs($user)
            ->post(route('billing.store'), $this->invoicePayload($patientA, [
                $this->line(['visit_item_id' => $foreign->id]),
            ]))
            ->assertRedirect();

        $this->assertNull(
            $foreign->fresh()->invoice_item_id,
            'One patient\'s invoice must never claim another patient\'s work.'
        );
    }

    // ── 4. editing rebuilds every line — the link must be rebuilt with it ───

    public function test_editing_an_invoice_re_establishes_the_link_it_tore_down(): void
    {
        $user      = $this->makeUser();
        $patient   = $this->makePatient();
        $treatment = $this->makeTreatment();
        $visitItem = $this->makeVisitItem($patient, $treatment);

        $this->actingAs($user)->post(route('billing.store'), $this->invoicePayload($patient, [
            $this->line(['treatment_id' => $treatment->id, 'visit_item_id' => $visitItem->id]),
        ]))->assertRedirect();

        $invoice    = Invoice::firstOrFail();
        $originalId = $visitItem->fresh()->invoice_item_id;
        $this->assertNotNull($originalId);

        // update() deletes every line and recreates it, which nulls the FK on
        // the way through. A price correction must not silently unbill the work.
        $this->actingAs($user)->put(route('billing.update', $invoice), $this->invoicePayload($patient, [
            $this->line([
                'unit_price'    => 6000,
                'treatment_id'  => $treatment->id,
                'visit_item_id' => $visitItem->id,
            ]),
        ]))->assertRedirect();

        $newId = $visitItem->fresh()->invoice_item_id;

        $this->assertNotNull($newId, 'The edit tore the link down and never rebuilt it.');
        $this->assertNotSame($originalId, $newId, 'The line was recreated, so the id must have moved.');
        $this->assertSame($newId, $invoice->fresh()->items()->firstOrFail()->id);
    }

    // ── 5. removing the line removes the claim ──────────────────────────────

    public function test_dropping_the_line_from_an_edit_leaves_the_work_unlinked(): void
    {
        $user      = $this->makeUser();
        $patient   = $this->makePatient();
        $treatment = $this->makeTreatment();
        $visitItem = $this->makeVisitItem($patient, $treatment);

        $this->actingAs($user)->post(route('billing.store'), $this->invoicePayload($patient, [
            $this->line(['visit_item_id' => $visitItem->id]),
        ]))->assertRedirect();

        $invoice = Invoice::firstOrFail();
        $this->assertNotNull($visitItem->fresh()->invoice_item_id);

        // The user replaces that line with something unrelated.
        $this->actingAs($user)->put(route('billing.update', $invoice), $this->invoicePayload($patient, [
            $this->line(['description' => 'Consultation fee', 'unit_price' => 500]),
        ]))->assertRedirect();

        $this->assertNull(
            $visitItem->fresh()->invoice_item_id,
            'Nothing is billing this work any more, so nothing may claim it is.'
        );
    }

    // ── 6. an invoice line with no work behind it stays unlinked ────────────

    public function test_a_plain_invoice_line_links_nothing(): void
    {
        $user    = $this->makeUser();
        $patient = $this->makePatient();

        $this->actingAs($user)
            ->post(route('billing.store'), $this->invoicePayload($patient, [
                $this->line(['description' => 'Consultation fee', 'unit_price' => 500]),
            ]))
            ->assertRedirect();

        $this->assertSame(0, TreatmentVisitItem::whereNotNull('invoice_item_id')->count());
        $this->assertNull(InvoiceItem::firstOrFail()->treatment_id);
    }
}
