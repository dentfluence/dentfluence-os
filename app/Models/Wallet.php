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
