<?php

namespace App\Models;

use App\Models\Finance\FinanceExpense;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * LabCase — a dental lab work order (Lab Module v3).
 *
 * Workflow:
 *   draft → order_placed → impression_sent | scan_sent
 *        → trial_received ↔ trial_returned (repeatable loop)
 *        → final_received → complete
 *   Any open status → rejected
 *
 * Priority:  routine | urgent | express
 * "Overdue" is computed (expected date passed, not yet at final_received) — never stored.
 *
 * Every create / update / status change is logged automatically to
 * lab_case_events (append-only timeline + audit trail).
 */
class LabCase extends Model
{
    use SoftDeletes, \App\Traits\BelongsToBranch;

    // ── Constants — single source of truth ──────────────────────────────

    public const STATUSES = [
        'draft', 'order_placed', 'impression_sent', 'scan_sent',
        'trial_received', 'trial_returned',
        'final_received', 'complete', 'rejected',
    ];

    /** Statuses where the work is still pending from / at the lab */
    public const OPEN_STATUSES = [
        'order_placed', 'impression_sent', 'scan_sent',
        'trial_received', 'trial_returned',
    ];

    /**
     * Allowed next statuses for one-click transitions.
     * trial_received → trial_returned is the repeatable loop.
     */
    public const STATUS_FLOW = [
        'draft'           => ['order_placed'],
        'order_placed'    => ['impression_sent', 'scan_sent', 'rejected'],
        'impression_sent' => ['trial_received', 'final_received', 'rejected'],
        'scan_sent'       => ['trial_received', 'final_received', 'rejected'],
        'trial_received'  => ['trial_returned', 'final_received', 'rejected'],
        'trial_returned'  => ['trial_received', 'rejected'],   // back to lab
        'final_received'  => ['complete'],
        'complete'        => [],
        'rejected'        => [],
    ];

    public const STATUS_LABELS = [
        'draft'           => 'Draft',
        'order_placed'    => 'Order Placed',
        'impression_sent' => 'Impression Sent',
        'scan_sent'       => 'Scan Sent',
        'trial_received'  => 'Trial Received',
        'trial_returned'  => 'Trial Returned',
        'final_received'  => 'Final Work In',
        'complete'        => 'Complete',
        'rejected'        => 'Rejected',
    ];

    /** Tailwind badge classes per status */
    public const STATUS_COLORS = [
        'draft'           => 'bg-gray-100 text-gray-600',
        'order_placed'    => 'bg-purple-100 text-purple-700',
        'impression_sent' => 'bg-indigo-100 text-indigo-700',
        'scan_sent'       => 'bg-indigo-100 text-indigo-700',
        'trial_received'  => 'bg-amber-100 text-amber-700',
        'trial_returned'  => 'bg-orange-100 text-orange-700',
        'final_received'  => 'bg-blue-100 text-blue-700',
        'complete'        => 'bg-green-100 text-green-700',
        'rejected'        => 'bg-red-100 text-red-700',
    ];

    public const PRIORITIES = ['routine', 'urgent', 'express'];

    public const PRIORITY_COLORS = [
        'routine' => 'bg-gray-100 text-gray-600',
        'urgent'  => 'bg-amber-100 text-amber-700',
        'express' => 'bg-red-100 text-red-700',
    ];

    public const PAYMENT_STATUSES = ['pending', 'paid', 'monthly_account'];

    /**
     * Work categories → subtypes.
     * Used by the lab case form, analytics, and filters.
     * Single source of truth — subtypes in Alpine are generated from this via JSON.
     */
    public const WORK_CATEGORIES = [
        'Crown & Bridge'          => [
            'Zirconia',
            'Layered Zirconia',
            'PFM (Porcelain Fused to Metal)',
            'E-max (All Ceramic)',
            'Full Metal / Cast Metal',
            'Temporary Crown (PMMA)',
            'Maryland Bridge',
        ],
        'Implant Prosthesis'      => [
            'Implant Crown – Cement Retained',
            'Implant Crown – Screw Retained',
            'Implant Bridge',
            'Custom Abutment',
            'Stock Abutment',
            'Bar Overdenture',
            'All-on-4 / Hybrid Bridge',
            'Locator Attachment',
        ],
        'Removable Prosthesis'    => [
            'Complete Denture – Upper',
            'Complete Denture – Lower',
            'Complete Denture – Both',
            'Cast Partial Denture (Metal Framework)',
            'Acrylic Partial Denture',
            'Flexible Denture (Valplast)',
            'Immediate Denture',
            'Overdenture',
            'Soft Liner / Reline',
        ],
        'Veneer'                  => [
            'E-max Veneer',
            'Zirconia Veneer',
            'Feldspathic Porcelain Veneer',
            'Composite Veneer',
            'Prepless Veneer',
        ],
        'Inlay / Onlay'           => [
            'E-max Inlay',
            'Zirconia Inlay',
            'E-max Onlay',
            'Zirconia Onlay',
            'Cast Metal Inlay / Onlay',
        ],
        'Orthodontics'            => [
            'Clear Aligner',
            'Hawley Retainer',
            'Essix Retainer',
            'Space Maintainer (Fixed)',
            'Space Maintainer (Removable)',
            'Expansion Appliance',
            'Functional Appliance (Twin Block)',
            'Habit Breaking Appliance',
            'Study Model',
        ],
        'Occlusal Guard / Splint' => [
            'Night Guard – Soft',
            'Night Guard – Hard',
            'Bruxism Guard (Dual Laminate)',
            'Occlusal Splint (Michigan)',
            'Occlusal Splint (Tanner)',
            'TMJ Splint (Anterior Repositioning)',
            'Sports Mouthguard',
        ],
        'Surgical Guide'          => [
            'Implant Surgical Guide',
            'Bone Reduction Guide',
            'Extraction Guide',
            'Osteotomy Guide',
        ],
        'Wax-up / Mock-up'        => [
            'Diagnostic Wax-up',
            'Digital Smile Design (DSD)',
            'Composite Mock-up',
        ],
        'Bleaching Tray'          => [
            'Upper Tray',
            'Lower Tray',
            'Both Trays',
        ],
        'Custom Impression Tray'  => [
            'Upper',
            'Lower',
            'Both',
        ],
        'Other'                   => [],
    ];

    /** Per-tooth work types for line items */
    public const ITEM_WORK_TYPES = [
        'Crown', 'Pontic', 'Implant Crown', 'Veneer',
        'Inlay', 'Onlay', 'Abutment', 'Denture Unit',
        'Retainer', 'Aligner', 'Splint', 'Other',
    ];

    /** Common shade guide values for the shade dropdown */
    public const SHADES = [
        'A1', 'A2', 'A3', 'A3.5', 'A4',
        'B1', 'B2', 'B3', 'B4',
        'C1', 'C2', 'C3', 'C4',
        'D2', 'D3', 'D4',
        'BL1', 'BL2', 'BL3', 'BL4',
    ];

    // ── Repeat / remake reasons ──────────────────────────────────────────

    public const REPEAT_REASONS = [
        'shade_mismatch'      => 'Shade Mismatch',
        'fit_issue'           => 'Fit Issue',
        'lab_error'           => 'Lab Error',
        'patient_changed_mind'=> 'Patient Changed Mind',
        'doctor_adjustment'   => 'Doctor Adjustment',
        'other'               => 'Other',
    ];

    // ── Billing lifecycle statuses (Phase 2) ────────────────────────────

    public const BILLING_STATUSES = [
        'unbilled'           => 'Unbilled',
        'in_reconciliation'  => 'In Reconciliation',
        'billed'             => 'Billed',
        'paid'               => 'Paid',
    ];

    public const BILLING_STATUS_COLORS = [
        'unbilled'          => 'bg-gray-100 text-gray-600',
        'in_reconciliation' => 'bg-yellow-100 text-yellow-700',
        'billed'            => 'bg-blue-100 text-blue-700',
        'paid'              => 'bg-green-100 text-green-700',
    ];

    protected $fillable = [
        'case_number', 'branch_id',
        'patient_id', 'doctor_id', 'lab_vendor_id', 'technician_name',
        'work_category', 'work_subtype', 'priority', 'status',
        'sent_date', 'expected_return_date',
        'order_placed_date', 'impression_sent_date', 'final_received_date',
        'received_date', 'delivered_date',
        'trial_round',
        'lab_cost', 'estimated_cost', 'payment_status',
        'billing_status', 'reconciliation_id',
        'recall_queued_at',   // recall-engine cooldown stamp
        'expense_id', 'active_task_id',
        'is_remake', 'remake_of_id', 'repeat_reason',
        'instructions', 'internal_notes',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'sent_date'             => 'date',
        'expected_return_date'  => 'date',
        'order_placed_date'     => 'date',
        'impression_sent_date'  => 'date',
        'final_received_date'   => 'date',
        'received_date'         => 'date',
        'delivered_date'        => 'date',
        'lab_cost'              => 'decimal:2',
        'estimated_cost'        => 'decimal:2',
        'is_remake'             => 'boolean',
        'trial_round'           => 'integer',
    ];

    // ── Boot — auto numbering, audit columns, automatic timeline ─────────

    protected static function booted(): void
    {
        static::creating(function (self $case) {
            // Auto case number: LAB-2026-0001 (withTrashed so numbers never repeat)
            if (empty($case->case_number)) {
                $year  = now()->format('Y');
                $count = static::withTrashed()->whereYear('created_at', $year)->count() + 1;
                $case->case_number = sprintf('LAB-%s-%04d', $year, $count);
            }

            $case->branch_id  = $case->branch_id ?: (auth()->user()?->branch_id ?? 1);
            $case->created_by = $case->created_by ?: auth()->id();
            $case->updated_by = $case->updated_by ?: auth()->id();
        });

        static::created(function (self $case) {
            $case->logEvent('created', 'Case created');
        });

        static::updating(function (self $case) {
            $case->updated_by = auth()->id() ?? $case->updated_by;
        });

        static::updated(function (self $case) {
            // Automatic, tamper-proof status history
            if ($case->wasChanged('status')) {
                $from = $case->getOriginal('status');
                $case->logEvent(
                    'status_changed',
                    self::STATUS_LABELS[$case->status] ?? ucfirst($case->status),
                    ['from_status' => $from, 'to_status' => $case->status]
                );
            }
        });

        static::deleted(function (self $case) {
            if (!$case->isForceDeleting()) {
                $case->logEvent('archived', 'Case archived');
            }
        });

        static::restored(function (self $case) {
            $case->logEvent('restored', 'Case restored from archive');
        });
    }

    // ── Relationships ────────────────────────────────────────────────────

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(LabVendor::class, 'lab_vendor_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LabCaseItem::class)->orderBy('sort_order');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(LabCaseAttachment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(LabCaseEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(FinanceExpense::class, 'expense_id');
    }

    /** Doctor's quality rating after case completion (hasOne) */
    public function rating(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(LabCaseRating::class);
    }

    /** Structured clinical prescription (hasOne — one per case) */
    public function prescription(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(LabCasePrescription::class);
    }

    public function remakeOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'remake_of_id');
    }

    /** Phase 2: Monthly reconciliation this case is part of */
    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(LabMonthlyReconciliation::class, 'reconciliation_id');
    }

    /** Phase 2: Billing status label + color helpers */
    public function billingStatusLabel(): string
    {
        return self::BILLING_STATUSES[$this->billing_status ?? 'unbilled'] ?? 'Unbilled';
    }

    public function billingStatusColor(): string
    {
        return self::BILLING_STATUS_COLORS[$this->billing_status ?? 'unbilled'] ?? 'bg-gray-100 text-gray-600';
    }

    /** Cost variance between estimated and actual (Phase 2) */
    public function costVariance(): ?float
    {
        if ($this->estimated_cost === null || $this->lab_cost === null) {
            return null;
        }
        return (float) $this->lab_cost - (float) $this->estimated_cost;
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Timeline / audit helper ──────────────────────────────────────────

    /**
     * Append an immutable event to the case timeline.
     * $extras may include from_status, to_status, meta.
     */
    public function logEvent(string $type, string $description, array $extras = []): LabCaseEvent
    {
        return $this->events()->create([
            'event_type'  => $type,
            'description' => $description,
            'from_status' => $extras['from_status'] ?? null,
            'to_status'   => $extras['to_status'] ?? null,
            'meta'        => $extras['meta'] ?? null,
            'user_id'     => auth()->id(),
        ]);
    }

    // ── Computed state ───────────────────────────────────────────────────

    /** Overdue = expected date passed and final work not yet received */
    public function isOverdue(): bool
    {
        return $this->expected_return_date !== null
            && $this->final_received_date === null
            && in_array($this->status, self::OPEN_STATUSES, true)
            && $this->expected_return_date->lt(now()->startOfDay());
    }

    public function isDueToday(): bool
    {
        return $this->expected_return_date !== null
            && in_array($this->status, self::OPEN_STATUSES, true)
            && $this->final_received_date === null
            && $this->expected_return_date->isToday();
    }

    /** True when in the trial loop (trial_received or trial_returned) */
    public function isInTrialLoop(): bool
    {
        return in_array($this->status, ['trial_received', 'trial_returned'], true);
    }

    /** Trial round label e.g. "Trial 2" */
    public function trialLabel(): string
    {
        return $this->trial_round > 0 ? 'Trial ' . $this->trial_round : '';
    }

    /** Aging in days since order_placed_date, null for drafts */
    public function agingDays(): ?int
    {
        $start = $this->order_placed_date ?? $this->sent_date;
        if ($start === null) return null;

        $end = $this->final_received_date ?? now()->startOfDay();
        return (int) $start->diffInDays($end);
    }

    public function agingLabel(): string
    {
        $days = $this->agingDays();
        return $days === null ? '—' : $days . ' ' . ($days === 1 ? 'Day' : 'Days');
    }

    /** Days the case is overdue by (for huddle red highlight) */
    public function overdueDays(): int
    {
        return $this->isOverdue()
            ? (int) $this->expected_return_date->diffInDays(now()->startOfDay())
            : 0;
    }

    /** Human-readable label for the repeat reason */
    public function repeatReasonLabel(): string
    {
        return self::REPEAT_REASONS[$this->repeat_reason ?? ''] ?? 'Repeat Work';
    }

    /** Compact tooth summary for the table, e.g. "11, 12, 13" or "11, 12, 13 +2 more" */
    public function toothSummary(): string
    {
        $teeth = $this->items->pluck('tooth_number')->filter()->unique()->values();

        if ($teeth->isEmpty()) {
            return '—';
        }

        if ($teeth->count() <= 4) {
            return $teeth->implode(', ');
        }

        return $teeth->take(3)->implode(', ') . ' +' . ($teeth->count() - 3) . ' more';
    }

    /** Allowed next statuses from current status (drives one-click buttons) */
    public function nextStatuses(): array
    {
        return self::STATUS_FLOW[$this->status] ?? [];
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, $this->nextStatuses(), true);
    }

    // ── Presentation helpers ─────────────────────────────────────────────

    public function statusLabel(): string
    {
        if ($this->isOverdue()) {
            return 'Overdue';
        }

        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }

    public function statusColor(): string
    {
        if ($this->isOverdue()) {
            return 'bg-red-100 text-red-700';
        }

        return self::STATUS_COLORS[$this->status] ?? 'bg-gray-100 text-gray-700';
    }

    public function priorityColor(): string
    {
        return self::PRIORITY_COLORS[$this->priority] ?? 'bg-gray-100 text-gray-600';
    }

    public static function subtypesFor(string $category): array
    {
        return self::WORK_CATEGORIES[$category] ?? [];
    }

    /** @deprecated Legacy alias used by the old v1 blade — removed with Phase 3 UI */
    public function workTypeLabel(): string
    {
        return trim($this->work_category . ($this->work_subtype ? " — {$this->work_subtype}" : '')) ?: '—';
    }

    // ── Query scopes (dashboard cards, filters, huddle, analytics) ───────

    /** Anything not yet complete/rejected */
    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNotIn('status', ['complete', 'rejected']);
    }

    public function scopeStatus(Builder $q, string $status): Builder
    {
        return $q->where('status', $status);
    }

    public function scopeOverdue(Builder $q): Builder
    {
        return $q->whereIn('status', self::OPEN_STATUSES)
                 ->whereNull('final_received_date')
                 ->whereDate('expected_return_date', '<', now()->toDateString());
    }

    public function scopeDueToday(Builder $q): Builder
    {
        return $q->whereIn('status', self::OPEN_STATUSES)
                 ->whereNull('final_received_date')
                 ->whereDate('expected_return_date', now()->toDateString());
    }

    public function scopeDueTomorrow(Builder $q): Builder
    {
        return $q->whereIn('status', self::OPEN_STATUSES)
                 ->whereNull('final_received_date')
                 ->whereDate('expected_return_date', now()->addDay()->toDateString());
    }

    /** Open cases at the lab for more than N days */
    public function scopePendingMoreThan(Builder $q, int $days = 15): Builder
    {
        return $q->whereIn('status', self::OPEN_STATUSES)
                 ->whereDate('order_placed_date', '<=', now()->subDays($days)->toDateString());
    }

    /** Final work received — ready to deliver to patient */
    public function scopeAwaitingDelivery(Builder $q): Builder
    {
        return $q->where('status', 'final_received');
    }

    /** Cases currently in the trial loop */
    public function scopeInTrialLoop(Builder $q): Builder
    {
        return $q->whereIn('status', ['trial_received', 'trial_returned']);
    }

    /** Repeat / remake cases */
    public function scopeRemakes(Builder $q): Builder
    {
        return $q->where('is_remake', true);
    }

    public function scopeThisMonth(Builder $q): Builder
    {
        return $q->whereMonth('created_at', now()->month)
                 ->whereYear('created_at', now()->year);
    }

    /** Toolbar search: patient name/phone, case number, doctor, vendor, tooth */
    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $q;
        }

        return $q->where(function (Builder $w) use ($term) {
            $like = "%{$term}%";
            $w->where('case_number', 'like', $like)
              ->orWhereHas('patient', fn (Builder $p) =>
                  $p->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('alternate_phone', 'like', $like))
              ->orWhereHas('doctor', fn (Builder $d) => $d->where('name', 'like', $like))
              ->orWhereHas('vendor', fn (Builder $v) => $v->where('name', 'like', $like))
              ->orWhereHas('items', fn (Builder $i) => $i->where('tooth_number', 'like', $like));
        });
    }
}
