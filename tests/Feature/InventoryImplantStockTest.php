<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\InventoryStock;
use App\Models\Inventory\ImplantCatalog;
use App\Models\Inventory\ImplantPlacement;
use App\Models\Inventory\StockMovement;
use App\Services\PatientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Inventory module — Implant Registry stock correctness (P0-2 / P0-3)
 * ─────────────────────────────────────────────────────────────────────────
 *  Before the fix, placing an implant never touched inventory_stocks at
 *  all — every fixture/abutment placed on a real patient stayed counted as
 *  available stock forever, and a "failed"/"explanted" status change had
 *  no stock effect either (correct outcome, but an invisible no-op).
 */
class InventoryImplantStockTest extends TestCase
{
    use RefreshDatabase;

    private function makePatient(User $user): \App\Models\Patient
    {
        return app(PatientService::class)->register([
            'name'  => 'Implant Test Patient ' . now()->format('His') . rand(100, 999),
            'phone' => '9' . rand(100000000, 999999999),
        ], $user);
    }

    private function makeLinkedCatalogItem(string $stamp): array
    {
        $item = InventoryItem::create([
            'product_name' => 'Fixture ' . $stamp,
            'item_code'    => 'IMPL-TEST-' . $stamp,
        ]);
        $catalog = ImplantCatalog::create([
            'brand'              => 'TestBrand',
            'component_type'     => 'fixture',
            'inventory_item_id'  => $item->id,
            'product_code'       => 'FX-' . $stamp,
        ]);

        return [$item, $catalog];
    }

    public function test_placing_a_catalog_linked_implant_deducts_one_unit_of_stock(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\CheckModulePermission::class);
        $user  = User::factory()->create(['branch_id' => 1]);
        $stamp = now()->format('His') . rand(100, 999);

        [$item, $catalog] = $this->makeLinkedCatalogItem($stamp);
        $location = InventoryLocation::create(['name' => 'Implant Drawer', 'code' => 'IMPL-LOC-' . $stamp]);
        InventoryStock::create([
            'inventory_item_id' => $item->id,
            'location_id'       => $location->id,
            'available_qty'     => 5,
        ]);
        $patient = $this->makePatient($user);

        $resp = $this->actingAs($user)->post(route('inventory.implants.placements.store'), [
            'patient_id'   => $patient->id,
            'implant_catalog_id' => $catalog->id,
            'surgery_date' => today()->toDateString(),
            'status'       => 'placed',
        ]);
        $resp->assertSessionHasNoErrors();

        // Live stock dropped from 5 -> 4.
        $this->assertDatabaseHas('inventory_stocks', [
            'inventory_item_id' => $item->id,
            'location_id'       => $location->id,
            'available_qty'     => 4,
        ]);

        // A treatment_usage movement was created and linked back to the placement.
        $movement = StockMovement::where('inventory_item_id', $item->id)
            ->where('movement_type', 'treatment_usage')
            ->firstOrFail();
        $this->assertSame(1.0, (float) $movement->qty);

        $placement = ImplantPlacement::where('patient_id', $patient->id)->firstOrFail();
        $this->assertSame($movement->id, $placement->stock_movement_id);
    }

    public function test_placing_an_implant_with_zero_stock_is_rejected(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\CheckModulePermission::class);
        $user  = User::factory()->create(['branch_id' => 1]);
        $stamp = now()->format('His') . rand(100, 999);

        [$item, $catalog] = $this->makeLinkedCatalogItem($stamp);
        // No InventoryStock row created — zero stock anywhere.
        $patient = $this->makePatient($user);

        $resp = $this->actingAs($user)->post(route('inventory.implants.placements.store'), [
            'patient_id'          => $patient->id,
            'implant_catalog_id'  => $catalog->id,
            'surgery_date'        => today()->toDateString(),
            'status'              => 'placed',
        ]);

        $resp->assertSessionHasErrors('implant_catalog_id');
        // The whole placement is rolled back — not just the stock deduction.
        $this->assertSame(0, ImplantPlacement::where('patient_id', $patient->id)->count());
    }

    public function test_free_text_placement_with_no_catalog_link_is_unaffected(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\CheckModulePermission::class);
        $user  = User::factory()->create(['branch_id' => 1]);
        $patient = $this->makePatient($user);

        $resp = $this->actingAs($user)->post(route('inventory.implants.placements.store'), [
            'patient_id'             => $patient->id,
            'implant_brand_freetext' => 'Some Other Brand',
            'implant_code_freetext'  => 'XYZ-123',
            'surgery_date'           => today()->toDateString(),
            'status'                 => 'placed',
        ]);

        $resp->assertSessionHasNoErrors();
        $this->assertSame(1, ImplantPlacement::where('patient_id', $patient->id)->count());
        $this->assertSame(0, StockMovement::where('movement_type', 'treatment_usage')->count());
    }

    public function test_marking_a_placement_failed_does_not_return_stock_and_logs_a_system_note(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\CheckModulePermission::class);
        $user  = User::factory()->create(['branch_id' => 1]);
        $stamp = now()->format('His') . rand(100, 999);

        [$item, $catalog] = $this->makeLinkedCatalogItem($stamp);
        $location = InventoryLocation::create(['name' => 'Implant Drawer B', 'code' => 'IMPL-LOCB-' . $stamp]);
        InventoryStock::create([
            'inventory_item_id' => $item->id,
            'location_id'       => $location->id,
            'available_qty'     => 3,
        ]);
        $patient = $this->makePatient($user);

        $this->actingAs($user)->post(route('inventory.implants.placements.store'), [
            'patient_id'          => $patient->id,
            'implant_catalog_id'  => $catalog->id,
            'surgery_date'        => today()->toDateString(),
            'status'              => 'placed',
        ])->assertSessionHasNoErrors();

        // Stock is now 3 -> 2 after placement.
        $this->assertDatabaseHas('inventory_stocks', [
            'inventory_item_id' => $item->id, 'location_id' => $location->id, 'available_qty' => 2,
        ]);

        $placement = ImplantPlacement::where('patient_id', $patient->id)->firstOrFail();

        $resp = $this->actingAs($user)->put(route('inventory.implants.placements.update', $placement), [
            'status' => 'failed',
        ]);
        $resp->assertSessionHasNoErrors();

        // Stock is UNCHANGED by the failure — still 2, not returned to 3.
        $this->assertDatabaseHas('inventory_stocks', [
            'inventory_item_id' => $item->id, 'location_id' => $location->id, 'available_qty' => 2,
        ]);
        // No second stock movement was created for the failure.
        $this->assertSame(1, StockMovement::where('inventory_item_id', $item->id)->count());

        $placement->refresh();
        $this->assertSame('failed', $placement->status);
        $this->assertStringContainsString('not returned to stock', $placement->notes);
    }

    public function test_non_terminal_status_change_does_not_add_a_system_note(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\CheckModulePermission::class);
        $user  = User::factory()->create(['branch_id' => 1]);
        $stamp = now()->format('His') . rand(100, 999);

        [$item, $catalog] = $this->makeLinkedCatalogItem($stamp);
        $location = InventoryLocation::create(['name' => 'Implant Drawer C', 'code' => 'IMPL-LOCC-' . $stamp]);
        InventoryStock::create([
            'inventory_item_id' => $item->id,
            'location_id'       => $location->id,
            'available_qty'     => 2,
        ]);
        $patient = $this->makePatient($user);

        $this->actingAs($user)->post(route('inventory.implants.placements.store'), [
            'patient_id'          => $patient->id,
            'implant_catalog_id'  => $catalog->id,
            'surgery_date'        => today()->toDateString(),
            'status'              => 'placed',
        ])->assertSessionHasNoErrors();

        $placement = ImplantPlacement::where('patient_id', $patient->id)->firstOrFail();

        $this->actingAs($user)->put(route('inventory.implants.placements.update', $placement), [
            'status' => 'osseointegrating',
            'notes'  => 'Healing normally.',
        ])->assertSessionHasNoErrors();

        $placement->refresh();
        $this->assertSame('osseointegrating', $placement->status);
        $this->assertSame('Healing normally.', $placement->notes);
    }
}
