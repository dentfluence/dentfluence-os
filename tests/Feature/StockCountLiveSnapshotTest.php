<?php

namespace Tests\Feature;

use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\InventoryStock;
use App\Models\Inventory\StockCountLine;
use App\Models\Inventory\StockCountSession;
use App\Models\Inventory\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Inventory module — Stock Count stale-snapshot fix (2026-08-04 P0 fix)
 * ─────────────────────────────────────────────────────────────────────────
 *  StockCountController::complete() used to trust the variance computed
 *  against the session-start system_qty snapshot. If normal stock-in/out
 *  happened on an item while the count session was still open, completing
 *  the count applied a corrupted adjustment (real variance + whatever moved
 *  during the count window) instead of correcting stock to the counted
 *  physical quantity. complete() now re-reads live stock at completion time
 *  as the baseline.
 */
class StockCountLiveSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_completion_uses_live_stock_not_the_stale_session_start_snapshot(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\CheckModulePermission::class);

        $user  = User::factory()->create(['branch_id' => 1]);
        $stamp = now()->format('His') . rand(100, 999);

        $item = InventoryItem::create([
            'product_name' => 'Stale Snapshot Item ' . $stamp,
            'item_code'    => 'STALE-' . $stamp,
            'minimum_qty'  => 1,
        ]);
        $location = InventoryLocation::create([
            'name' => 'Main Store',
            'code' => 'MAIN-STORE',
        ]);

        // System said 10 at session start.
        InventoryStock::create([
            'inventory_item_id' => $item->id,
            'location_id'       => $location->id,
            'available_qty'     => 10,
        ]);

        $session = StockCountSession::create([
            'session_no' => 'SCS-STALE-' . now()->format('His'),
            'count_date' => now()->toDateString(),
            'status'     => 'in_progress',
            'started_by' => $user->id,
        ]);

        $line = StockCountLine::create([
            'session_id'        => $session->id,
            'inventory_item_id' => $item->id,
            'category_name'     => 'Uncategorised',
            'product_name'      => $item->product_name,
            'system_qty'        => 10,   // stale — session-start snapshot
            'physical_qty'      => 9,    // staff physically counted 9
            'variance'          => -1,   // computed against the STALE snapshot at save() time
            'minimum_qty'       => 1,
            'reorder_level'     => 2,
        ]);

        // While the count session is still open, a normal stock-in of +5
        // happens (e.g. a GRN receipt). Live stock is now 15, but the
        // line's stored variance (-1) doesn't know that yet.
        StockMovement::create([
            'inventory_item_id' => $item->id,
            'movement_type'     => 'stock_in',
            'qty'               => 5,
            'to_location_id'    => $location->id,
            'created_by'        => $user->id,
        ]);
        $this->assertDatabaseHas('inventory_stocks', [
            'inventory_item_id' => $item->id, 'available_qty' => 15,
        ]);

        $resp = $this->actingAs($user)
            ->post(route('inventory.stock-count.complete', $session));
        $resp->assertSessionHasNoErrors();

        // The physical count (9) is the ground truth. Completion must
        // correct live stock to exactly 9 — NOT apply the stale -1 to the
        // post-stock-in total (which would have wrongly left it at 14).
        $this->assertDatabaseHas('inventory_stocks', [
            'inventory_item_id' => $item->id,
            'location_id'       => $location->id,
            'available_qty'     => 9,
        ]);
        $this->assertDatabaseMissing('inventory_stocks', [
            'inventory_item_id' => $item->id,
            'available_qty'     => 14,
        ]);

        // The adjustment movement itself must be signed -6 (15 -> 9), not
        // the stale -1.
        $this->assertDatabaseHas('stock_movements', [
            'inventory_item_id' => $item->id,
            'movement_type'     => 'adjustment',
            'qty'               => -6,
            'reference_type'    => StockCountSession::class,
            'reference_id'      => $session->id,
        ]);

        // The line record itself should reflect the corrected figures, not
        // the stale ones, for an accurate audit trail.
        $line->refresh();
        $this->assertSame(15.0, $line->system_qty);
        $this->assertSame(-6.0, $line->variance);
    }

    public function test_completion_still_creates_no_movement_when_live_stock_matches_physical_count(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\CheckModulePermission::class);

        $user  = User::factory()->create(['branch_id' => 1]);
        $stamp = now()->format('His') . rand(100, 999);

        $item = InventoryItem::create([
            'product_name' => 'Stale Snapshot Match Item ' . $stamp,
            'item_code'    => 'STALE-EQ-' . $stamp,
            'minimum_qty'  => 1,
        ]);
        $location = InventoryLocation::create([
            'name' => 'Main Store',
            'code' => 'MAIN-STORE',
        ]);
        InventoryStock::create([
            'inventory_item_id' => $item->id,
            'location_id'       => $location->id,
            'available_qty'     => 5,
        ]);

        $session = StockCountSession::create([
            'session_no' => 'SCS-STALE-EQ-' . now()->format('His'),
            'count_date' => now()->toDateString(),
            'status'     => 'in_progress',
            'started_by' => $user->id,
        ]);

        // Stale snapshot said 3 with a +2 variance, but live stock (5) now
        // matches the physical count exactly — no movement should fire.
        StockCountLine::create([
            'session_id'        => $session->id,
            'inventory_item_id' => $item->id,
            'category_name'     => 'Uncategorised',
            'product_name'      => $item->product_name,
            'system_qty'        => 3,
            'physical_qty'      => 5,
            'variance'          => 2,
            'minimum_qty'       => 1,
            'reorder_level'     => 2,
        ]);

        $resp = $this->actingAs($user)
            ->post(route('inventory.stock-count.complete', $session));
        $resp->assertSessionHasNoErrors();

        $session->refresh();
        $this->assertSame(0, $session->items_adjusted);
        $this->assertSame(0, StockMovement::where('inventory_item_id', $item->id)->count());
    }
}
