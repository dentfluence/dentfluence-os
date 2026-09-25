<?php

namespace App\Services\Inventory;

use App\Models\Invoice;
use App\Models\Inventory\StockMovement;
use Illuminate\Support\Facades\DB;

/**
 * INT-13 (security audit 24 Sep 2026) — give back the retail stock an
 * invoice deducted, exactly once per sale.
 *
 * Web and mobile each had their own copy of this, and both re-read EVERY
 * retail_sale row for the invoice, including ones already given back. So
 * sell 2 -> edit -> cancel returned 4 units: +2 stock from nothing, and
 * low-stock alerts on retail items stopped firing.
 *
 * Now each sale is reversed once and linked both ways, the same way manual
 * adjustments and GRN reversals already are: the new stock_in carries
 * reversal_of_id, the original sale is stamped reversed_at/reversed_by.
 *
 * Rows written before this fix have an unlinked "Reversal — invoice ..."
 * stock_in. Those are adopted first (linked to the sale they gave back), so a
 * sale already returned is never returned a second time.
 */
class RetailStockReversal
{
    public static function forInvoice(Invoice $invoice, string $why): int
    {
        return DB::transaction(function () use ($invoice, $why) {
            $sales = StockMovement::where('reference_type', Invoice::class)
                ->where('reference_id', $invoice->id)
                ->where('movement_type', 'retail_sale')
                ->whereNull('reversed_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($sales->isEmpty()) {
                return 0;
            }

            self::adoptLegacyReversals($invoice, $sales);

            $done = 0;
            foreach ($sales->whereNull('reversed_at') as $sale) {
                StockMovement::create([
                    'inventory_item_id' => $sale->inventory_item_id,
                    'movement_type'     => 'stock_in',
                    'qty'               => abs($sale->qty),
                    'to_location_id'    => $sale->from_location_id,
                    'unit_cost'         => $sale->unit_cost,
                    'total_cost'        => $sale->total_cost,
                    'reference_type'    => Invoice::class,
                    'reference_id'      => $invoice->id,
                    'reversal_of_id'    => $sale->id,
                    'notes'             => 'Reversal — invoice ' . $invoice->invoice_number . ' ' . $why,
                    'created_by'        => auth()->id(),
                ]);

                $sale->update(['reversed_at' => now(), 'reversed_by' => auth()->id()]);
                $done++;
            }

            return $done;
        });
    }

    /** Link pre-fix reversal rows to the sale they already gave back. */
    private static function adoptLegacyReversals(Invoice $invoice, $sales): void
    {
        $legacy = StockMovement::where('reference_type', Invoice::class)
            ->where('reference_id', $invoice->id)
            ->where('movement_type', 'stock_in')
            ->whereNull('reversal_of_id')
            ->where('notes', 'like', 'Reversal%')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($legacy as $rev) {
            $sale = $sales->first(fn ($s) => is_null($s->reversed_at)
                && $s->id < $rev->id
                && (int) $s->inventory_item_id === (int) $rev->inventory_item_id
                && abs((float) $s->qty) == abs((float) $rev->qty));

            if (! $sale) {
                continue;
            }

            $rev->update(['reversal_of_id' => $sale->id]);
            $sale->update(['reversed_at' => $rev->created_at ?? now(), 'reversed_by' => $rev->created_by]);
        }
    }
}
