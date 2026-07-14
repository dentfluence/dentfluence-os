<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use App\Traits\Auditable;

class TreatmentPlan extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    /** Tag audit-log entries for this model with the "treatment_plans" module. */
    protected $auditModule = 'treatment_plans';

    protected $fillable = [
        'plan_uuid',          // stable public identifier
        'patient_id',
        'consultation_id',
        'doctor_id',          // treating doctor shown on the printed plan
        'plan_name',          // internal name e.g. "Treatment Plan A"
        'display_order',      // ordering within a consultation (1, 2, 3 …)
        'plan_type',
        'status',
        'accepted_at',        // null = not accepted; set when patient confirms
        'rows',               // legacy JSON — kept for backward compat
        'total',
        'overall_disc_pct',
        'aocp',
        'aocp_plan',
        'created_by',
        'estimated_duration', // e.g. "3–4 Months"
        'visit_count',        // approximate visits
        'doctor_notes',       // optional recommendation on print
    ];

    protected $casts = [
        'rows'             => 'array',
        'aocp'             => 'boolean',
        'total'            => 'decimal:2',
        'overall_disc_pct' => 'decimal:2',
        'accepted_at'      => 'datetime',
    ];

    // ── Boot — auto-assign uuid + display_order ───────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (self $plan) {
            if (empty($plan->plan_uuid)) {
                $plan->plan_uuid = (string) Str::uuid();
            }
            if (empty($plan->display_order) && $plan->consultation_id) {
                $plan->display_order = static::where('consultation_id', $plan->consultation_id)->count() + 1;
            }
        });
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    /** Treating doctor for this plan (falls back to consultation doctor on prints). */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TreatmentPlanItem::class)->orderBy('sort_order');
    }

    public function billingPrompts(): HasMany
    {
        return $this->hasMany(BillingPrompt::class, 'trigger_id')
                    ->where('trigger_type', 'consultation');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function getIsAcceptedAttribute(): bool
    {
        return !is_null($this->accepted_at);
    }

    public function getComputedTotalAttribute(): float
    {
        return (float) $this->items()->sum('total');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForConsultation($query, int $consultationId)
    {
        return $query->where('consultation_id', $consultationId)->orderBy('display_order');
    }
}
