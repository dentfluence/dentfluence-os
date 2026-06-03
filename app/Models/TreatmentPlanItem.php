<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TreatmentPlanItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'treatment_plan_id',
        'tooth_number',
        'treatment_name',
        'unit_price',
        'units',
        'disc_pct',
        'disc_amount',
        'net_amount',
        'gst_pct',
        'gst_amount',
        'total',
        'aocp_applied',
        'option_rank',
        'status',
        'notes',
        'sort_order',
    ];

    protected $casts = [
        'unit_price'    => 'decimal:2',
        'disc_pct'      => 'decimal:2',
        'disc_amount'   => 'decimal:2',
        'net_amount'    => 'decimal:2',
        'gst_pct'       => 'decimal:2',
        'gst_amount'    => 'decimal:2',
        'total'         => 'decimal:2',
        'aocp_applied'  => 'boolean',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function plan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class, 'treatment_plan_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Recalculate derived fields from unit_price, units, disc_pct, gst_pct.
     * Call this before saving whenever pricing inputs change.
     */
    public function recalculate(): void
    {
        $gross          = (float) $this->unit_price * (int) $this->units;
        $discAmt        = $gross * ((float) $this->disc_pct / 100);
        $net            = $gross - $discAmt;
        $gstAmt         = $net * ((float) $this->gst_pct / 100);

        $this->disc_amount  = round($discAmt, 2);
        $this->net_amount   = round($net, 2);
        $this->gst_amount   = round($gstAmt, 2);
        $this->total        = round($net + $gstAmt, 2);
    }
}
