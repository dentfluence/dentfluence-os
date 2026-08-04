<?php

namespace Tests\Feature;

use App\Models\AppSetting;
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
 *  Inventory module — GRN reversal ledger consistency (P0-7 / M1 hardening)
 * ─────────────────────────────────────────────────────────────────────────
 *  Before the fix, reversing a GRN hard-deleted the stock_movements and
 *  goods_receipt_notes/grn_items rows (destroying the audit trail for the
 *  module's most financially significant action) and voided the linked
 *  Finance expense with an invalid enum value ('void' was never a valid
 *  finance_expenses.payment_status). It also never checked whether the
 *  bill had already been paid before unwinding stock.
 */
class InventoryGrnReversalTest extends TestCase
{
    use RefreshDatabase;

    private function receiveAndReturn(): array
    {
        $this->withoutMiddleware(\App\Http\Middleware\CheckModulePermission::class);
        AppSetting::set('grn_correction_window_hours', 24, 'inventory');

        $user = User::factory()->create(['branch_id' => 1]);
        $stamp = now()->format('His') . rand(100, 999);

        $item = InventoryItem::create([
            'product_name' => 'Reversal Item ' . $stamp,
            'item_code'    => 'REV-' . $stamp,
        ]);
        $location = InventoryLocation::create(['name' => 'Reversal Store', 'code' => 'REV-LOC-' . $stamp]);
        $po = PurchaseOrder::create([
            'order_no'   => 'REV-PO-' . $stamp,
            'order_date' => today()->toDateString(),
            'status'     => 'ordered',
        ]);
        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'inventory_item_id' => $item->id,
            'qty_ordered'       => 10,
            'unit_price'        => 100,
        ]);

        $this->actingAs($user)->post(route('inventory.purchase.receive', $po), [
            'location_id'   => $location->id,
            'received_date' => today()->toDateString(),
            'lines'         => [['item_id' => $item->id, 'qty' => 7]],
        ])->assertSessionHasNoErrors();

        return [$user, $item, $location, $po];
    }

    public function test_reversal_creates_a_compensating_entry_instead_of_deleting_the_ledger(): void
    {
        [$user, $item, $location, $po] = $this->receiveAndReturn();

        $grn = GoodsReceiptNote::where('purchase_order_id', $po->id)->firstOrFail();
        $originalMovement = StockMovement::where('inventory_item_id', $item->id)->firstOrFail();

        $resp = $this->actingAs($user)->delete(route('inventory.purchase.grn.reverse', $po));
        $resp->assertSessionHasNoErrors();

        // Original movement still exists (never deleted) and is stamped reversed.
        $this->assertDatabaseHas('stock_movements', ['id' => $originalMovement->id]);
        $originalMovement->refresh();
        $this->assertNotNull($originalMovement->reversed_at);

        // A NEW compensating stock_out entry exists, linked back to the original.
        $this->assertDatabaseHas('stock_movements', [
            'inventory_item_id' => $item->id,
            'movement_type'     => 'stock_out',
            'qty'               => 7,
            'from_location_id'  => $location->id,
            'reversal_of_id'    => $originalMovement->id,
        ]);

        // Live stock is back to zero.
        $this->assertDatabaseHas('inventory_stocks', [
            'inventory_item_id' => $item->id,
            'location_id'       => $location->id,
            'available_qty'     => 0,
        ]);

        // GRN is marked reversed, NOT deleted — record and line items remain.
        $grn->refresh();
        $this->assertSame('reversed', $grn->status);
        $this->assertNotNull($grn->reversed_at);
        $this->assertSame(1, $grn->items()->count());

        // PO qty_received rolled back.
        $po->refresh();
        $this->assertSame(0.0, (float) $po->items()->first()->qty_received);
        $this->assertSame('ordered', $po->status);

        // Finance expense voided with a valid enum value.
        $this->assertDatabaseHas('finance_expenses', [
            'source_type'    => GoodsReceiptNote::class,
            'source_id'      => $grn->id,
            'payment_status' => 'void',
        ]);
    }

    public function test_reversal_is_blocked_if_the_bill_is_already_paid(): void
    {
        [$user, $item, $location, $po] = $this->receiveAndReturn();

        $grn = GoodsReceiptNote::where('purchase_order_id', $po->id)->firstOrFail();
        FinanceExpense::where('source_type', GoodsReceiptNote::class)
            ->where('source_id', $grn->id)
            ->update(['payment_status' => 'paid']);

        $resp = $this->actingAs($user)->delete(route('inventory.purchase.grn.reverse', $po));
        $resp->assertSessionHasErrors('grn');

        // Nothing changed — GRN still confirmed, stock untouched, expense still paid.
        $grn->refresh();
        $this->assertSame('confirmed', $grn->status);
        $this->assertDatabaseHas('inventory_stocks', [
            'inventory_item_id' => $item->id,
            'location_id'       => $location->id,
            'available_qty'     => 7,
        ]);
        $this->assertDatabaseHas('finance_expenses', [
            'source_type'    => GoodsReceiptNote::class,
            'source_id'      => $grn->id,
            'payment_status' => 'paid',
        ]);
    }
}
