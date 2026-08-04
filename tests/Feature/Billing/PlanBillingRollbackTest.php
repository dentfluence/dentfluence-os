<?php

namespace Tests\Feature\Billing;

use App\Models\Invoice;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\TreatmentPlanItemTooth;
use App\Models\User;
use App\Services\Billing\PlanBillingRollbackService;
use App\Services\Billing\TreatmentPlanBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * S1 (CEO Directive #006) — BILLING TRUTH & ROLLBACK.
 *
 * The invariant this slice must never break:
 *
 *   A tooth is 'invoiced' if and only if a LIVE invoice line is billing it.
 *
 * Before S1 that was one-directional. Billing could set 'invoiced'; nothing
 * could ever unset it. Cancelling, deleting or re-editing an invoice left the
 * teeth stranded — permanently unbillable through the UI — and left plans
 * closed on top of voided invoices.
 */
class PlanBillingRollbackTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    private function billing(): TreatmentPlanBillingService
    {
        return app(TreatmentPlanBillingService::class);
    }

    private function rollback(): PlanBillingRollbackService
    {
        return app(PlanBillingRollbackService::class);
    }

    private function patient(): Patient
    {
        return Patient::create([
            'name'      => 'Billing Rollback Patient',
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ]);
    }

    /**
     * An ACCEPTED plan with one two-tooth item — the shape that exposes partial
     * billing (bill tooth 24 now, tooth 36 next visit).
     */
    private function acceptedPlan(?Patient $patient = null, string $teeth = '24, 36'): TreatmentPlan
    {
        $patient ??= $this->patient();

        $plan = TreatmentPlan::create([
            'patient_id'  => $patient->id,
            'plan_name'   => 'Implant Plan',
            'status'      => 'ongoing',
            'accepted_at' => now(),
            'rows'        => [],
            'total'       => 90000,
        ]);

        TreatmentPlanItem::create([
            'treatment_plan_id' => $plan->id,
            'treatment_name'    => 'Implant',
            'tooth_number'      => $teeth,
            'unit_price'        => 45000,
            'units'             => 2,
            'total'             => 90000,
        ]);

        return $plan->fresh('items');
    }

    private function toothIds(TreatmentPlan $plan): array
    {
        $this->billing()->ensurePlanTeeth($plan);

        return TreatmentPlanItemTooth::whereIn(
            'treatment_plan_item_id',
            $plan->items()->pluck('id')
        )->orderBy('id')->pluck('id')->all();
    }

    // ── 1. Cancel / delete an invoice → teeth released, plan reopened ────────

    public function test_rolling_back_an_invoice_releases_its_teeth_and_reopens_the_plan(): void
    {
        $plan = $this->acceptedPlan();
        $ids  = $this->toothIds($plan);

        $invoice = $this->billing()->createInvoiceFromSelection($plan, $ids);

        // F1 — billing records a financial event and nothing else. Invoicing
        // every tooth does NOT complete the plan; completion is clinical.
        $this->assertSame('ongoing', $plan->fresh()->status);
        $this->assertSame(
            TreatmentPlanItem::PROGRESS_INVOICED,
            $plan->items()->first()->fresh()->billing_progress
        );

        $this->rollback()->rollbackInvoice($invoice);

        $teeth = TreatmentPlanItemTooth::whereIn('id', $ids)->get();
        $this->assertCount(2, $teeth);

        foreach ($teeth as $tooth) {
            $this->assertSame(TreatmentPlanItemTooth::STATUS_PENDING, $tooth->status);
            $this->assertNull($tooth->invoice_item_id);
            $this->assertNull($tooth->invoiced_at);
        }

        $item = $plan->items()->first()->fresh();
        $this->assertSame(0, (int) $item->invoiced_units);
        $this->assertSame(TreatmentPlanItem::PROGRESS_PENDING, $item->billing_progress);

        // Accepted plan reopens to 'ongoing', never back past acceptance.
        $plan->refresh();
        $this->assertSame('ongoing', $plan->status);
        $this->assertNotNull($plan->accepted_at);
    }

    public function test_released_teeth_can_be_billed_again(): void
    {
        $plan = $this->acceptedPlan();
        $ids  = $this->toothIds($plan);

        $first = $this->billing()->createInvoiceFromSelection($plan, $ids);
        $this->rollback()->rollbackInvoice($first);

        // The whole point: the clinic can re-invoice work whose invoice was voided.
        $second = $this->billing()->createInvoiceFromSelection($plan->fresh(), $ids);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, TreatmentPlanItemTooth::whereIn('id', $ids)
            ->where('status', TreatmentPlanItemTooth::STATUS_INVOICED)->count());
    }

    public function test_partial_rollback_moves_the_item_back_to_partially_completed(): void
    {
        $plan = $this->acceptedPlan();
        $ids  = $this->toothIds($plan);

        // Bill both teeth on two separate invoices, then void only the first.
        $invoiceA = $this->billing()->createInvoiceFromSelection($plan, [$ids[0]]);
        $invoiceB = $this->billing()->createInvoiceFromSelection($plan->fresh(), [$ids[1]]);

        $this->rollback()->rollbackInvoice($invoiceA);

        $item = $plan->items()->first()->fresh();
        $this->assertSame(1, (int) $item->invoiced_units);
        $this->assertSame(TreatmentPlanItem::PROGRESS_PARTIAL, $item->billing_progress);
        $this->assertSame('ongoing', $plan->fresh()->status);

        // Invoice B's tooth is untouched — rollback is scoped to the voided invoice.
        $this->assertSame(
            TreatmentPlanItemTooth::STATUS_INVOICED,
            TreatmentPlanItemTooth::find($ids[1])->status
        );
        $this->assertNotNull($invoiceB->fresh());
    }

    public function test_rollback_of_an_invoice_with_no_plan_linkage_is_a_no_op(): void
    {
        $patient = $this->patient();

        $invoice = Invoice::create([
            'invoice_number' => Invoice::nextNumber(),
            'patient_id'     => $patient->id,
            'invoice_date'   => now()->toDateString(),
            'status'         => 'draft',
        ]);

        // Must not throw — every cancel path calls this unconditionally.
        $this->rollback()->rollbackInvoice($invoice);

        $this->assertSame('draft', $invoice->fresh()->status);
    }

    // ── 2. Invoice line replacement (BillingController@update shape) ─────────

    public function test_rolling_back_specific_invoice_lines_releases_only_their_teeth(): void
    {
        $plan = $this->acceptedPlan();
        $ids  = $this->toothIds($plan);

        $invoice = $this->billing()->createInvoiceFromSelection($plan, $ids);
        $lines   = $invoice->items()->get();

        $this->rollback()->rollbackInvoiceItems($lines);

        $this->assertSame(0, TreatmentPlanItemTooth::whereIn('id', $ids)
            ->where('status', TreatmentPlanItemTooth::STATUS_INVOICED)->count());
        $this->assertSame(TreatmentPlanItem::PROGRESS_PENDING,
            $plan->items()->first()->fresh()->billing_progress);
        $this->assertSame('ongoing', $plan->fresh()->status);
    }

    // ── 3. Double-billing protection ────────────────────────────────────────

    public function test_billing_the_same_teeth_twice_is_rejected_and_creates_no_second_invoice(): void
    {
        $plan = $this->acceptedPlan();
        $ids  = $this->toothIds($plan);

        $this->billing()->createInvoiceFromSelection($plan, $ids);
        $invoiceCount = Invoice::count();

        $this->expectException(ValidationException::class);

        try {
            $this->billing()->createInvoiceFromSelection($plan->fresh(), $ids);
        } finally {
            // The critical assertion: no second invoice was left behind.
            $this->assertSame($invoiceCount, Invoice::count());
            $this->assertSame(2, TreatmentPlanItemTooth::whereIn('id', $ids)
                ->where('status', TreatmentPlanItemTooth::STATUS_INVOICED)->count());
        }
    }

    public function test_a_selection_mixing_pending_and_already_invoiced_teeth_bills_only_the_pending_one(): void
    {
        $plan = $this->acceptedPlan();
        $ids  = $this->toothIds($plan);

        $this->billing()->createInvoiceFromSelection($plan, [$ids[0]]);

        // Re-submitting BOTH ids must bill only the still-pending tooth, and
        // must never re-point the already-invoiced tooth at the new line.
        $before  = TreatmentPlanItemTooth::find($ids[0])->invoice_item_id;
        $invoice = $this->billing()->createInvoiceFromSelection($plan->fresh(), $ids);

        $this->assertSame($before, TreatmentPlanItemTooth::find($ids[0])->invoice_item_id);
        $this->assertEquals(1, $invoice->items()->sum('qty'));
    }

    // ── 4. ensureTeeth idempotency ──────────────────────────────────────────

    public function test_ensure_teeth_is_idempotent_and_never_duplicates_rows(): void
    {
        $plan = $this->acceptedPlan();
        $item = $plan->items()->first();

        $this->billing()->ensureTeeth($item);
        $this->billing()->ensureTeeth($item->fresh());
        $this->billing()->ensurePlanTeeth($plan->fresh());

        $this->assertSame(2, TreatmentPlanItemTooth::where('treatment_plan_item_id', $item->id)->count());
    }

    public function test_ensure_teeth_creates_one_generic_row_per_unit_for_a_non_tooth_item(): void
    {
        $patient = $this->patient();

        $plan = TreatmentPlan::create([
            'patient_id'  => $patient->id,
            'plan_name'   => 'Cleaning Package',
            'status'      => 'ongoing',
            'accepted_at' => now(),
            'rows'        => [],
            'total'       => 3000,
        ]);

        $item = TreatmentPlanItem::create([
            'treatment_plan_id' => $plan->id,
            'treatment_name'    => 'Scaling',
            'tooth_number'      => null,
            'unit_price'        => 1000,
            'units'             => 3,
            'total'             => 3000,
        ]);

        $this->billing()->ensureTeeth($item);
        $this->billing()->ensureTeeth($item->fresh());

        // Generic rows carry NULL tooth_number; the unique index must not
        // collapse them (NULLs are distinct in both MySQL and SQLite).
        $this->assertSame(3, TreatmentPlanItemTooth::where('treatment_plan_item_id', $item->id)->count());
    }

    // ── 5. Billing authorization: an unaccepted plan cannot be invoiced ──────

    public function test_web_store_from_plan_rejects_an_unaccepted_plan(): void
    {
        $patient = $this->patient();

        $plan = TreatmentPlan::create([
            'patient_id' => $patient->id,
            'plan_name'  => 'Quotation Only',
            'status'     => 'pending',
            'rows'       => [],
            'total'      => 45000,
        ]);

        TreatmentPlanItem::create([
            'treatment_plan_id' => $plan->id,
            'treatment_name'    => 'Implant',
            'tooth_number'      => '24',
            'unit_price'        => 45000,
            'units'             => 1,
            'total'             => 45000,
        ]);

        $ids  = $this->toothIds($plan->fresh('items'));
        $user = $this->userWithModulePerm('finance', true, true, false);

        $this->actingAs($this->fresh($user))
            ->post(route('billing.storeFromPlan', $plan), ['tooth_ids' => $ids])
            ->assertSessionHasErrors('tooth_ids');

        $this->assertSame(0, Invoice::where('treatment_plan_id', $plan->id)->count());
    }

    public function test_api_bill_rejects_an_unaccepted_plan(): void
    {
        $patient = $this->patient();

        $plan = TreatmentPlan::create([
            'patient_id' => $patient->id,
            'plan_name'  => 'Quotation Only',
            'status'     => 'pending',
            'rows'       => [],
            'total'      => 45000,
        ]);

        TreatmentPlanItem::create([
            'treatment_plan_id' => $plan->id,
            'treatment_name'    => 'Implant',
            'tooth_number'      => '24',
            'unit_price'        => 45000,
            'units'             => 1,
            'total'             => 45000,
        ]);

        $ids  = $this->toothIds($plan->fresh('items'));
        $user = $this->userWithTwoModulePerms('finance', [true, true, false], 'patients', [true, true, false]);

        Sanctum::actingAs($this->fresh($user));

        $this->postJson('/api/v1/treatment-plans/' . $plan->id . '/bill', ['tooth_ids' => $ids])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This plan must be accepted before billing.');

        $this->assertSame(0, Invoice::where('treatment_plan_id', $plan->id)->count());
    }

    public function test_an_accepted_plan_still_bills_normally_from_the_web_route(): void
    {
        $plan = $this->acceptedPlan();
        $ids  = $this->toothIds($plan);
        $user = $this->userWithModulePerm('finance', true, true, false);

        $this->actingAs($this->fresh($user))
            ->post(route('billing.storeFromPlan', $plan), ['tooth_ids' => $ids])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Invoice::where('treatment_plan_id', $plan->id)->count());
    }

    public function test_finance_view_only_user_cannot_create_an_invoice_from_a_plan(): void
    {
        $plan = $this->acceptedPlan();
        $ids  = $this->toothIds($plan);

        // view = true, edit = false — creating an invoice is a write.
        $user = $this->userWithModulePerm('finance', true, false, false);

        $this->actingAs($this->fresh($user))
            ->post(route('billing.storeFromPlan', $plan), ['tooth_ids' => $ids]);

        $this->assertSame(0, Invoice::where('treatment_plan_id', $plan->id)->count());
    }

    // ── 6. Integrity checker ────────────────────────────────────────────────

    public function test_integrity_check_passes_on_healthy_data(): void
    {
        $plan = $this->acceptedPlan();
        $this->billing()->createInvoiceFromSelection($plan, $this->toothIds($plan));

        $this->artisan('billing:integrity-check')->assertExitCode(0);
    }

    public function test_integrity_check_detects_a_stranded_invoiced_tooth(): void
    {
        $plan    = $this->acceptedPlan();
        $ids     = $this->toothIds($plan);
        $invoice = $this->billing()->createInvoiceFromSelection($plan, $ids);

        // Simulate the pre-S1 damage: invoice cancelled with no rollback.
        $invoice->update(['status' => 'cancelled']);
        $invoice->delete();

        $this->artisan('billing:integrity-check')->assertExitCode(1);
    }

    public function test_integrity_check_is_read_only(): void
    {
        $plan    = $this->acceptedPlan();
        $ids     = $this->toothIds($plan);
        $invoice = $this->billing()->createInvoiceFromSelection($plan, $ids);

        $invoice->update(['status' => 'cancelled']);
        $invoice->delete();

        $before = TreatmentPlanItemTooth::whereIn('id', $ids)->get()
            ->map(fn ($t) => [$t->status, $t->invoice_item_id, (string) $t->invoiced_at])->all();

        $this->artisan('billing:integrity-check')->assertExitCode(1);

        $after = TreatmentPlanItemTooth::whereIn('id', $ids)->get()
            ->map(fn ($t) => [$t->status, $t->invoice_item_id, (string) $t->invoiced_at])->all();

        $this->assertSame($before, $after, 'billing:integrity-check must never modify data.');
    }
}
