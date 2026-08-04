<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\PurchaseOrderItem;
use App\Models\Procurement\GoodsReceiptNote;
use App\Models\Finance\FinanceExpense;
use App\Models\Inventory\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Inventory module — GRN idempotency (P0-4 hardening fix)
 * ─────────────────────────────────────────────────────────────────────────
 *  Before the fix, receiving a PO had no protection against a double-click,
 *  a browser back-button resubmit, or a client retry after a timeout — each
 *  identical request created a second GRN, a second stock-in, and a second
 *  unpaid Finance bill for the same delivery.
 */
class InventoryGrnIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function makePo(string $stamp): array
    {
        $item = InventoryItem::create([
            'product_name' => 'Idem Item ' . $stamp,
            'item_code'    => 'IDEM-' . $stamp,
        ]);
        $location = InventoryLocation::create([
            'name' => 'Idem Store',
            'code' => 'IDEM-LOC-' . $stamp,
        ]);
        $po = PurchaseOrder::create([
            'order_no'   => 'IDEM-PO-' . $stamp,
            'order_date' => today()->toDateString(),
            'status'     => 'ordered',
        ]);
        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'inventory_item_id' => $item->id,
            'qty_ordered'       => 10,
            'unit_price'        => 50,
        ]);

        return [$item, $location, $po];
    }

    public function test_submitting_the_same_receipt_twice_creates_only_one_grn(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\CheckModulePermission::class);
        $user = User::factory()->create(['branch_id' => 1]);
        $stamp = now()->format('His') . rand(100, 999);
        [$item, $location, $po] = $this->makePo($stamp);

        $payload = [
            'location_id'   => $location->id,
            'received_date' => today()->toDateString(),
            'lines'         => [
                ['item_id' => $item->id, 'qty' => 6],
            ],
        ];

        // First submit — succeeds.
        $first = $this->actingAs($user)->post(route('inventory.purchase.receive', $po), $payload);
        $first->assertSessionHasNoErrors();

        // Second, identical submit (double-click / retry) — must NOT create
        // a second GRN, a second stock-in, or a second Finance bill.
        $second = $this->actingAs($user)->post(route('inventory.purchase.receive', $po), $payload);
        $second->assertSessionHasNoErrors();

        $this->assertSame(1, GoodsReceiptNote::where('purchase_order_id', $po->id)->count());
        $this->assertSame(1, StockMovement::where('inventory_item_id', $item->id)->count());
        $this->assertSame(1, FinanceExpense::where('source_type', GoodsReceiptNote::class)->count());

        $this->assertDatabaseHas('inventory_stocks', [
            'inventory_item_id' => $item->id,
            'location_id'       => $location->id,
            'available_qty'     => 6, // NOT 12
        ]);

        $po->refresh();
        $this->assertSame(6.0, (float) $po->items()->first()->qty_received); // NOT 12
    }

    public function test_a_genuinely_different_second_receipt_still_works(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\CheckModulePermission::class);
        $user = User::factory()->create(['branch_id' => 1]);
        $stamp = now()->format('His') . rand(100, 999);
        [$item, $location, $po] = $this->makePo($stamp);

        $this->actingAs($user)->post(route('inventory.purchase.receive', $po), [
            'location_id'   => $location->id,
            'received_date' => today()->toDateString(),
            'lines'         => [['item_id' => $item->id, 'qty' => 4]],
        ])->assertSessionHasNoErrors();

        // Different quantity = different fingerprint = a legitimate second
        // partial delivery, not a duplicate.
        $this->actingAs($user)->post(route('inventory.purchase.receive', $po), [
            'location_id'   => $location->id,
            'received_date' => today()->toDateString(),
            'lines'         => [['item_id' => $item->id, 'qty' => 3]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, GoodsReceiptNote::where('purchase_order_id', $po->id)->count());
        $this->assertDatabaseHas('inventory_stocks', [
            'inventory_item_id' => $item->id,
            'location_id'       => $location->id,
            'available_qty'     => 7,
        ]);
    }
}
