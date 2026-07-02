<?php

namespace Tests\Feature;

use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Inventory module — Stock movement → live stock (core math)
 * ─────────────────────────────────────────────────────────────────────────
 *
 *  WHAT THIS CHECKS (plain language):
 *  Recording a "stock in" movement must automatically increase the live
 *  stock for that item at that location. This is the heart of inventory —
 *  if it breaks, every stock count is wrong.
 */
class InventoryStockMovementTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_in_movement_increases_live_stock(): void
    {
        $item     = InventoryItem::create([
            'product_name' => 'Dusk Gloves',
            'item_code'    => 'DUSK-' . now()->format('His'),
        ]);
        $location = InventoryLocation::create([
            'name' => 'Dusk Store',
            'code' => 'DUSK-LOC-' . now()->format('His'),
        ]); // type defaults to 'storage'

        StockMovement::create([
            'inventory_item_id' => $item->id,
            'movement_type'     => 'stock_in',
            'qty'               => 10,
            'to_location_id'    => $location->id,
        ]);

        // The StockMovement "created" hook should have updated live stock.
        $this->assertDatabaseHas('inventory_stocks', [
            'inventory_item_id' => $item->id,
            'location_id'       => $location->id,
            'available_qty'     => 10,
        ]);

        // And a second stock-in should add to it.
        StockMovement::create([
            'inventory_item_id' => $item->id,
            'movement_type'     => 'stock_in',
            'qty'               => 5,
            'to_location_id'    => $location->id,
        ]);

        $this->assertDatabaseHas('inventory_stocks', [
            'inventory_item_id' => $item->id,
            'location_id'       => $location->id,
            'available_qty'     => 15,
        ]);
    }
}
