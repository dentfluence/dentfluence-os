<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\InventoryStock;
use App\Models\Inventory\StockCountSession;
use App\Models\Inventory\StockCountLine;
use App\Models\Inventory\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Inventory module — Stock Count completion (P0-1 hardening fix)
 * ─────────────────────────────────────────────────────────────────────────
 *  Before the fix, StockCountController::complete() wrote
 *  movement_type => 'stock_adjustment', which is not a valid value in the
 *  stock_movements.movement_type ENUM (only 'adjustment' exists) and is
 *  not recognised by StockMovement::updateLiveStock(). Finalising a count
 *  with any variance therefore either threw a DB error (strict SQL mode)
 *  or silently left inventory_stocks untouched while still marking the
 *  session 'completed' (lenient SQL mode).
 *
 *  These tests prove: (a) completion succeeds without a DB error, (b) the
 *  live stock quantity is actually corrected in both directions, and
 *  (c) the ledger records the correction as 'adjustment' with a signed qty.
 */
class StockCountCompletionTest extends TestCase
{
    use RefreshDatabase;

    private function makeSession(): StockCountSession
    {
        return StockCountSession::create([
            'session_no' => 'SCS-TEST-' . now()->format('His'),
            'count_date' => now()->toDateString(),
            'status'     => 'in_progress',
            'started_by' => auth()->id(),
        ]);
    }

    public function test_completing_a_count_with_a_positive_variance_increases_live_stock(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\CheckModulePermission::class);

        $user = User::factory()->create(['branch_id' => 1]);
        $stamp = now()->format('His') . rand(100, 999);

        $item = InventoryItem::create([
            'product_name' => 'Count Item Up ' . $stamp,
            'item_code'    => 'CNT-UP-' . $stamp,
            'minimum_qty'  => 2,
        ]);
        $location = InventoryLocation::create([
            'name' => 'Main Store',
            'code' => 'MAIN-STORE',
        ]);
        // System says 5 on hand.
        InventoryStock::create([
            'inventory_item_id' => $item->id,
            'location_id'       => $location->id,
            'available_qty'     => 5,
        ]);

        $session = $this->makeSession();
        $line = StockCountLine::create([
            'session_id'        => $session->id,
            'inventory_item_id' => $item->id,
            'category_name'     => 'Uncategorised',
            'product_name'      => $item->product_name,
            'system_qty'        => 5,
            'physical_qty'      => 8,   // staff counted 8 on the shelf
            'variance'          => 3,   // +3
            'minimum_qty'       => 2,
            'reorder_level'     => 3,
        ]);

        $resp = $this->actingAs($user)
            ->post(route('inventory.stock-count.complete', $session));
        $resp->assertSessionHasNoErrors();

        $session->refresh();
        $this->assertSame('completed', $session->status);
        $this->assertSame(1, $session->items_adjusted);

        // Live stock corrected from 5 -> 8.
        $this->assertDatabaseHas('inventory_stocks', [
            'inventory_item_id' => $item->id,
            'location_id'       => $location->id,
            'available_qty'     => 8,
        ]);

        // Ledger records a valid, signed 'adjustment' entry (not the old
        // invalid 'stock_adjustment' value).
        $this->assertDatabaseHas('stock_movements', [
            'inventory_item_id' => $item->id,
            'movement_type'     => 'adjustment',
            'qty'               => 3,
            'to_location_id'    => $location->id,
            'reference_type'    => StockCountSession::class,
            'reference_id'      => $session->id,
        ]);

        $line->refresh();
        $this->assertNotNull($line->stock_movement_id);
    }

    public function test_completing_a_count_with_a_negative_variance_decreases_live_stock(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\CheckModulePermission::class);

        $user = User::factory()->create(['branch_id' => 1]);
        $stamp = now()->format('His') . rand(100, 999);

        $item = InventoryItem::create([
            'product_name' => 'Count Item Down ' . $stamp,
            'item_code'    => 'CNT-DN-' . $stamp,
            'minimum_qty'  => 2,
        ]);
        $location = InventoryLocation::create([
            'name' => 'Main Store',
            'code' => 'MAIN-STORE',
        ]);
        // System says 10 on hand, but only 6 are really on the shelf (shrinkage).
        InventoryStock::create([
            'inventory_item_id' => $item->id,
            'location_id'       => $location->id,
            'available_qty'     => 10,
        ]);

        $session = $this->makeSession();
        StockCountLine::create([
            'session_id'        => $session->id,
            'inventory_item_id' => $item->id,
            'category_name'     => 'Uncategorised',
            'product_name'      => $item->product_name,
            'system_qty'        => 10,
            'physical_qty'      => 6,
            'variance'          => -4,
            'minimum_qty'       => 2,
            'reorder_level'     => 3,
        ]);

        $resp = $this->actingAs($user)
            ->post(route('inventory.stock-count.complete', $session));
        $resp->assertSessionHasNoErrors();

        // Live stock corrected from 10 -> 6.
        $this->assertDatabaseHas('inventory_stocks', [
            'inventory_item_id' => $item->id,
            'location_id'       => $location->id,
            'available_qty'     => 6,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'inventory_item_id' => $item->id,
            'movement_type'     => 'adjustment',
            'qty'               => -4,
            'from_location_id'  => $location->id,
        ]);
    }

    public function test_completing_a_count_with_zero_variance_creates_no_movement(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\CheckModulePermission::class);

        $user = User::factory()->create(['branch_id' => 1]);
        $stamp = now()->format('His') . rand(100, 999);

        $item = InventoryItem::create([
            'product_name' => 'Count Item Match ' . $stamp,
            'item_code'    => 'CNT-EQ-' . $stamp,
            'minimum_qty'  => 2,
        ]);
        $location = InventoryLocation::create([
            'name' => 'Main Store',
            'code' => 'MAIN-STORE',
        ]);
        InventoryStock::create([
            'inventory_item_id' => $item->id,
            'location_id'       => $location->id,
            'available_qty'     => 4,
        ]);

        $session = $this->makeSession();
        StockCountLine::create([
            'session_id'        => $session->id,
            'inventory_item_id' => $item->id,
            'category_name'     => 'Uncategorised',
            'product_name'      => $item->product_name,
            'system_qty'        => 4,
            'physical_qty'      => 4,
            'variance'          => 0,
            'minimum_qty'       => 2,
            'reorder_level'     => 3,
        ]);

        $resp = $this->actingAs($user)
            ->post(route('inventory.stock-count.complete', $session));
        $resp->assertSessionHasNoErrors();

        $session->refresh();
        $this->assertSame('completed', $session->status);
        $this->assertSame(0, $session->items_adjusted);
        $this->assertSame(0, StockMovement::where('inventory_item_id', $item->id)->count());
    }
}
