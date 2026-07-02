<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Inventory\StockCountSession;
use App\Models\Inventory\StockCountLine;
use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\StockMovement;
use Carbon\Carbon;

/**
 * StockCountController
 * ─────────────────────────────────────────────────────────────────
 * Handles the 15-day physical stock count cycle.
 *
 * Routes:
 *   GET  /inventory/stock-count              → index (list of sessions + start)
 *   POST /inventory/stock-count              → start a new session
 *   GET  /inventory/stock-count/{session}    → count sheet (entry form)
 *   POST /inventory/stock-count/{session}    → save progress (draft → in_progress)
 *   POST /inventory/stock-count/{session}/complete → finalise, apply adjustments
 * ─────────────────────────────────────────────────────────────────
 */
class StockCountController extends Controller
{
    /* ══════════════════════════════════════════════════════════
       INDEX — list past sessions + "Start New Count" button
    ══════════════════════════════════════════════════════════ */

    public function index()
    {
        $sessions = StockCountSession::with(['startedBy', 'completedBy'])
            ->orderByDesc('id')
            ->paginate(15);

        // Is there already an open session?
        $activeSession = StockCountSession::active()->latest()->first();

        // When is the next count due? (based on last completed session + 15 days)
        $lastCompleted = StockCountSession::completed()->latest('completed_at')->first();
        $nextDue       = $lastCompleted
            ? Carbon::parse($lastCompleted->completed_at)->addDays(15)->toDateString()
            : now()->toDateString();

        // Quick summary counts for the info bar
        $totalItems   = InventoryItem::where('is_active', true)->count();
        $lowCount     = $this->countLowItems();
        $criticalCount = $this->countCriticalItems();

        return view('inventory.stock-count-index', compact(
            'sessions', 'activeSession', 'nextDue',
            'totalItems', 'lowCount', 'criticalCount'
        ));
    }

    /* ══════════════════════════════════════════════════════════
       START — create a new session and pre-load all items
    ══════════════════════════════════════════════════════════ */

    public function start(Request $request)
    {
        // Only one active session at a time
        $existing = StockCountSession::active()->first();
        if ($existing) {
            return redirect()->route('inventory.stock-count.sheet', $existing)
                ->with('info', 'A count session is already in progress. Continuing it.');
        }

        $session = StockCountSession::create([
            'session_no' => StockCountSession::generateSessionNo(),
            'count_date' => now()->toDateString(),
            'status'     => 'in_progress',
            'started_by' => auth()->id(),
        ]);

        // Pre-load all active items as count lines (with current stock snapshot)
        $this->populateLines($session);

        return redirect()->route('inventory.stock-count.sheet', $session)
            ->with('success', 'Stock count session ' . $session->session_no . ' started.');
    }

    /* ══════════════════════════════════════════════════════════
       SHEET — the actual count entry form
    ══════════════════════════════════════════════════════════ */

    public function sheet(StockCountSession $session)
    {
        if (!$session->isEditable()) {
            return redirect()->route('inventory.stock-count.index')
                ->with('info', 'Session ' . $session->session_no . ' is already completed.');
        }

        $lines = $session->lines()
            ->with('item')
            ->orderBy('category_name')
            ->orderBy('product_name')
            ->get();

        // Group by category for the count sheet UI
        $grouped = $lines->groupBy('category_name');

        return view('inventory.stock-count', compact('session', 'lines', 'grouped'));
    }

    /* ══════════════════════════════════════════════════════════
       SAVE — save physical counts (keeps session open for edits)
    ══════════════════════════════════════════════════════════ */

    public function save(Request $request, StockCountSession $session)
    {
        if (!$session->isEditable()) {
            return back()->withErrors(['session' => 'This session is already completed.']);
        }

        $request->validate([
            'counts'         => 'required|array',
            'counts.*.line_id' => 'required|exists:stock_count_lines,id',
            'counts.*.qty'   => 'nullable|numeric|min:0',
            'counts.*.notes' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($request, $session) {
            foreach ($request->counts as $entry) {
                $line = StockCountLine::find($entry['line_id']);
                if (!$line || $line->session_id !== $session->id) continue;

                $physical = isset($entry['qty']) && $entry['qty'] !== '' ? (float) $entry['qty'] : null;
                $variance = ($physical !== null) ? ($physical - $line->system_qty) : null;
                $status   = ($physical !== null)
                    ? StockCountLine::deriveStatus($physical, $line->minimum_qty, $line->reorder_level)
                    : null;

                $line->update([
                    'physical_qty' => $physical,
                    'variance'     => $variance,
                    'stock_status' => $status,
                    'notes'        => $entry['notes'] ?? null,
                ]);
            }

            $session->update(['status' => 'in_progress']);
        });

        return back()->with('success', 'Progress saved. Continue counting or submit when done.');
    }

    /* ══════════════════════════════════════════════════════════
       COMPLETE — apply stock adjustments + flag low/critical
    ══════════════════════════════════════════════════════════ */

    public function complete(Request $request, StockCountSession $session)
    {
        if (!$session->isEditable()) {
            return back()->withErrors(['session' => 'This session is already completed.']);
        }

        // Make sure at least some lines have been counted
        $countedLines = $session->lines()->whereNotNull('physical_qty')->count();
        if ($countedLines === 0) {
            return back()->withErrors(['session' => 'Please enter at least one physical count before submitting.']);
        }

        // Find the main store location for adjustments (fallback to first active location)
        $location = InventoryLocation::where('code', 'MAIN-STORE')->first()
            ?? InventoryLocation::where('is_active', true)->first();

        DB::transaction(function () use ($session, $location) {

            $adjustedCount  = 0;
            $lowCount       = 0;
            $criticalCount  = 0;

            $lines = $session->lines()->whereNotNull('physical_qty')->get();

            foreach ($lines as $line) {
                // Re-derive status (in case items were saved without status)
                $status = StockCountLine::deriveStatus(
                    $line->physical_qty,
                    $line->minimum_qty,
                    $line->reorder_level
                );
                $line->stock_status = $status;

                if ($status === 'low')      $lowCount++;
                if (in_array($status, ['critical', 'out'])) $criticalCount++;

                // Create stock adjustment movement only if variance is non-zero
                if ($line->variance !== null && abs($line->variance) > 0.001) {
                    $movementType = $line->variance > 0 ? 'stock_in' : 'stock_out';

                    $movement = StockMovement::create([
                        'inventory_item_id' => $line->inventory_item_id,
                        'movement_type'     => 'stock_adjustment',
                        'qty'               => abs($line->variance),
                        'to_location_id'    => $line->variance > 0 ? $location?->id : null,
                        'from_location_id'  => $line->variance < 0 ? $location?->id : null,
                        'unit_cost'         => 0,
                        'total_cost'        => 0,
                        'notes'             => 'Physical count — Session ' . $session->session_no
                                              . '. System: ' . $line->system_qty
                                              . ' | Physical: ' . $line->physical_qty
                                              . ' | Variance: ' . ($line->variance > 0 ? '+' : '') . $line->variance,
                        'reference_type'    => StockCountSession::class,
                        'reference_id'      => $session->id,
                        'created_by'        => auth()->id(),
                    ]);

                    $line->stock_movement_id = $movement->id;
                    $adjustedCount++;
                }

                $line->save();
            }

            // Finalise the session
            $session->update([
                'status'              => 'completed',
                'items_counted'       => $countedLines = $session->lines()->whereNotNull('physical_qty')->count(),
                'items_adjusted'      => $adjustedCount,
                'low_stock_count'     => $lowCount,
                'critical_stock_count'=> $criticalCount,
                'next_count_due'      => now()->addDays(15)->toDateString(),
                'completed_by'        => auth()->id(),
                'completed_at'        => now(),
            ]);

            // Flash summary for admin notification banner
            session()->flash('stock_count_summary', [
                'session_no'     => $session->session_no,
                'items_counted'  => $countedLines,
                'adjusted'       => $adjustedCount,
                'low'            => $lowCount,
                'critical'       => $criticalCount,
                'next_due'       => now()->addDays(15)->format('d M Y'),
            ]);
        });

        return redirect()->route('inventory.stock-count.index')
            ->with('success',
                'Stock count ' . $session->session_no . ' completed. '
                . 'Adjustments applied. Check low/critical items and raise a PO.'
            );
    }

    /* ══════════════════════════════════════════════════════════
       PRIVATE HELPERS
    ══════════════════════════════════════════════════════════ */

    /**
     * Pre-populate count lines for a new session.
     * Snaps the current system stock for every active item.
     */
    private function populateLines(StockCountSession $session): void
    {
        // Total stock per item across all locations
        $stockMap = DB::table('inventory_stocks')
            ->selectRaw('inventory_item_id, SUM(available_qty) as total_qty')
            ->groupBy('inventory_item_id')
            ->pluck('total_qty', 'inventory_item_id');

        $items = InventoryItem::where('is_active', true)
            ->with('category')
            ->orderBy('product_name')
            ->get();

        $lines = [];
        $now   = now();

        foreach ($items as $item) {
            $lines[] = [
                'session_id'         => $session->id,
                'inventory_item_id'  => $item->id,
                'category_name'      => $item->category?->name ?? 'Uncategorised',
                'product_name'       => $item->product_name,
                'system_qty'         => (float) ($stockMap[$item->id] ?? 0),
                'consumption_unit'   => $item->consumption_unit,
                'minimum_qty'        => (float) $item->minimum_qty,
                'reorder_level'      => (float) ($item->reorder_level ?? 0),
                'created_at'         => $now,
                'updated_at'         => $now,
            ];
        }

        // Chunked insert for performance
        foreach (array_chunk($lines, 100) as $chunk) {
            DB::table('stock_count_lines')->insert($chunk);
        }
    }

    private function countLowItems(): int
    {
        return DB::table('inventory_items as i')
            ->join('inventory_stocks as s', 'i.id', '=', 's.inventory_item_id')
            ->where('i.is_active', true)
            ->whereRaw('i.reorder_level > 0')
            ->whereRaw('s.available_qty > i.minimum_qty')
            ->whereRaw('s.available_qty <= i.reorder_level')
            ->distinct('i.id')
            ->count('i.id');
    }

    private function countCriticalItems(): int
    {
        return DB::table('inventory_items as i')
            ->join('inventory_stocks as s', 'i.id', '=', 's.inventory_item_id')
            ->where('i.is_active', true)
            ->whereRaw('i.minimum_qty > 0')
            ->whereRaw('s.available_qty <= i.minimum_qty')
            ->distinct('i.id')
            ->count('i.id');
    }
}
