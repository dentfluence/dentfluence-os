<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One tooth of a treatment-plan item.
 *
 * This is what makes partial multi-tooth invoicing possible: a plan item for
 * "Implant on 24 & 36" has two rows here. Billing tooth 24 flips only that row
 * to 'invoiced' and links it to the invoice line; tooth 36 stays 'pending' and
 * keeps the parent plan open until it's completed too.
 */
class TreatmentPlanItemTooth extends Model
{
    protected $table = 'treatment_plan_item_teeth';

    protected $fillable = [
        'treatment_plan_item_id',
        'tooth_number',
        'status',          // pending | completed | invoiced
        'invoice_item_id',
        'invoiced_at',
    ];

    protected $casts = [
        'invoiced_at' => 'datetime',
    ];

    const STATUS_PENDING   = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_INVOICED  = 'invoiced';

    public function planItem(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlanItem::class, 'treatment_plan_item_id');
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class, 'invoice_item_id');
    }
}
