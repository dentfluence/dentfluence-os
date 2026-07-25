<?php

namespace App\Services\Smoke\Journeys;

use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\InventoryStock;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\PurchaseOrderItem;
use App\Models\Inventory\StockMovement;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\StockStatusService;
use App\Services\Smoke\SmokeRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory smoke journey (frozen module V1.0).
 *
 * TEST INFRASTRUCTURE ONLY — operates exclusively on a run-scoped TEST item
 * in a run-scoped TEST location, so real clinic stock is never touched.
 * Stock changes go through the canonical write paths: StockMovement rows
 * (whose model boot maintains live stock) and, in rollback mode, the full
 * PO → GRN chain via InventoryService::receivePurchaseOrder().
 *
 * Commit mode deliberately SKIPS the PO → GRN chain because receiving a GRN
 * auto-posts a real unpaid vendor bill to Finance (production smoke must
 * never create financial records); it uses the canonical manual stock-in
 * movement instead. Stock math is asserted, never recomputed:
 *   FINAL = OPENING + RECEIVED − ISSUED (± adjustments).
 */
class InventorySmokeJourney
{
    private const J = 'Inventory';

    public function __construct(
        private readonly InventoryService $inventory,
        private readonly StockStatusService $stockStatus,
    ) {
    }

    public function run(SmokeRun $run, User $actor): void
    {
        $m = $run->marker();

        // ── 1. Isolated TEST item + TEST location ────────────────────────────
        $item = InventoryItem::create([
            'product_name' => "{$m} Item",
            'item_code'    => "{$m}-ITEM",
            'minimum_qty'  => 5,
            'is_active'    => true,
        ]);
        $run->track($item, "inventory item #{$item->id} ({$m} Item)");

        $location = InventoryLocation::create([
            'name' => "{$m} Store",
            'code' => "{$m}-LOC",
        ]);
        $run->track($location, "inventory location #{$location->id} ({$m} Store)");

        $run->check(self::J, 'Isolated TEST item + location created', $item->exists && $location->exists, SmokeRun::CRITICAL);

        // Commit-mode cleanup: live-stock row + any movements for the TEST item.
        $run->onCleanup(function () use ($item) {
            StockMovement::where('inventory_item_id', $item->id)->delete();
            InventoryStock::where('inventory_item_id', $item->id)->delete();
        });

        // ── 2. Opening quantity through the canonical movement path ──────────
        $opening = StockMovement::create([
            'inventory_item_id' => $item->id,
            'movement_type'     => 'opening_stock',
            'qty'               => 10,
            'to_location_id'    => $location->id,
            'notes'             => "{$m} opening",
            'created_by'        => $actor->id,
        ]);
        $run->track($opening, "stock movement #{$opening->id} ({$m} opening 10)");

        $run->check(
            self::J,
            'Opening stock applied to live stock exactly once (0 → 10)',
            $this->live($item, $location) === 10.0,
            SmokeRun::CRITICAL
        );

        // ── 3–5. Receive quantity: PO → GRN (rollback) / manual in (commit) ──
        if ($run->mode === SmokeRun::MODE_ROLLBACK) {
            $po = PurchaseOrder::create([
                'order_no'   => "{$m}-PO",
                'order_date' => today()->toDateString(),
                'status'     => 'ordered',
            ]);
            $run->track($po, "purchase order #{$po->id} ({$m}-PO)");
            PurchaseOrderItem::create([
                'purchase_order_id' => $po->id,
                'inventory_item_id' => $item->id,
                'qty_ordered'       => 5,
                'unit_price'        => 100,
            ]);

            $grn = $this->inventory->receivePurchaseOrder($po, [
                'location_id'   => $location->id,
                'received_date' => today()->toDateString(),
                'lines'         => [['item_id' => $item->id, 'qty' => 5]],
            ], $actor);
            $run->track($grn, "GRN #{$grn->id} ({$grn->grn_number})");

            $run->check(self::J, 'GRN created for the TEST purchase order', $grn->exists, SmokeRun::CRITICAL);
            $run->check(
                self::J,
                'PO status recalculated after full receipt',
                $po->fresh()->status === 'completed'
            );
            $run->check(
                self::J,
                'Accounts-payable bill auto-posted for the GRN (rolled back afterwards)',
                Schema::hasTable('finance_expenses') && DB::table('finance_expenses')
                    ->where('source_type', \App\Models\Procurement\GoodsReceiptNote::class)
                    ->where('source_id', $grn->id)
                    ->where('payment_status', 'unpaid')
                    ->exists()
            );
        } else {
            // Commit mode: canonical manual stock-in, no financial side effects.
            $stockIn = StockMovement::create([
                'inventory_item_id' => $item->id,
                'movement_type'     => 'stock_in',
                'qty'               => 5,
                'to_location_id'    => $location->id,
                'notes'             => "{$m} received (commit mode: GRN chain skipped — it posts a real AP bill)",
                'created_by'        => $actor->id,
            ]);
            $run->track($stockIn, "stock movement #{$stockIn->id} ({$m} in 5)");
            $run->check(
                self::J,
                'Received via canonical manual stock-in (commit mode skips PO→GRN by design)',
                $stockIn->exists,
                SmokeRun::WORKFLOW,
                'GRN receiving auto-posts an unpaid vendor bill; not allowed in production smoke'
            );
        }

        $run->check(
            self::J,
            'Stock increased EXACTLY once on receipt (10 → 15, no double counting)',
            $this->live($item, $location) === 15.0,
            SmokeRun::CRITICAL
        );
        $run->check(
            self::J,
            'Ledger shows exactly one receiving movement (no duplicate rows)',
            StockMovement::where('inventory_item_id', $item->id)
                ->where('movement_type', 'stock_in')->count() === 1,
            SmokeRun::CRITICAL
        );

        // ── 6–8. Issue / consume through the canonical movement path ─────────
        $issue = StockMovement::create([
            'inventory_item_id' => $item->id,
            'movement_type'     => 'stock_out',
            'qty'               => 3,
            'from_location_id'  => $location->id,
            'notes'             => "{$m} issued",
            'created_by'        => $actor->id,
        ]);
        $run->track($issue, "stock movement #{$issue->id} ({$m} out 3)");

        $run->check(
            self::J,
            'Stock decreased EXACTLY once on issue (15 → 12, no double counting)',
            $this->live($item, $location) === 12.0,
            SmokeRun::CRITICAL
        );

        // ── 9. The movement ledger fully explains the final quantity ─────────
        $movements = StockMovement::where('inventory_item_id', $item->id)->get();

        $in  = $movements->whereIn('movement_type', ['stock_in', 'opening_stock'])->sum(fn ($mv) => abs($mv->qty));
        $out = $movements->whereIn('movement_type', ['stock_out', 'expired', 'damaged', 'treatment_usage', 'retail_sale'])
            ->sum(fn ($mv) => abs($mv->qty));
        $adj = $movements->where('movement_type', 'adjustment')->sum(fn ($mv) => $mv->qty);

        $run->check(
            self::J,
            'FINAL STOCK = OPENING + RECEIVED − ISSUED ± adjustments (ledger explains 12)',
            abs(($in - $out + $adj) - $this->live($item, $location)) < 0.001,
            SmokeRun::CRITICAL,
            sprintf('in=%s out=%s adj=%s live=%s', $in, $out, $adj, $this->live($item, $location))
        );
        $run->check(
            self::J,
            'Exactly 3 ledger rows for the TEST item (no phantom or duplicate movements)',
            $movements->count() === 3,
            SmokeRun::CRITICAL
        );

        // ── 10. Stock-status calculation is consistent and sane ──────────────
        $freshItem = $item->fresh();
        $run->check(
            self::J,
            'StockStatusService consistent (statusFor == classify(onHand, min))',
            $this->stockStatus->statusFor($freshItem) === $this->stockStatus->classify(12.0, 5.0),
            SmokeRun::WORKFLOW,
            'statusFor=' . $this->stockStatus->statusFor($freshItem)->name
        );

        // ── 11. Auditability of the movements ────────────────────────────────
        $run->check(
            self::J,
            'Every TEST movement attributes the acting user (audit trail)',
            $movements->every(fn ($mv) => (int) $mv->created_by === (int) $actor->id)
        );
    }

    /** Live on-hand for the TEST item at the TEST location. */
    private function live(InventoryItem $item, InventoryLocation $location): float
    {
        return (float) (InventoryStock::where('inventory_item_id', $item->id)
            ->where('location_id', $location->id)
            ->value('available_qty') ?? 0.0);
    }
}
