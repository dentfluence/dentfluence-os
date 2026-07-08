<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class StockMovement extends Model
{
    protected $table = 'stock_movements';

    protected $fillable = [
        'inventory_item_id', 'movement_type', 'qty',
        'from_location_id', 'to_location_id',
        'batch_no', 'expiry_date', 'manufacturing_date',
        'unit_cost', 'total_cost',
        'reference_type', 'reference_id', 'reversal_of_id',
        'notes', 'created_by', 'reversed_at', 'reversed_by',
    ];

    protected $casts = [
        'qty'          => 'float',
        'unit_cost'    => 'float',
        'total_cost'   => 'float',
        'expiry_date'  => 'date',
        'manufacturing_date' => 'date',
        'reversed_at'  => 'datetime',
    ];

    /* ── Boot: update live stock table after every movement ── */

    protected static function booted(): void
    {
        static::created(function (StockMovement $movement) {
            $movement->updateLiveStock();
        });
    }

    /**
     * Update the inventory_stocks table based on this movement.
     *
     * Rules:
     *   stock_in / opening_stock  → add qty to to_location
     *   stock_out / expired / damaged / treatment_usage → subtract qty from from_location
     *   transfer                  → subtract from from_location, add to to_location
     *   adjustment                → qty can be positive (add) or negative (remove) at to_location
     *   sterilization / maintenance → no qty change (status event only)
     */
    public function updateLiveStock(): void
    {
        $type  = $this->movement_type;
        $qty   = abs($this->qty);
        $itemId = $this->inventory_item_id;

        // Movements that ADD stock somewhere
        $addTypes = ['stock_in', 'opening_stock'];

        // Movements that REMOVE stock somewhere
        $removeTypes = ['stock_out', 'expired', 'damaged', 'treatment_usage', 'retail_sale'];

        if (in_array($type, $addTypes)) {
            $this->adjustStock($itemId, $this->to_location_id, +$qty);

        } elseif (in_array($type, $removeTypes)) {
            $this->adjustStock($itemId, $this->from_location_id, -$qty);

        } elseif ($type === 'transfer') {
            $this->adjustStock($itemId, $this->from_location_id, -$qty);
            $this->adjustStock($itemId, $this->to_location_id,   +$qty);

        } elseif ($type === 'adjustment') {
            // adjustment qty is signed: +ve = add, -ve = remove
            $signedQty = $this->qty;
            $locationId = $this->to_location_id ?? $this->from_location_id;
            $this->adjustStock($itemId, $locationId, $signedQty);
        }
        // sterilization / maintenance = status events, no qty movement
    }

    /**
     * Upsert the inventory_stocks record for a given item+location.
     */
    private function adjustStock(int $itemId, ?int $locationId, float $delta): void
    {
        if (! $locationId) return;

        $stock = InventoryStock::firstOrCreate(
            ['inventory_item_id' => $itemId, 'location_id' => $locationId],
            ['available_qty' => 0, 'reserved_qty' => 0]
        );

        $stock->available_qty = max(0, $stock->available_qty + $delta);
        $stock->save();
    }

    /* ── Relationships ── */

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'to_location_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The original entry this movement was created to cancel out (if it's a reversal). */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'reversal_of_id');
    }

    /** The compensating entry that cancelled this movement out (if it was reversed). */
    public function reversalEntry(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(StockMovement::class, 'reversal_of_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    /** Only manual quick-adjustments can be reversed — GRN/consumption etc. have their own flows. */
    public function isReversible(): bool
    {
        return $this->reference_type === 'manual_adjustment'
            && is_null($this->reversed_at)
            && is_null($this->reversal_of_id);
    }

    /* ── Helpers ── */

    public function getMovementLabel(): string
    {
        return match($this->movement_type) {
            'stock_in'         => 'Stock In',
            'stock_out'        => 'Stock Out',
            'transfer'         => 'Transfer',
            'adjustment'       => 'Adjustment',
            'expired'          => 'Expired',
            'damaged'          => 'Damaged',
            'treatment_usage'  => 'Treatment Usage',
            'sterilization'    => 'Sterilization',
            'maintenance'      => 'Maintenance',
            'opening_stock'    => 'Opening Stock',
            'retail_sale'      => 'Retail Sale',
            default            => ucfirst($this->movement_type),
        };
    }

    public function getMovementColor(): string
    {
        return match($this->movement_type) {
            'stock_in', 'opening_stock' => '#1a7a45',
            'stock_out', 'treatment_usage', 'retail_sale' => '#6a0f70',
            'transfer'                  => '#1a5ea8',
            'adjustment'                => '#a05c00',
            'expired', 'damaged'        => '#b52020',
            'sterilization'             => '#0e7b89',
            'maintenance'               => '#5c4800',
            default                     => '#555',
        };
    }
}
