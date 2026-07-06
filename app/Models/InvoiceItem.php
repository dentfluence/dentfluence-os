<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'treatment_id',            // link to Treatment master (single source of truth)
        'treatment_plan_item_id',  // link back to the plan item that produced this line
        'inventory_item_id',       // link to a retail product sold on this line (nullable)
        'description',
        'tooth_number',
        'unit_price',
        'qty',
        'disc_pct',
        'disc_amount',
        'net_amount',
        'gst_pct',
        'gst_amount',
        'total',
        'sort_order',
    ];

    protected $casts = [
        'unit_price'  => 'decimal:2',
        'disc_pct'    => 'decimal:2',
        'disc_amount' => 'decimal:2',
        'net_amount'  => 'decimal:2',
        'gst_pct'     => 'decimal:2',
        'gst_amount'  => 'decimal:2',
        'total'       => 'decimal:2',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    /** Master treatment this line represents. */
    public function treatment()
    {
        return $this->belongsTo(Treatment::class, 'treatment_id');
    }

    /** Plan item this line was generated from (for partial-billing progress). */
    public function planItem()
    {
        return $this->belongsTo(TreatmentPlanItem::class, 'treatment_plan_item_id');
    }

    /** Retail product this line sold, if any (toothpaste, brushes, OTC medicines). */
    public function inventoryItem()
    {
        return $this->belongsTo(\App\Models\Inventory\InventoryItem::class, 'inventory_item_id');
    }

    /** Calculate and set all derived fields from unit_price, qty, disc_pct, gst_pct. */
    public function compute(): void
    {
        $gross      = round($this->unit_price * $this->qty, 2);
        $discAmt    = round($gross * ($this->disc_pct / 100), 2);
        $net        = $gross - $discAmt;
        $gstAmt     = round($net * ($this->gst_pct / 100), 2);
        $total      = $net + $gstAmt;

        $this->disc_amount = $discAmt;
        $this->net_amount  = $net;
        $this->gst_amount  = $gstAmt;
        $this->total       = $total;
    }
}
