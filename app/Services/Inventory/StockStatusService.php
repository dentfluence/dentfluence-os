<?php

namespace App\Services\Inventory;

use App\Enums\StockStatus;
use App\Models\Inventory\InventoryItem;
use Illuminate\Support\Collection;

/**
 * StockStatusService
 * ------------------
 * The single source of truth for stock status across the whole Inventory
 * module — Dashboard, Alerts, Stock Count, Items list, Reports, the mobile
 * API, and any future AI / notification surface. Nothing computes stock
 * status on its own; everything calls this service so every screen always
 * shows identical numbers.
 *
 * Definitions live in App\Enums\StockStatus. Par level = the item's Minimum
 * Stock (`minimum_qty`). Items with no Minimum Stock are classified
 * MinNotSet and are excluded from the Low / Critical / Out counts.
 */
class StockStatusService
{
    /**
     * Classify a single item from its clinic-wide on-hand quantity and its
     * Minimum Stock. This is the ONLY definition of the boundaries.
     */
    public function classify(float $onHand, ?float $minimumStock): StockStatus
    {
        $min = (float) ($minimumStock ?? 0);

        // No Minimum Stock configured → not part of the alerting population.
        if ($min <= 0) {
            return StockStatus::MinNotSet;
        }

        if ($onHand <= 0)          return StockStatus::Out;
        if ($onHand <= $min / 2)   return StockStatus::Critical;
        if ($onHand <= $min)       return StockStatus::Low;

        return StockStatus::InStock;
    }

    /** Convenience: classify a loaded item (sums its stock rows). */
    public function statusFor(InventoryItem $item): StockStatus
    {
        $onHand = $item->stocks->sum('available_qty');
        return $this->classify((float) $onHand, $item->minimum_qty);
    }

    /**
     * Counts for every status across all active items — the numbers behind
     * every KPI card. Returns keys: in_stock, low, critical, out, min_not_set,
     * total, managed (total minus min_not_set), attention (low+critical+out).
     */
    public function counts(): array
    {
        $c = [
            StockStatus::InStock->value   => 0,
            StockStatus::Low->value       => 0,
            StockStatus::Critical->value  => 0,
            StockStatus::Out->value       => 0,
            StockStatus::MinNotSet->value => 0,
        ];

        foreach ($this->activeItemsWithOnHand() as $item) {
            $status = $this->classify((float) $item->on_hand, $item->minimum_qty);
            $c[$status->value]++;
        }

        $c['total']     = array_sum($c);
        $c['managed']   = $c['total'] - $c[StockStatus::MinNotSet->value];
        $c['attention'] = $c[StockStatus::Low->value]
                        + $c[StockStatus::Critical->value]
                        + $c[StockStatus::Out->value];

        return $c;
    }

    /**
     * Items in one or more statuses — powers the Alerts and Stock Count lists.
     * Eager-loads category + stock locations for display.
     */
    public function itemsByStatus(StockStatus ...$statuses): Collection
    {
        return InventoryItem::query()
            ->where('is_active', true)
            ->with(['category', 'stocks.location'])
            ->withSum('stocks as on_hand', 'available_qty')
            ->orderBy('product_name')
            ->get()
            ->filter(fn ($item) => in_array(
                $this->classify((float) $item->on_hand, $item->minimum_qty),
                $statuses,
                true
            ))
            ->values();
    }

    /** Active items with a summed clinic-wide on-hand column (`on_hand`). */
    private function activeItemsWithOnHand(): Collection
    {
        return InventoryItem::query()
            ->where('is_active', true)
            ->withSum('stocks as on_hand', 'available_qty')
            ->get(['id', 'minimum_qty']);
    }
}
