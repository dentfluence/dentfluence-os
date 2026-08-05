<?php

namespace Tests\Feature\TreatmentVisits\Concerns;

use App\Models\Inventory\ImplantCatalog;
use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\InventoryLocation;
use App\Models\LabVendor;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\User;

/**
 * Shared fixture builders for the Treatment Visits Feature test suite.
 *
 * All required-field lists were verified against the actual migrations /
 * models (not guessed) as part of the 2026-08-05 Treatment Visits audit and
 * a follow-up schema-verification pass. See Treatment_Visits_Test_Report.docx
 * for the citation trail.
 */
trait BuildsVisitFixtures
{
    protected function makePatient(array $overrides = []): Patient
    {
        return Patient::create(array_merge([
            'name'  => 'Test Patient ' . uniqid(),
            'phone' => '9' . random_int(100000000, 999999999),
        ], $overrides));
    }

    protected function makeUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role'      => 'admin',
            'branch_id' => 1,
            'is_active' => true,
        ], $overrides));
    }

    /**
     * A committed (accepted) plan with one item, ready to be linked to a visit.
     */
    protected function makeAcceptedPlanWithItem(Patient $patient, string $treatmentName = 'RCT'): array
    {
        $plan = TreatmentPlan::create([
            'patient_id'  => $patient->id,
            'plan_type'   => 'best',
            'accepted_at' => now(),
        ]);

        $item = TreatmentPlanItem::create([
            'treatment_plan_id' => $plan->id,
            'treatment_name'    => $treatmentName,
        ]);

        return [$plan, $item];
    }

    protected function makeLabVendor(array $overrides = []): LabVendor
    {
        return LabVendor::create(array_merge([
            'name' => 'Dusk Lab ' . uniqid(),
        ], $overrides));
    }

    protected function makeInventoryLocation(array $overrides = []): InventoryLocation
    {
        return InventoryLocation::create(array_merge([
            'name'      => 'Implant Drawer ' . uniqid(),
            'code'      => 'LOC-' . uniqid(),
            'type'      => 'implant_drawer',
            'is_active' => true,
        ], $overrides));
    }

    protected function makeInventoryItem(array $overrides = []): InventoryItem
    {
        return InventoryItem::create(array_merge([
            'product_name' => 'Dusk Implant Fixture ' . uniqid(),
            'item_code'    => 'IMPL-' . uniqid(),
        ], $overrides));
    }

    protected function makeImplantCatalogEntry(InventoryItem $item, array $overrides = []): ImplantCatalog
    {
        return ImplantCatalog::create(array_merge([
            'brand'             => 'Dusk Brand',
            'system'            => 'Dusk System',
            'component_type'    => 'fixture',
            'inventory_item_id' => $item->id,
            'is_active'         => true,
        ], $overrides));
    }

    /** Minimal, otherwise-valid visit payload accepted by TreatmentVisitService::rules(). */
    protected function baseVisitPayload(array $overrides = []): array
    {
        return array_merge([
            'visit_date' => now()->format('Y-m-d'),
            'visit_type' => 'treatment',
            'status'     => 'completed',
        ], $overrides);
    }
}
