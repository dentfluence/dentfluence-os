<?php

namespace App\Models;

use App\Models\Finance\FinanceExpense;
use App\Models\Finance\FinanceVendor;
use App\Models\Finance\FinanceVoucher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * LabMonthlyReconciliation — one reconciliation per lab vendor per billing period.
 *
 * Status flow:
 *   draft → pending_review → approved → paid
 *                          ↘ disputed (can go back to pending_review)
 *
 * On approval: auto-creates FinanceExpense (Accounts Payable / unpaid vendor bill).
 * On payment in Finance: updates status to 'paid' and lab_cases.billing_status to 'paid'.
 */
class LabMonthlyReconciliation extends Model
{
    use SoftDeletes;

    protected $table = 'lab_monthly_reconciliations';

    public const STATUSES = [
        'draft', 'pending_review', 'approved', 'paid', 'disputed',
    ];

    public const STATUS_LABELS = [
        'draft'          => 'Draft',
        'pending_review' => 'Pending Review',
        'approved'       => 'Approved',
        'paid'           => 'Paid',
        'disputed'       => 'Disputed',
    ];

    public const STATUS_COLORS = [
        'draft'          => 'bg-gray-100 text-gray-600',
        'pending_review' => 'bg-yellow-100 text-yellow-700',
        'approved'       => 'bg-blue-100 text-blue-700',
        'paid'           => 'bg-green-100 text-green-700',
        'disputed'       => 'bg-red-100 text-red-700',
    ];

    protected $fillable = [
        'reconciliation_ref',
        'lab_vendor_id',
        'finance_vendor_id',
        'billing_month',
        'billing_year',
        'our_total',
        'vendor_total',
        'difference',
        'agreed_amount',
        'vendor_bill_number',
        'vendor_bill_date',
        'status',
        'notes',
        'dispute_reason',
        'finance_expense_id',
        'voucher_id',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'vendor_bill_date' => 'date',
        'approved_at'      => 'datetime',
        'our_total'        => 'decimal:2',
        'vendor_total'     => 'decimal:2',
        'difference'       => 'decimal:2',
        'agreed_amount'    => 'decimal:2',
        'billing_month'    => 'integer',
        'billing_year'     => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────────

    public function labVendor(): BelongsTo
    {
        return $this->belongsTo(LabVendor::class, 'lab_vendor_id');
    }

    public function financeVendor(): BelongsTo
    {
        return $this->belongsTo(FinanceVendor::class, 'finance_vendor_id');
    }

    public function financeExpense(): BelongsTo
    {
        return $this->belongsTo(FinanceExpense::class, 'finance_expense_id');
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(FinanceVoucher::class, 'voucher_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LabReconciliationItem::class, 'reconciliation_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(LabReconciliationEvent::class, 'reconciliation_id')->orderBy('id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeForVendor(Builder $q, int $vendorId): Builder
    {
        return $q->where('lab_vendor_id', $vendorId);
    }

    public function scopeForPeriod(Builder $q, int $year, int $month): Builder
    {
        return $q->where('billing_year', $year)->where('billing_month', $month);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /** Auto-generate: REC-YYYY-NNNN */
    public static function generateRef(): string
    {
        $year = now()->year;
        $last = static::whereYear('created_at', $year)->orderByDesc('id')->value('reconciliation_ref');
        $seq  = 1;
        if ($last && preg_match('/REC-\d{4}-(\d+)/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }
        return 'REC-' . $year . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    public function getBillingPeriodLabel(): string
    {
        return \Carbon\Carbon::createFromDate($this->billing_year, $this->billing_month, 1)
            ->format('F Y');
    }

    public function getStatusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }

    public function getStatusColor(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'bg-gray-100 text-gray-600';
    }

    public function hasDifference(): bool
    {
        return abs((float) $this->difference) > 0.01;
    }

    public function hasConflicts(): bool
    {
        return $this->items()->whereIn('match_status', ['conflict', 'disputed'])->exists();
    }

    /** Log a status change event */
    public function logEvent(string $type, ?string $from, ?string $to, ?string $notes = null): void
    {
        $this->events()->create([
            'event_type'  => $type,
            'from_status' => $from,
            'to_status'   => $to,
            'notes'       => $notes,
            'created_by'  => auth()->id(),
        ]);
    }
}
