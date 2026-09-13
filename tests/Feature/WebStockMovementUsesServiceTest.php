<?php

namespace Tests\Feature;

use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\InventoryStock;
use App\Models\Inventory\StockMovement;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * I-1 (12 Sep 2026) — the web Stock In / Stock Out screens carried their own
 * copy of the movement logic. storeStockOut() in particular did a
 * check-then-act with NO transaction and NO row lock: it read available_qty,
 * compared, then wrote. The 4-5 Aug P0 hardening (CEO Directive #007) closed
 * exactly that race in InventoryService::createStockOut() with
 * DB::transaction + lockForUpdate(), but this web copy was never consolidated
 * onto it — so the mobile API was safe and the screen reception actually uses
 * was not. Both web methods now delegate.
 *
 * The spy below proves DELEGATION structurally, not just that the outcome
 * looks right. Behaviour alone cannot tell the two apart: the old inlined
 * code returned the same errors and wrote the same rows — what it did NOT do
 * was hold a lock. If anyone re-inlines the logic, the spy stops being called
 * and the first and last cases fail.
 */
class WebStockMovementUsesServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $spy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->spy = new class extends InventoryService {
            public array $calls = [];

            public function createStockOut(array $data, User $user): StockMovement
            {
                $this->calls[] = 'createStockOut';
                return parent::createStockOut($data, $user);
            }

            public function createStockIn(array $data, User $user): StockMovement
            {
                $this->calls[] = 'createStockIn';
                return parent::createStockIn($data, $user);
            }
        };

        $this->app->instance(InventoryService::class, $this->spy);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'branch_id' => 1, 'is_active' => true]);
    }

    /** @return array{0:InventoryItem,1:InventoryLocation} */
    private function itemWithStock(User $user, float $qty): array
    {
        $stamp = now()->format('His') . rand(100, 999);

        $item = InventoryItem::create([
            'product_name'     => 'Web Stock Test Item ' . $stamp,
            'item_code'        => 'WSM-' . $stamp,
            'consumption_unit' => 'units',
        ]);
        $location = InventoryLocation::create([
            'name' => 'Web Stock Store ' . $stamp,
            'code' => 'WSM-LOC-' . $stamp,
        ]);

        if ($qty > 0) {
            // Seeded through a fresh service instance, NOT the web route, so
            // the spy's call log only ever holds the route's own calls.
            (new InventoryService())->createStockIn([
                'inventory_item_id' => $item->id,
                'to_location_id'    => $location->id,
                'qty'               => $qty,
                'unit_cost'         => 50,
            ], $user);
        }

        return [$item, $location];
    }

    public function test_web_stock_out_goes_through_the_locked_service_path(): void
    {
        $admin = $this->admin();
        [$item, $location] = $this->itemWithStock($admin, 10);

        $this->actingAs($admin)->post(route('inventory.stock-out.store'), [
            'inventory_item_id' => $item->id,
            'from_location_id'  => $location->id,
            'qty'               => 4,
            'movement_type'     => 'stock_out',
            'notes'             => 'chairside use',
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            ['createStockOut'],
            $this->spy->calls,
            'the web Stock Out screen must delegate to InventoryService::createStockOut() — that is where the transaction and the row lock live'
        );

        $this->assertSame(6.0, (float) InventoryStock::where('inventory_item_id', $item->id)
            ->where('location_id', $location->id)->value('available_qty'));
    }

    public function test_web_stock_out_writes_the_movement_the_service_writes(): void
    {
        $admin = $this->admin();
        [$item, $location] = $this->itemWithStock($admin, 10);

        $this->actingAs($admin)->post(route('inventory.stock-out.store'), [
            'inventory_item_id' => $item->id,
            'from_location_id'  => $location->id,
            'qty'               => 4,
            'movement_type'     => 'stock_out',
        ]);

        $movement = StockMovement::where('inventory_item_id', $item->id)
            ->where('movement_type', 'stock_out')->firstOrFail();

        // Sign convention is the service's: leaving the system is negative.
        $this->assertSame(-4.0, (float) $movement->qty);
        $this->assertSame($location->id, (int) $movement->from_location_id);
        $this->assertSame(200.0, (float) $movement->total_cost); // 4 x 50
        $this->assertSame($admin->id, (int) $movement->created_by);
    }

    public function test_web_stock_out_refuses_over_removal_and_writes_nothing(): void
    {
        $admin = $this->admin();
        [$item, $location] = $this->itemWithStock($admin, 3);

        $this->actingAs($admin)->post(route('inventory.stock-out.store'), [
            'inventory_item_id' => $item->id,
            'from_location_id'  => $location->id,
            'qty'               => 5,
            'movement_type'     => 'stock_out',
        ])->assertSessionHasErrors('qty');

        $this->assertSame(0, StockMovement::where('inventory_item_id', $item->id)
            ->where('movement_type', 'stock_out')->count(), 'a refused stock-out must leave no ledger row');

        $this->assertSame(3.0, (float) InventoryStock::where('inventory_item_id', $item->id)
            ->where('location_id', $location->id)->value('available_qty'));
    }

    public function test_web_stock_in_goes_through_the_service_and_updates_price(): void
    {
        $admin = $this->admin();
        [$item, $location] = $this->itemWithStock($admin, 0);

        $this->actingAs($admin)->post(route('inventory.stock-in.store'), [
            'inventory_item_id' => $item->id,
            'to_location_id'    => $location->id,
            'qty'               => 12,
            'unit_cost'         => 75,
            'batch_no'          => 'B-2026-09',
            'notes'             => 'opening purchase',
        ])->assertSessionHasNoErrors();

        $this->assertSame(['createStockIn'], $this->spy->calls);

        $this->assertSame(12.0, (float) InventoryStock::where('inventory_item_id', $item->id)
            ->where('location_id', $location->id)->value('available_qty'));

        $movement = StockMovement::where('inventory_item_id', $item->id)
            ->where('movement_type', 'stock_in')->firstOrFail();
        $this->assertSame('B-2026-09', $movement->batch_no);
        $this->assertSame(900.0, (float) $movement->total_cost); // 12 x 75

        $this->assertSame(75.0, (float) $item->fresh()->last_purchase_price);
    }
}
