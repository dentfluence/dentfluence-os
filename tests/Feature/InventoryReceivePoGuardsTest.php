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
 *  Inventory module — PO status guard + over-receive cap (P0-5 hardening fix)
 * ─────────────────────────────────────────────────────────────────────────
 *  Before the fix, receivePO() never checked the PO's status or whether a
 *  line's quantity exceeded what was still outstanding — a completed or
 *  cancelled PO could be received against again, and any quantity could be
 *  entered regardless of how much was actually ordered, each time posting
 *  another Finance bill.
 */
class InventoryReceivePoGuardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_completed_po_cannot_be_received_against_again(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\CheckModulePermission::class);
        $user = User::factory()->create(['branch_id' => 1]);
        $stamp = now()->format('His') . rand(100, 999);

        $item = InventoryItem::create([
            'product_name' => 'Guard Item ' . $stamp,
            'item_code'    => 'GRD-' . $stamp,
        ]);
        $location = InventoryLocation::create(['name' => 'Guard Store', 'code' => 'GRD-LOC-' . $stamp]);
        $po = PurchaseOrder::create([
            'order_no'   => 'GRD-PO-' . $stamp,
            'order_date' => today()->toDateString(),
            'status'     => 'completed', // already fully received
        ]);
        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'inventory_item_id' => $item->id,
            'qty_ordered'       => 5,
            'qty_received'      => 5,
            'unit_price'        => 20,
        ]);

        $resp = $this->actingAs($user)->post(route('inventory.purchase.receive', $po), [
            'location_id'   => $location->id,
            'received_date' => today()->toDateString(),
            'lines'         => [['item_id' => $item->id, 'qty' => 2]],
        ]);

        $resp->assertSessionHasErrors('lines');
        $this->assertDatabaseCount('goods_receipt_notes', 0);
        $this->assertDatabaseCount('finance_expenses', 0);
    }

    public function test_a_cancelled_po_cannot_be_received_against(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\CheckModulePermission::class);
        $user = User::factory()->create(['branch_id' => 1]);
        $stamp = now()->format('His') . rand(100, 999);

        $item = InventoryItem::create([
            'product_name' => 'Guard Item B ' . $stamp,
            'item_code'    => 'GRDB-' . $stamp,
        ]);
        $location = InventoryLocation::create(['name' => 'Guard Store B', 'code' => 'GRDB-LOC-' . $stamp]);
        $po = PurchaseOrder::create([
            'order_no'   => 'GRDB-PO-' . $stamp,
            'order_date' => today()->toDateString(),
            'status'     => 'cancelled',
        ]);
        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'inventory_item_id' => $item->id,
            'qty_ordered'       => 5,
            'unit_price'        => 20,
        ]);

        $resp = $this->actingAs($user)->post(route('inventory.purchase.receive', $po), [
            'location_id'   => $location->id,
            'received_date' => today()->toDateString(),
            'lines'         => [['item_id' => $item->id, 'qty' => 1]],
        ]);

        $resp->assertSessionHasErrors('lines');
        $this->assertDatabaseCount('goods_receipt_notes', 0);
    }

    public function test_cannot_receive_more_than_the_outstanding_quantity(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\CheckModulePermission::class);
        $user = User::factory()->create(['branch_id' => 1]);
        $stamp = now()->format('His') . rand(100, 999);

        $item = InventoryItem::create([
            'product_name' => 'Guard Item C ' . $stamp,
            'item_code'    => 'GRDC-' . $stamp,
        ]);
        $location = InventoryLocation::create(['name' => 'Guard Store C', 'code' => 'GRDC-LOC-' . $stamp]);
        $po = PurchaseOrder::create([
            'order_no'   => 'GRDC-PO-' . $stamp,
            'order_date' => today()->toDateString(),
            'status'     => 'ordered',
        ]);
        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'inventory_item_id' => $item->id,
            'qty_ordered'       => 5,
            'qty_received'      => 3, // only 2 outstanding
            'unit_price'        => 20,
        ]);

        $resp = $this->actingAs($user)->post(route('inventory.purchase.receive', $po), [
            'location_id'   => $location->id,
            'received_date' => today()->toDateString(),
            'lines'         => [['item_id' => $item->id, 'qty' => 10]], // way over
        ]);

        $resp->assertSessionHasErrors('lines');
        $this->assertDatabaseCount('goods_receipt_notes', 0);

        // Receiving exactly what's outstanding still works.
        $ok = $this->actingAs($user)->post(route('inventory.purchase.receive', $po), [
            'location_id'   => $location->id,
            'received_date' => today()->toDateString(),
            'lines'         => [['item_id' => $item->id, 'qty' => 2]],
        ]);
        $ok->assertSessionHasNoErrors();
        $this->assertDatabaseCount('goods_receipt_notes', 1);
    }
}
