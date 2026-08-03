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
        'plan_date',          // date printed on the plan / base for validity window
        'plan_name',          // internal name e.g. "Treatment Plan A"
        'display_order',      // ordering within a consultation (1, 2, 3 …)
        'plan_type',
        'status',
        'accepted_at',        // null = not accepted; set when patient confirms
        'presented_at',       // Slice 2.2 — FIRST time the patient was shown this plan.
                              // Never overwritten; never implies a decision.
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
        'plan_date'        => 'date',
        'aocp'             => 'boolean',
        'total'            => 'decimal:2',
        'overall_disc_pct' => 'decimal:2',
        'accepted_at'      => 'datetime',
        'presented_at'     => 'datetime',
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

    /**
     * The append-only ledger of what the patient decided about this plan
     * (Slice 2.3). Newest first — the head of this list is the current decision.
     */
    public function decisions(): HasMany
    {
        return $this->hasMany(PlanDecision::class)->latest('created_at')->latest('id');
    }

    /**
     * The current patient decision — DERIVED as the latest ledger row, never
     * stored. A plan deferred in July and accepted in August keeps both rows;
     * this returns the August one.
     */
    public function currentDecision(): ?PlanDecision
    {
        return $this->relationLoaded('decisions')
            ? $this->decisions->first()
            : $this->decisions()->first();
    }

    /**
     * Presented, and the patient has not decided anything yet.
     * NOT the same as deferred — deferred is an explicit patient choice.
     */
    public function getIsDecisionPendingAttribute(): bool
    {
        return ! is_null($this->presented_at)
            && is_null($this->accepted_at)
            && $this->status !== 'cancelled'
            && is_null($this->currentDecision());
    }

    public function items(): HasMany
    {
        return $this->hasMany(TreatmentPlanItem::class)->orderBy('sort_order');
    }

    /**
     * The single sales Opportunity linked to this plan (one per plan, ever —
     * keyed by treatment_plan_id). Populated by TreatmentPlanOpportunitySync
     * when the plan is presented / accepted / declined.
     */
    public function opportunity(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(TreatmentOpportunity::class);
    }

    // REMOVED (Consultations Slice 4, 2026-08-03): billingPrompts() was
    //     hasMany(BillingPrompt::class, 'trigger_id')->where('trigger_type', 'consultation')
    // i.e. it matched billing_prompts rows written by Minor Visit (trigger_id =
    // consultation id) against a *treatment plan* id — a cross-entity ID collision
    // that could surface other patients' prompts. It had zero callers (verified by
    // repo-wide grep; the Billing tab reads prompts via PatientProfileService's
    // direct BillingPrompt query). There is currently no valid way to scope prompts
    // to a plan: prompts are keyed to consultations or treatment visits, and
    // treatment_visits has no treatment_plan_id. If plan-level prompts are ever
    // needed, introduce trigger_type='treatment_plan' and a matching writer first.

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function getIsAcceptedAttribute(): bool
    {
        return !is_null($this->accepted_at);
    }

    /** Slice 2.2 — has the patient actually been shown this plan? */
    public function getIsPresentedAttribute(): bool
    {
        return ! is_null($this->presented_at);
    }

    // Slice 2.3 note: getIsDecisionPendingAttribute() moved up beside the
    // decisions() relation — it now consults the real decision ledger instead
    // of approximating "no decision" as "not accepted".

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
