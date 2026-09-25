<?php

namespace Tests\Feature\Inventory;

use App\Models\Invoice;
use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\InventoryStock;
use App\Models\Inventory\StockMovement;
use App\Services\Inventory\RetailStockReversal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\TreatmentVisits\Concerns\BuildsVisitFixtures;
use Tests\TestCase;

/**
 * INT-13 (security audit 24 Sep 2026) — sell 2, edit, cancel used to return
 * 4 units, because every reversal re-read sales already given back.
 *
 * Invoice edit = reverse + re-deduct; cancel = reverse. Both controllers now
 * call RetailStockReversal, so it is exercised here directly with exactly
 * that sequence.
 */
class RetailStockReversedOnceTest extends TestCase
{
    use RefreshDatabase;
    use BuildsVisitFixtures;

    private InventoryItem $item;
    private InventoryLocation $store;
    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->makeUser());

        $this->store   = $this->makeInventoryLocation(['type' => 'main_store']);
        $this->item    = $this->makeInventoryItem(['is_sellable' => true]);
        $this->invoice = Invoice::create([
            'invoice_number' => Invoice::nextNumber(),
            'patient_id'     => $this->makePatient()->id,
            'invoice_date'   => now()->toDateString(),
            'status'         => 'draft',
        ]);

        StockMovement::create([
            'inventory_item_id' => $this->item->id, 'movement_type' => 'stock_in',
            'qty' => 10, 'to_location_id' => $this->store->id,
        ]);
    }

    private function sell(int $qty): StockMovement
    {
        return StockMovement::create([
            'inventory_item_id' => $this->item->id, 'movement_type' => 'retail_sale',
            'qty' => -$qty, 'from_location_id' => $this->store->id,
            'reference_type' => Invoice::class, 'reference_id' => $this->invoice->id,
        ]);
    }

    private function onHand(): float
    {
        return (float) InventoryStock::where('inventory_item_id', $this->item->id)
            ->where('location_id', $this->store->id)->value('available_qty');
    }

    public function test_sell_edit_cancel_returns_stock_to_where_it_started(): void
    {
        $this->sell(2);                                            // sale
        $this->assertSame(8.0, $this->onHand());

        RetailStockReversal::forInvoice($this->invoice, 'edited'); // edit: give back...
        $this->sell(2);                                            // ...and deduct again
        $this->assertSame(8.0, $this->onHand());

        RetailStockReversal::forInvoice($this->invoice, 'cancelled');
        $this->assertSame(10.0, $this->onHand());                  // was 12 before the fix
    }

    public function test_each_sale_is_linked_to_its_reversal_and_a_second_call_does_nothing(): void
    {
        $sale = $this->sell(3);

        $this->assertSame(1, RetailStockReversal::forInvoice($this->invoice, 'cancelled'));
        $this->assertSame(0, RetailStockReversal::forInvoice($this->invoice, 'cancelled'));

        $this->assertNotNull($sale->fresh()->reversed_at);
        $this->assertSame($sale->id, StockMovement::where('reversal_of_id', $sale->id)->sole()->reversal_of_id);
        $this->assertSame(10.0, $this->onHand());
    }

    public function test_a_pre_fix_unlinked_reversal_is_adopted_not_repeated(): void
    {
        $sale = $this->sell(2);

        // What the old code wrote on an edit: a stock_in with no link.
        StockMovement::create([
            'inventory_item_id' => $this->item->id, 'movement_type' => 'stock_in',
            'qty' => 2, 'to_location_id' => $this->store->id,
            'reference_type' => Invoice::class, 'reference_id' => $this->invoice->id,
            'notes' => 'Reversal — invoice X edited/cancelled',
        ]);
        $this->sell(2); // re-deducted after that old edit
        $this->assertSame(8.0, $this->onHand());

        RetailStockReversal::forInvoice($this->invoice, 'cancelled');

        $this->assertSame(10.0, $this->onHand());
        $this->assertNotNull($sale->fresh()->reversed_at);
    }
}
