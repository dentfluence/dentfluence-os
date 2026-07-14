<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    protected $fillable = [
        'patient_id',
        'balance_promotional',
        'balance_permanent',
        'balance_total',
    ];

    protected $casts = [
        'balance_promotional' => 'decimal:2',
        'balance_permanent'   => 'decimal:2',
        'balance_total'       => 'decimal:2',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class)->latest();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Get or create wallet for a patient. */
    public static function forPatient(int $patientId): self
    {
        return self::firstOrCreate(['patient_id' => $patientId]);
    }

    /**
     * Get or create the wallet, then re-fetch it under a pessimistic row lock.
     * MUST be called inside a DB transaction. Used by every debit path so two
     * concurrent debits serialize instead of both reading the same balance.
     */
    public static function forPatientLocked(int $patientId): self
    {
        $wallet = self::firstOrCreate(['patient_id' => $patientId]);

        return self::whereKey($wallet->id)->lockForUpdate()->first();
    }

    /**
     * Recalculate and persist running totals from transaction ledger.
     * Call after any credit/debit.
     */
    public function recalculate(): void
    {
        $promo = $this->transactions()
            ->where('credit_type', 'promotional')
            ->selectRaw('SUM(CASE WHEN direction="credit" THEN amount ELSE -amount END) as bal')
            ->value('bal') ?? 0;

        $perm = $this->transactions()
            ->where('credit_type', 'permanent')
            ->selectRaw('SUM(CASE WHEN direction="credit" THEN amount ELSE -amount END) as bal')
            ->value('bal') ?? 0;

        // A negative ledger sum means more was debited than credited (e.g. a
        // historical concurrency bug). Never hide it silently — the balance is
        // still floored at 0 for display, but the discrepancy is logged so it
        // shows up in reconciliation instead of being erased.
        if ($promo < -0.009 || $perm < -0.009) {
            \Illuminate\Support\Facades\Log::warning('Wallet ledger negative — possible double-spend', [
                'wallet_id'  => $this->id,
                'patient_id' => $this->patient_id,
                'promo_sum'  => (float) $promo,
                'perm_sum'   => (float) $perm,
            ]);
        }

        $this->update([
            'balance_promotional' => max(0, $promo),
            'balance_permanent'   => max(0, $perm),
            'balance_total'       => max(0, $promo + $perm),
        ]);
    }

    /**
     * Get expiring promotional credits ordered by earliest expiry (FIFO).
     * Used by WalletService to consume credits in the right order.
     */
    public function expiringCredits()
    {
        return $this->transactions()
            ->where('direction', 'credit')
            ->where('credit_type', 'promotional')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '>=', today())
            ->orderBy('expiry_date');
    }

    public function hasBalance(): bool
    {
        return $this->balance_total > 0;
    }
}
