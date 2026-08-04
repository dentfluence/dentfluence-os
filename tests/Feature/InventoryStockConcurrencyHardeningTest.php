<?php

namespace Tests\Feature;

use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\InventoryStock;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Inventory module — stock update concurrency hardening (2026-08-04 P0 fix)
 * ─────────────────────────────────────────────────────────────────────────
 *  StockMovement::adjustStock() used to be a PHP read-modify-write
 *  ($stock->available_qty = max(0, $stock->available_qty + $delta);
 *  $stock->save();) — a lost-update race under concurrent writes. It's now
 *  a single atomic SQL UPDATE (GREATEST(0, available_qty + delta)), with a
 *  locked, race-safe fallback for first-ever-row creation.
 *
 *  A true multi-process race can't be reproduced inside a single synchronous
 *  PHPUnit process, so these tests instead prove: (a) the new atomic path
 *  computes the exact same correct totals as the old code for every normal
 *  sequence of movements (no regression), (b) first-row creation still
 *  works, (c) the non-negative floor still holds, and (d) the newly
 *  transaction-wrapped, lockForUpdate()'d availability checks in
 *  InventoryService still correctly reject over-removal.
 */
class InventoryStockConcurrencyHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function makeItemAndLocation(string $stamp): array
    {
        $item = InventoryItem::create([
            'product_name' => 'Concurrency Test Item ' . $stamp,
            'item_code'    => 'CONC-' . $stamp,
        ]);
        $location = InventoryLocation::create([
            'name' => 'Concurrency Store ' . $stamp,
            'code' => 'CONC-LOC-' . $stamp,
        ]);

        return [$item, $location];
    }

    public function test_first_movement_against_an_item_location_creates_the_stock_row_correctly(): void
    {
        $user  = User::factory()->create(['branch_id' => 1]);
        $stamp = now()->format('His') . rand(100, 999);
        [$item, $location] = $this->makeItemAndLocation($stamp);

        // No InventoryStock row exists yet — this exercises the fallback
        // insert path in the new atomic adjustStock().
        app(InventoryService::class)->createStockIn([
            'inventory_item_id' => $item->id,
            'to_location_id'    => $location->id,
            'qty'               => 10,
        ], $user);

        $this->assertDatabaseHas('inventory_stocks', [
            'inventory_item_id' => $item->id,
            'location_id'       => $location->id,
            'available_qty'     => 10,
        ]);
    }

    public function test_a_sequence_of_movements_nets_to_the_correct_total(): void
    {
        $user  = User::factory()->create(['branch_id' => 1]);
        $stamp = now()->format('His') . rand(100, 999);
        [$item, $location] = $this->makeItemAndLocation($stamp);
        $svc = app(InventoryService::class);

        // +10, -3, +5, -2 = 10
        $svc->createStockIn(['inventory_item_id' => $item->id, 'to_location_id' => $location->id, 'qty' => 10], $user);
        $svc->createStockOut(['inventory_item_id' => $item->id, 'from_location_id' => $location->id, 'qty' => 3, 'movement_type' => 'stock_out'], $user);
        $svc->createStockIn(['inventory_item_id' => $item->id, 'to_location_id' => $location->id, 'qty' => 5], $user);
        $svc->createStockOut(['inventory_item_id' => $item->id, 'from_location_id' => $location->id, 'qty' => 2, 'movement_type' => 'stock_out'], $user);

        $this->assertDatabaseHas('inventory_stocks', [
            'inventory_item_id' => $item->id,
            'location_id'       => $location->id,
            'available_qty'     => 10,
        ]);
    }

    public function test_stock_out_still_rejects_removing_more_than_available_after_locking_change(): void
    {
        $user  = User::factory()->create(['branch_id' => 1]);
        $stamp = now()->format('His') . rand(100, 999);
        [$item, $location] = $this->makeItemAndLocation($stamp);
        $svc = app(InventoryService::class);

        $svc->createStockIn(['inventory_item_id' => $item->id, 'to_location_id' => $location->id, 'qty' => 4], $user);

        $this->expectException(\RuntimeException::class);
        $svc->createStockOut([
            'inventory_item_id' => $item->id,
            'from_location_id'  => $location->id,
            'qty'               => 5,
            'movement_type'     => 'stock_out',
        ], $user);
    }

    public function test_manual_adjust_remove_still_rejects_removing_more_than_available_after_locking_change(): void
    {
        $user  = User::factory()->create(['branch_id' => 1]);
        $stamp = now()->format('His') . rand(100, 999);
        [$item, $location] = $this->makeItemAndLocation($stamp);
        $svc = app(InventoryService::class);

        InventoryStock::create([
            'inventory_item_id' => $item->id,
            'location_id'       => $location->id,
            'available_qty'     => 2,
        ]);

        $this->expectException(\RuntimeException::class);
        $svc->adjustStock($item, [
            'type'        => 'remove',
            'location_id' => $location->id,
            'qty'         => 3,
        ], $user);
    }

    public function test_available_qty_never_goes_negative_even_if_delta_would_overdraw(): void
    {
        $user  = User::factory()->create(['branch_id' => 1]);
        $stamp = now()->format('His') . rand(100, 999);
        [$item, $location] = $this->makeItemAndLocation($stamp);

        // implant deduction / treatment usage bypasses the qty>available
        // guard used by manual stock-out; confirm the floor in adjustStock()
        // itself still protects the row from ever reading negative.
        \App\Models\Inventory\StockMovement::create([
            'inventory_item_id' => $item->id,
            'movement_type'     => 'treatment_usage',
            'qty'               => 1,
            'from_location_id'  => $location->id,
            'created_by'        => $user->id,
        ]);

        $this->assertDatabaseHas('inventory_stocks', [
            'inventory_item_id' => $item->id,
            'location_id'       => $location->id,
            'available_qty'     => 0,
        ]);
    }
}
