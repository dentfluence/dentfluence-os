<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\PurchaseOrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Inventory module — Purchase Order receive → GRN + stock + AP (the chain)
 * ─────────────────────────────────────────────────────────────────────────
 *
 *  WHAT THIS CHECKS (plain language):
 *  Receiving a purchase order should automatically:
 *    1. create a Goods Receipt Note (GRN),
 *    2. add the received quantity to live stock, and
 *    3. create an unpaid finance expense (accounts-payable entry).
 */
class InventoryPurchaseReceiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_receiving_a_po_creates_grn_stock_and_payable(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\CheckModulePermission::class);

        $user = User::factory()->create(['branch_id' => 1]);
        $stamp = now()->format('His');

        $item = InventoryItem::create([
            'product_name' => 'Dusk Mask',
            'item_code'    => 'DUSK-IT-' . $stamp,
        ]);
        $location = InventoryLocation::create([
            'name' => 'Dusk Store',
            'code' => 'DUSK-LOC-' . $stamp,
        ]);
        $po = PurchaseOrder::create([
            'order_no'   => 'DUSK-PO-' . $stamp,
            'order_date' => today()->toDateString(),
        ]);
        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'inventory_item_id' => $item->id,
            'qty_ordered'       => 8,
            'unit_price'        => 100,   // so the payable amount is > 0
        ]);

        // Receive all 8 units.
        $resp = $this->actingAs($user)->post(route('inventory.purchase.receive', $po), [
            'location_id'   => $location->id,
            'received_date' => today()->toDateString(),
            'lines'         => [
                ['item_id' => $item->id, 'qty' => 8],
            ],
        ]);
        $resp->assertSessionHasNoErrors();

        // 1. GRN created
        $this->assertDatabaseHas('goods_receipt_notes', ['purchase_order_id' => $po->id]);
        $grn = \App\Models\Procurement\GoodsReceiptNote::where('purchase_order_id', $po->id)->firstOrFail();

        // 2. Live stock increased
        $this->assertDatabaseHas('inventory_stocks', [
            'inventory_item_id' => $item->id,
            'location_id'       => $location->id,
            'available_qty'     => 8,
        ]);

        // 3. Accounts-payable (unpaid finance expense) is created against the GRN
        //    (the payable arises when goods are RECEIVED, so it is tied to the
        //    Goods Receipt Note, not the Purchase Order).
        $this->assertDatabaseHas('finance_expenses', [
            'source_type'    => \App\Models\Procurement\GoodsReceiptNote::class,
            'source_id'      => $grn->id,
            'payment_status' => 'unpaid',
        ]);
    }
}
