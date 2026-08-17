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
        'balance_patient_credit',
        'balance_total',
    ];

    protected $casts = [
        'balance_promotional'    => 'decimal:2',
        'balance_permanent'      => 'decimal:2',
        'balance_patient_credit' => 'decimal:2',
        'balance_total'          => 'decimal:2',
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
        $wallet = self::firstOrCreate(['patient_id' => $patientId]);

        // Promotional credit expires with the CALENDAR, not with a transaction.
        // balance_promotional is a cached column refreshed by recalculate(), so
        // a credit that lapsed overnight leaves the cache overstated until
        // something happens to touch this wallet — which may be never. Re-sync
        // only when the cache has actually drifted, so a normal read performs no
        // write and only a genuinely lapsed wallet costs one.
        if (abs((float) $wallet->balance_promotional - $wallet->availablePromotionalCredit()) > 0.009) {
            $wallet->recalculate();
            $wallet->refresh();
        }

        return $wallet;
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
        // Gross promotional movement, kept ONLY for the double-spend warning
        // below. It is deliberately NOT the balance: it counts credits that have
        // since expired, which is exactly the bug this replaced.
        $promo = $this->transactions()
            ->where('credit_type', 'promotional')
            ->selectRaw('SUM(CASE WHEN direction="credit" THEN amount ELSE -amount END) as bal')
            ->value('bal') ?? 0;

        // What the patient can actually spend today: unexpired, unconsumed.
        $promoAvailable = $this->availablePromotionalCredit();

        $perm = $this->transactions()
            ->where('credit_type', 'permanent')
            ->selectRaw('SUM(CASE WHEN direction="credit" THEN amount ELSE -amount END) as bal')
            ->value('bal') ?? 0;

        // U8 — Patient Credit: the cash-backed, refundable, never-expiring
        // balance the clinic owes the patient. Keyed on `funding`, NOT on
        // `credit_type`: `source='admin_credit'` is written both by a genuine
        // reversal (patient money) and by a clinic gift, so only an explicit
        // funding flag can answer "did cash enter the clinic for this?".
        $patientCredit = $this->transactions()
            ->where('funding', 'patient')
            ->selectRaw('SUM(CASE WHEN direction="credit" THEN amount ELSE -amount END) as bal')
            ->value('bal') ?? 0;

        // A negative ledger sum means more was debited than credited (e.g. a
        // historical concurrency bug). Never hide it silently — the balance is
        // still floored at 0 for display, but the discrepancy is logged so it
        // shows up in reconciliation instead of being erased.
        if ($promo < -0.009 || $perm < -0.009 || $patientCredit < -0.009) {
            \Illuminate\Support\Facades\Log::warning('Wallet ledger negative — possible double-spend', [
                'wallet_id'      => $this->id,
                'patient_id'     => $this->patient_id,
                'promo_sum'      => (float) $promo,
                'perm_sum'       => (float) $perm,
                'patient_credit' => (float) $patientCredit,
            ]);
        }

        $this->update([
            'balance_promotional'    => max(0, $promoAvailable),
            'balance_permanent'      => max(0, $perm),
            'balance_patient_credit' => max(0, $patientCredit),
            'balance_total'          => max(0, $promoAvailable + $perm),
        ]);
    }

    /**
     * SPENDABLE promotional credit right now: unexpired and not yet consumed.
     *
     * Why this is not a one-line WHERE clause. Promotional credit is issued in
     * lots, each with its own expiry. Debit rows do NOT record which lot they
     * drew from, so neither shortcut works:
     *
     *   SUM(unexpired credits) - SUM(all debits)
     *       charges spending that came out of a since-lapsed lot against the
     *       live ones, and under-reports.
     *
     *   naive FIFO over all lots
     *       hands old spending to a lot that was ALREADY expired when the money
     *       was spent — which it cannot have come from — and over-reports.
     *
     * So replay the ledger exactly the way WalletService::consumePromotional-
     * Credits spent it: walk the debits oldest-first, and allocate each one FIFO
     * by expiry across the lots that were live ON THAT DEBIT'S DATE (which is
     * precisely what expiringCredits() returned at the time). Whatever survives
     * the replay and has not lapsed as of today is the available balance.
     *
     * A lot with no expiry_date never expires. Promotional credit issued through
     * the UI always carries one; this is here so an undated legacy row is not
     * silently destroyed.
     *
     * Expiry boundary matches expiringCredits() exactly — a credit expiring
     * TODAY is still spendable; expired means expiry_date < the date in question.
     */
    public function availablePromotionalCredit(): float
    {
        $lots = $this->transactions()
            ->where('direction', 'credit')
            ->where('credit_type', 'promotional')
            ->reorder()
            ->orderByRaw('expiry_date IS NULL, expiry_date ASC, id ASC')
            ->get(['id', 'amount', 'expiry_date', 'created_at']);

        if ($lots->isEmpty()) {
            return 0.0;
        }

        $debits = $this->transactions()
            ->where('direction', 'debit')
            ->where('credit_type', 'promotional')
            ->reorder()
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'amount', 'created_at']);

        // Mutable remaining balance per lot, keyed by lot id.
        $remaining = [];
        foreach ($lots as $lot) {
            $remaining[$lot->id] = (float) $lot->amount;
        }

        foreach ($debits as $debit) {
            $needed  = (float) $debit->amount;
            $spentOn = $debit->created_at?->copy()->startOfDay() ?? today();

            foreach ($lots as $lot) {
                if ($needed <= 0.009) {
                    break;
                }
                if ($remaining[$lot->id] <= 0.009) {
                    continue;
                }
                // A lot cannot fund a debit that predates it...
                if ($lot->created_at !== null && $lot->created_at->gt($debit->created_at ?? now())) {
                    continue;
                }
                // ...nor one made after the lot had already lapsed.
                if ($lot->expiry_date !== null && $lot->expiry_date->lt($spentOn)) {
                    continue;
                }

                $take                 = min($remaining[$lot->id], $needed);
                $remaining[$lot->id] -= $take;
                $needed              -= $take;
            }
            // Any unmatched remainder is a historical inconsistency, not a
            // balance: ignore it rather than inventing a lot to charge it to.
        }

        $today     = today();
        $available = 0.0;

        foreach ($lots as $lot) {
            if ($remaining[$lot->id] <= 0.009) {
                continue;                                   // fully consumed
            }
            if ($lot->expiry_date !== null && $lot->expiry_date->lt($today)) {
                continue;                                   // lapsed — history keeps it, the balance does not
            }
            $available += $remaining[$lot->id];
        }

        return round($available, 2);
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

    /**
     * U8 rule 11 — Patient Credit NEVER expires. It is the patient's own money
     * and remains theirs until spent or refunded. No expiry_date filter here,
     * deliberately: expiry is a property of a clinic-funded concession, never
     * of a liability.
     */
    public function patientCreditEntries()
    {
        return $this->transactions()
            ->where('direction', 'credit')
            ->where('funding', 'patient')
            ->reorder()
            ->orderBy('created_at');
    }

    public function hasBalance(): bool
    {
        return $this->balance_total > 0;
    }

    /** U8 — spendable, refundable patient money. */
    public function hasPatientCredit(): bool
    {
        return $this->balance_patient_credit > 0;
    }
}
