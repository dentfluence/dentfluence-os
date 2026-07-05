<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Inventory\StockCountSession;
use App\Models\Inventory\StockCountLine;
use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * StockCountController (API v1)
 * ------------------------------
 * Mobile mirror of the web App\Http\Controllers\StockCountController.
 * Same 15-day physical stock count cycle, same session/line logic — just
 * returned as JSON instead of Blade views.
 *
 *   GET  /inventory/stock-count                    list + summary
 *   POST /inventory/stock-count                    start (or continue) a session
 *   GET  /inventory/stock-count/{session}          count sheet (lines)
 *   POST /inventory/stock-count/{session}/save      save progress
 *   POST /inventory/stock-count/{session}/complete  finalise + apply adjustments
 */
class StockCountController extends ApiController
{
    /* ══════════════════════════════════════════════════════════
       INDEX — list past sessions + summary block
    ══════════════════════════════════════════════════════════ */

    public function index(Request $request): JsonResponse
    {
        $query = StockCountSession::with(['startedBy', 'completedBy'])
            ->orderByDesc('id');

        $page = $this->paginate($query, $request, 15);

        $activeSession = StockCountSession::active()->latest()->first();

        $lastCompleted = StockCountSession::completed()->latest('completed_at')->first();
        $nextDue       = $lastCompleted
            ? Carbon::parse($lastCompleted->completed_at)->addDays(15)->toDateString()
            : now()->toDateString();

        $totalItems    = InventoryItem::where('is_active', true)->count();
        $lowCount      = $this->countLowItems();
        $criticalCount = $this->countCriticalItems();

        $sessions = collect($page->items())->map(fn (StockCountSession $s) => $this->mapSession($s))->values();

        return $this->success([
            'sessions'        => $sessions,
            'active_session'  => $activeSession ? $this->mapSessionSummary($activeSession) : null,
            'next_count_due'  => $nextDue,
            'total_items'     => $totalItems,
            'low_count'       => $lowCount,
            'critical_count'  => $criticalCount,
        ], '', 200, [
            'current_page' => $page->currentPage(),
            'per_page'     => $page->perPage(),
            'total'        => $page->total(),
            'last_page'    => $page->lastPage(),
        ]);
    }

    /* ══════════════════════════════════════════════════════════
       START — create a new session (or return the existing one)
    ══════════════════════════════════════════════════════════ */

    public function start(Request $request): JsonResponse
    {
        $existing = StockCountSession::active()->first();
        if ($existing) {
            return $this->success($this->mapSessionSummary($existing), 'A count session is already in progress. Continuing it.');
        }

        $session = StockCountSession::create([
            'session_no' => StockCountSession::generateSessionNo(),
            'count_date' => now()->toDateString(),
            'status'     => 'in_progress',
            'started_by' => $request->user()->id,
        ]);

        $this->populateLines($session);

        return $this->success($this->mapSessionSummary($session), 'Stock count session ' . $session->session_no . ' started.', 201);
    }

    /* ══════════════════════════════════════════════════════════
       SHEET — count entry form data
    ══════════════════════════════════════════════════════════ */

    public function sheet(StockCountSession $session): JsonResponse
    {
        $lines = $session->lines()
            ->orderBy('category_name')
            ->orderBy('product_name')
            ->get();

        return $this->success([
            'session' => $this->mapSessionSummary($session),
            'lines'   => $lines->map(fn (StockCountLine $l) => $this->mapLine($l))->values(),
        ], '');
    }

    /* ══════════════════════════════════════════════════════════
       SAVE — save physical counts (keeps session open for edits)
    ══════════════════════════════════════════════════════════ */

    public function save(Request $request, StockCountSession $session): JsonResponse
    {
        if (!$session->isEditable()) {
            return $this->error('This session is already completed.', ['session' => ['This session is already completed.']], 422);
        }

        $request->validate([
            'counts'            => 'required|array',
            'counts.*.line_id'  => 'required|exists:stock_count_lines,id',
            'counts.*.qty'      => 'nullable|numeric|min:0',
            'counts.*.notes'    => 'nullable|string|max:255',
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

        return $this->success(null, 'Progress saved. Continue counting or submit when done.');
    }

    /* ══════════════════════════════════════════════════════════
       COMPLETE — apply stock adjustments + flag low/critical
    ══════════════════════════════════════════════════════════ */

    public function complete(Request $request, StockCountSession $session): JsonResponse
    {
        if (!$session->isEditable()) {
            return $this->error('This session is already completed.', ['session' => ['This session is already completed.']], 422);
        }

        $countedLines = $session->lines()->whereNotNull('physical_qty')->count();
        if ($countedLines === 0) {
            return $this->error(
                'Please enter at least one physical count before submitting.',
                ['session' => ['Please enter at least one physical count before submitting.']],
                422
            );
        }

        $location = InventoryLocation::where('code', 'MAIN-STORE')->first()
            ?? InventoryLocation::where('is_active', true)->first();

        $summary = [];

        DB::transaction(function () use ($session, $location, &$summary) {
            $adjustedCount = 0;
            $lowCount      = 0;
            $criticalCount = 0;

            $lines = $session->lines()->whereNotNull('physical_qty')->get();

            foreach ($lines as $line) {
                $status = StockCountLine::deriveStatus(
                    $line->physical_qty,
                    $line->minimum_qty,
                    $line->reorder_level
                );
                $line->stock_status = $status;

                if ($status === 'low')      $lowCount++;
                if (in_array($status, ['critical', 'out'])) $criticalCount++;

                if ($line->variance !== null && abs($line->variance) > 0.001) {
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

            $countedLines = $session->lines()->whereNotNull('physical_qty')->count();

            $session->update([
                'status'               => 'completed',
                'items_counted'        => $countedLines,
                'items_adjusted'       => $adjustedCount,
                'low_stock_count'      => $lowCount,
                'critical_stock_count' => $criticalCount,
                'next_count_due'       => now()->addDays(15)->toDateString(),
                'completed_by'         => auth()->id(),
                'completed_at'         => now(),
            ]);

            $summary = [
                'session_no'    => $session->session_no,
                'items_counted' => $countedLines,
                'adjusted'      => $adjustedCount,
                'low'           => $lowCount,
                'critical'      => $criticalCount,
                'next_due'      => now()->addDays(15)->format('d M Y'),
            ];
        });

        return $this->success($summary, 'Stock count ' . $session->session_no . ' completed. Adjustments applied.');
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

    /** Paginate any query builder with clamped limit (mirrors InventoryController style). */
    private function paginate($query, Request $request, int $default = 20)
    {
        $limit = (int) $request->query('limit', $default);
        $limit = max(1, min($limit, 100));

        return $query->paginate($limit)->appends($request->query());
    }

    private function mapSession(StockCountSession $s): array
    {
        return [
            'id'                   => $s->id,
            'session_no'           => $s->session_no,
            'count_date'           => optional($s->count_date)->toDateString(),
            'status'               => $s->status,
            'items_counted'        => (int) $s->items_counted,
            'items_adjusted'       => (int) $s->items_adjusted,
            'low_stock_count'      => (int) $s->low_stock_count,
            'critical_stock_count' => (int) $s->critical_stock_count,
            'started_by'           => $s->startedBy?->name,
            'completed_by'         => $s->completedBy?->name,
            'completed_at'         => optional($s->completed_at)->toIso8601String(),
        ];
    }

    private function mapSessionSummary(StockCountSession $s): array
    {
        return [
            'id'         => $s->id,
            'session_no' => $s->session_no,
            'status'     => $s->status,
        ];
    }

    private function mapLine(StockCountLine $l): array
    {
        return [
            'id'                 => $l->id,
            'inventory_item_id'  => $l->inventory_item_id,
            'category_name'      => $l->category_name,
            'product_name'       => $l->product_name,
            'system_qty'         => (float) $l->system_qty,
            'physical_qty'       => $l->physical_qty !== null ? (float) $l->physical_qty : null,
            'variance'           => $l->variance !== null ? (float) $l->variance : null,
            'stock_status'       => $l->stock_status,
            'consumption_unit'   => $l->consumption_unit,
            'minimum_qty'        => (float) $l->minimum_qty,
            'reorder_level'      => (float) $l->reorder_level,
            'notes'              => $l->notes,
        ];
    }
}
