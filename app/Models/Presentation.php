<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use App\Traits\Auditable;

/**
 * Presentation — the "Smart Treatment Presentation" module's own record.
 *
 * Sits between Treatment Plan (clinical source of truth) and Treatment
 * Accepted. Read-only against Patient/Consultation/TreatmentPlan/Invoice —
 * never writes back to any of them. Acceptance is always recorded by the
 * Treatment Plan module itself (see PresentationController@markAccepted in
 * a later slice), never by this model directly.
 *
 * See docs/plan-smart-treatment-presentation.md for the full design.
 */
class Presentation extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    /** Tag audit-log entries for this model with the "presentations" module. */
    protected $auditModule = 'presentations';

    protected $fillable = [
        'uuid',
        'treatment_plan_id',
        'patient_id',
        'consultation_id',
        'status',
        'ai_summary_text',
        'doctor_message',
        'follow_up_notes',
        'reviewed_at',
        'sent_at',
        'first_viewed_at',
        'last_viewed_at',
        'view_count',
        'declined_at',
        'created_by',
    ];

    protected $casts = [
        'reviewed_at'      => 'datetime',
        'sent_at'          => 'datetime',
        'first_viewed_at'  => 'datetime',
        'last_viewed_at'   => 'datetime',
        'view_count'       => 'integer',
        'declined_at'      => 'datetime',
    ];

    // ── Status lifecycle ───────────────────────────────────────────────────
    // Only DRAFT and FINALIZED are used/settable in Slice A+B. The rest are
    // reserved so later slices (Send/Share, Activity tracking) only need to
    // ADD behaviour, not rename statuses already in use.

    public const STATUS_DRAFT                = 'draft';
    public const STATUS_FINALIZED             = 'finalized';
    public const STATUS_SENT                  = 'sent';
    public const STATUS_VIEWED                = 'viewed';
    public const STATUS_ACCEPTED              = 'accepted';
    public const STATUS_DECLINED              = 'declined';
    public const STATUS_FOLLOW_UP_REQUIRED    = 'follow_up_required';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT             => 'Draft',
        self::STATUS_FINALIZED         => 'Finalized',
        self::STATUS_SENT              => 'Sent',
        self::STATUS_VIEWED            => 'Viewed',
        self::STATUS_ACCEPTED          => 'Accepted',
        self::STATUS_DECLINED          => 'Declined',
        self::STATUS_FOLLOW_UP_REQUIRED => 'Follow-up Required',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $presentation) {
            if (empty($presentation->uuid)) {
                $presentation->uuid = (string) Str::uuid();
            }
            if (empty($presentation->status)) {
                $presentation->status = self::STATUS_DRAFT;
            }
        });
    }

    // ── Relationships (all read-only from this module's perspective) ──────

    public function treatmentPlan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function snapshot(): HasOne
    {
        return $this->hasOne(PresentationSnapshot::class);
    }

    public function mediaItems(): HasMany
    {
        return $this->hasMany(PresentationMediaItem::class);
    }

    public function accessTokens(): HasMany
    {
        return $this->hasMany(PresentationAccessToken::class);
    }

    /** The token that would currently work if a patient clicked it — newest valid one, if any. */
    public function activeAccessToken(): ?PresentationAccessToken
    {
        return $this->accessTokens()
            ->whereNull('revoked_at')
            ->latest()
            ->get()
            ->first(fn (PresentationAccessToken $t) => $t->isValid());
    }

    /**
     * The live public microsite URL for a given treatment plan's most recent
     * Presentation, if one exists and still has a valid (non-revoked,
     * non-expired) link — null otherwise. Used to put a "scan to view
     * online" QR on print templates without forcing a Presentation to be
     * created just for printing (see TreatmentPlanController::printView and
     * ConsultationController::print).
     */
    public static function activeLinkUrlForPlan(int $treatmentPlanId): ?string
    {
        $presentation = static::where('treatment_plan_id', $treatmentPlanId)->latest('id')->first();
        $token = $presentation?->activeAccessToken();

        return $token ? route('presentations.public.show', $token->token) : null;
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }

    public function getIsFinalizedAttribute(): bool
    {
        return ! is_null($this->reviewed_at) && $this->status !== self::STATUS_DRAFT;
    }

    /**
     * Live cost summary — deliberately NOT snapshotted before Finalize, so the
     * Builder screen always reflects current Billing truth. Prefers the plan's
     * own Invoice (if one exists) over the plan's own totals, since Invoice is
     * the more authoritative billing record once it exists.
     */
    public function currentCostSummary(): array
    {
        $plan = $this->treatmentPlan;
        $invoice = $plan?->invoices()->latest()->first();

        if ($invoice) {
            return [
                'source'          => 'invoice',
                'subtotal'        => (float) $invoice->subtotal,
                'discount_amount' => (float) $invoice->discount_amount,
                'membership_discount' => (float) $invoice->membership_discount,
                'total'           => (float) $invoice->total_amount,
                'paid_amount'     => (float) $invoice->paid_amount,
                'balance_due'     => (float) $invoice->balance_due,
            ];
        }

        return [
            'source'          => 'treatment_plan',
            'subtotal'        => (float) ($plan->total ?? 0),
            'discount_amount' => 0.0,
            'membership_discount' => 0.0,
            'total'           => (float) ($plan->total ?? 0),
            'paid_amount'     => 0.0,
            'balance_due'     => (float) ($plan->total ?? 0),
        ];
    }
}
