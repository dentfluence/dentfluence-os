<?php

namespace App\Models;

use App\Models\Finance\FinanceVendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * LabVendor — a dental laboratory the clinic sends work to.
 *
 * Optionally linked to a FinanceVendor so lab spend flows into
 * the Finance/Expense module without duplicate master data.
 */
class LabVendor extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'branch_id', 'finance_vendor_id',
        'name', 'contact_person', 'phone', 'whatsapp_number', 'email', 'digital_email', 'address',
        'default_turnaround_days', 'payment_terms', 'credit_days',
        'is_active', 'notes', 'created_by',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'credit_days' => 'integer',
    ];

    // ── Relationships ────────────────────────────────────────────────────

    public function cases(): HasMany
    {
        return $this->hasMany(LabCase::class);
    }

    public function financeVendor(): BelongsTo
    {
        return $this->belongsTo(FinanceVendor::class, 'finance_vendor_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Phase 1 — Lab Master: multiple contacts per lab */
    public function contacts(): HasMany
    {
        return $this->hasMany(LabVendorContact::class, 'lab_vendor_id');
    }

    /** Phase 1 — Lab Master: service catalog with agreed rates. This IS the
     *  vendor's capability list — a category appears here only if a priced
     *  service exists for it, so there's no separate checklist to drift
     *  out of sync with what the vendor can actually be booked for. */
    public function services(): HasMany
    {
        return $this->hasMany(LabVendorService::class, 'lab_vendor_id');
    }

    /** Distinct work categories this vendor has an active priced service for */
    public function capabilityCategories(): array
    {
        return $this->services()->active()->distinct()->pluck('category')->filter()->values()->all();
    }

    /**
     * Phase 1: sync this Lab vendor to Finance so lab spend auto-flows.
     * Mirrors the pattern used by InventoryVendor::syncToFinance().
     */
    public function syncToFinance(): FinanceVendor
    {
        $data = [
            'vendor_name'  => $this->name,
            'company_name' => $this->name,
            'vendor_type'  => 'lab',
            'phone'        => $this->phone,
            'email'        => $this->email,
            'address'      => $this->address,
            'credit_days'  => $this->credit_days ?? 0,
            'is_active'    => $this->is_active,
            'notes'        => $this->notes,
        ];

        if ($this->finance_vendor_id) {
            $fv = FinanceVendor::find($this->finance_vendor_id);
            if ($fv) {
                $fv->update($data);
                return $fv;
            }
        }

        $fv = FinanceVendor::create($data);
        $this->updateQuietly(['finance_vendor_id' => $fv->id]);

        return $fv;
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    // ── Vendor analytics (query-based, no schema changes needed) ─────────

    /** Total cases ever sent to this vendor */
    public function totalCases(): int
    {
        return $this->cases()->count();
    }

    /** Total money given to this vendor */
    public function totalSpend(): float
    {
        return (float) $this->cases()->sum('lab_cost');
    }

    /** Average turnaround in days (sent → received), completed cases only */
    public function averageTurnaroundDays(): ?float
    {
        $avg = $this->cases()
            ->whereNotNull('sent_date')
            ->whereNotNull('received_date')
            ->selectRaw('AVG(DATEDIFF(received_date, sent_date)) as avg_days')
            ->value('avg_days');

        return $avg !== null ? round((float) $avg, 1) : null;
    }

    /** % of cases received after the expected return date */
    public function delayRate(): ?float
    {
        $completed = $this->cases()
            ->whereNotNull('received_date')
            ->whereNotNull('expected_return_date');

        $total = (clone $completed)->count();
        if ($total === 0) {
            return null;
        }

        $late = (clone $completed)->whereColumn('received_date', '>', 'expected_return_date')->count();

        return round($late / $total * 100, 1);
    }

    /** % of cases that are remakes */
    public function remakeRate(): ?float
    {
        $total = $this->cases()->count();
        if ($total === 0) {
            return null;
        }

        return round($this->cases()->where('is_remake', true)->count() / $total * 100, 1);
    }

    /** Date of most recent case sent */
    public function lastOrderDate(): ?string
    {
        return $this->cases()->max('sent_date');
    }

    // ── Vendor Intelligence metrics ──────────────────────────────────────

    /** Average turnaround in days (order_placed_date → final_received_date) */
    public function avgTurnaround(): ?float
    {
        $rows = $this->cases()
            ->whereNotNull('order_placed_date')
            ->whereNotNull('final_received_date')
            ->selectRaw('AVG(DATEDIFF(final_received_date, order_placed_date)) as avg_days')
            ->value('avg_days');

        return $rows !== null ? round((float) $rows, 1) : null;
    }

    /**
     * Average quality score from LabCaseRating records (1–5).
     * Null if no ratings yet.
     */
    public function avgQualityScore(): ?float
    {
        $avg = \App\Models\LabCaseRating::whereHas('labCase', fn($q) => $q->where('lab_vendor_id', $this->id))
            ->avg('overall');

        return $avg !== null ? round((float) $avg, 1) : null;
    }

    /**
     * Total active (open) cases currently at this lab.
     */
    public function activeCaseCount(): int
    {
        return $this->cases()->whereIn('status', \App\Models\LabCase::OPEN_STATUSES)->count();
    }

    /**
     * Vendor score 0–100 combining quality (40%), delay rate (30%), remake rate (30%).
     * Higher is better.
     */
    public function vendorScore(): ?int
    {
        $quality = $this->avgQualityScore();
        $delay   = $this->delayRate();
        $remake  = $this->remakeRate();

        // Need at least quality or delay data
        if ($quality === null && $delay === null) {
            return null;
        }

        $qualityScore = $quality !== null ? (($quality / 5) * 40) : 20; // default mid
        $delayScore   = $delay   !== null ? ((1 - min($delay, 100) / 100) * 30) : 15;
        $remakeScore  = $remake  !== null ? ((1 - min($remake, 100) / 100) * 30) : 15;

        return (int) round($qualityScore + $delayScore + $remakeScore);
    }

    /**
     * Returns a recommendation label based on vendor score.
     * Used in the create-case drawer.
     */
    public function recommendationBadge(): ?string
    {
        $score = $this->vendorScore();
        if ($score === null) {
            return null;
        }
        if ($score >= 85) return 'Top Rated';
        if ($score >= 70) return 'Reliable';
        if ($score >= 50) return 'Average';
        return null; // don't show badge for low-performing labs
    }

    /**
     * Recommendation badge Tailwind color classes.
     */
    public function recommendationBadgeColor(): string
    {
        $score = $this->vendorScore() ?? 0;
        if ($score >= 85) return 'bg-green-100 text-green-700';
        if ($score >= 70) return 'bg-blue-100 text-blue-700';
        return 'bg-gray-100 text-gray-600';
    }
}
