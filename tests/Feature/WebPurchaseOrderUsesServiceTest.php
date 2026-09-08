<?php

namespace Tests\Feature;

use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\InventoryVendor;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M-7 (8 Sep 2026) — PO creation had two writers: the web controller and
 * InventoryService::createPurchaseOrder() (the mobile path), ~80 identical
 * lines each. The web POST now goes through the service. This pins that the
 * web path still produces the same PO: totals with GST, line rows, the
 * Finance vendor link, and the two vendor tasks for an 'ordered' PO.
 */
class WebPurchaseOrderUsesServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_store_creates_the_same_po_the_service_does(): void
    {
        $admin  = User::factory()->create(['role' => 'admin', 'branch_id' => 1, 'is_active' => true]);
        $vendor = InventoryVendor::create(['vendor_name' => 'Dental Depot', 'is_active' => true]);
        $item   = InventoryItem::create(['product_name' => 'Gloves M', 'item_code' => 'GLV-M7-' . uniqid()]);

        $this->actingAs($admin)->post(route('inventory.purchase.store'), [
            'vendor_id'     => $vendor->id,
            'order_date'    => today()->toDateString(),
            'expected_date' => today()->addDays(5)->toDateString(),
            'status'        => 'ordered',
            'items'         => [
                ['item_id' => $item->id, 'qty' => 10, 'price' => 100, 'gst' => 18],
            ],
        ])->assertRedirect(route('inventory.purchase'));

        $po = PurchaseOrder::with('items')->firstOrFail();
        $this->assertSame(1180.0, (float) $po->total_amount);   // 1000 + 18% GST
        $this->assertSame(180.0, (float) $po->gst_amount);
        $this->assertCount(1, $po->items);
        $this->assertSame(10, (int) $po->items->first()->qty_ordered);
        $this->assertNotNull($po->finance_vendor_id, 'the inventory vendor must be synced to Finance');
        $this->assertSame($admin->id, (int) $po->created_by);

        $this->assertSame(1, Task::where('po_id', $po->id)->where('title', 'like', 'Confirm PO%')->count());
        $this->assertSame(1, Task::where('po_id', $po->id)->where('title', 'like', 'Delivery follow-up%')->count());
    }
}
