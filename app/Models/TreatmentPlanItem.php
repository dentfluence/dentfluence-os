<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TreatmentPlanItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'treatment_plan_id',
        'treatment_id',       // link to Treatment master (single source of truth)
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
        'billing_progress',   // pending | partially_completed | completed | invoiced
        'invoiced_units',     // how many units already billed
        'notes',
        'sort_order',
        'recall_queued_at',   // recall-engine cooldown stamp
        'material_variants',
        'consent_required',   // Phase 2 refinement — per-item consent toggle
        'material_id',         // Phase 4 — final chosen material (optional, separate from variants JSON)
        'brand_id',            // Phase 4 — final chosen brand (optional)
    ];

    protected $casts = [
        'unit_price'        => 'decimal:2',
        'disc_pct'          => 'decimal:2',
        'disc_amount'       => 'decimal:2',
        'net_amount'        => 'decimal:2',
        'gst_pct'           => 'decimal:2',
        'gst_amount'        => 'decimal:2',
        'total'             => 'decimal:2',
        'aocp_applied'      => 'boolean',
        'material_variants' => 'array',
        'invoiced_units'    => 'integer',
        'consent_required'  => 'boolean',
    ];

    // Billing-progress states
    const PROGRESS_PENDING    = 'pending';
    const PROGRESS_PARTIAL    = 'partially_completed';
    const PROGRESS_COMPLETED  = 'completed';
    const PROGRESS_INVOICED   = 'invoiced';

    // ── Relationships ─────────────────────────────────────────────────────────

    public function plan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class, 'treatment_plan_id');
    }

    /** The master Treatment this item was created from (single source of truth). */
    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class, 'treatment_id');
    }

    /** One row per tooth — drives partial multi-tooth invoicing. */
    public function teeth(): HasMany
    {
        return $this->hasMany(TreatmentPlanItemTooth::class, 'treatment_plan_item_id');
    }

    /** Phase 4 — final chosen material for this item, if set. */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /** Phase 4 — final chosen brand for this item, if set. */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
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
