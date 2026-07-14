<?php

namespace App\Services;

use App\Models\BillingAuditLog;
use App\Models\Finance\FinanceTransaction;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class WalletService
{
    // ── Credit ───────────────────────────────────────────────────────────────

    /**
     * Add credit to a patient's wallet.
     *
     * @param  int          $patientId
     * @param  float        $amount
     * @param  string       $creditType           'promotional' | 'permanent'
     * @param  string|null  $expiryDate           YYYY-MM-DD; required for promotional; optional for permanent
     * @param  string|null  $notes
     * @param  int|null     $createdBy
     * @param  string|null  $campaignName         Label for campaign (e.g. "Diwali Offer")
     * @param  array|null   $applicableTreatments  Treatment IDs this promo applies to; null = all
     */
    public function credit(
        int     $patientId,
        float   $amount,
        string  $creditType = 'permanent',
        ?string $expiryDate = null,
        ?string $notes = null,
        ?int    $createdBy = null,
        ?string $campaignName = null,
        ?array  $applicableTreatments = null
    ): WalletTransaction {
        return DB::transaction(function () use (
            $patientId, $amount, $creditType, $expiryDate, $notes,
            $createdBy, $campaignName, $applicableTreatments
        ) {
            $wallet = Wallet::forPatient($patientId);

            // source: 'campaign' when a campaign name is given for promo, else 'admin_credit'
            $source = ($creditType === 'promotional' && $campaignName)
                ? 'campaign'
                : 'admin_credit';

            $tx = WalletTransaction::create([
                'wallet_id'             => $wallet->id,
                'patient_id'            => $patientId,
                'direction'             => 'credit',
                'credit_type'           => $creditType,
                'source'                => $source,
                'campaign_name'         => $campaignName,
                'applicable_treatments' => $applicableTreatments, // null = unrestricted
                'amount'                => $amount,
                'expiry_date'           => $expiryDate,            // allowed for both types
                'notes'                 => $notes,
                'created_by'            => $createdBy,
            ]);

            $wallet->recalculate();

            return $tx;
        });
    }

    // ── Debit ────────────────────────────────────────────────────────────────

    /**
     * Debit wallet for an invoice payment.
     * Consumes promotional credits first (FIFO by expiry, respecting treatment restrictions),
     * then permanent credits.
     *
     * @param  int    $patientId
     * @param  float  $amount        How much to debit
     * @param  int    $invoiceId
     * @param  int|null $createdBy
     * @param  array  $treatmentIds  Treatment IDs on this invoice (for promo restriction check)
     * @return float  Actual amount debited (capped by available eligible balance)
     */
    public function debit(
        int   $patientId,
        float $amount,
        int   $invoiceId,
        ?int  $createdBy = null,
        array $treatmentIds = []
    ): float {
        return DB::transaction(function () use ($patientId, $amount, $invoiceId, $createdBy, $treatmentIds) {
            // Row-lock the wallet so concurrent debits serialize (a double-click
            // or two counters can otherwise both read the same balance and
            // double-spend the credit). Then refresh cached totals from the
            // ledger so the checks below run on trustworthy numbers.
            $wallet = Wallet::forPatientLocked($patientId);
            $wallet->recalculate();
            $wallet->refresh();

            $invoiceNumber = Invoice::find($invoiceId)?->invoice_number;

            if ($wallet->balance_total <= 0 || $amount <= 0) {
                return 0.0;
            }

            $remaining = (float) $amount;

            // ── Step 1: Consume promotional credits FIFO by expiry ───────────
            // Only use promo credits that are applicable for the invoice's treatments.
            if ($remaining > 0 && $wallet->balance_promotional > 0) {
                $promoConsumed = $this->consumePromotionalCredits(
                    $wallet, $remaining, $invoiceId, $invoiceNumber, $createdBy, $treatmentIds
                );
                $remaining -= $promoConsumed;
            }

            // ── Step 2: Consume permanent credits ────────────────────────────
            if ($remaining > 0 && $wallet->balance_permanent > 0) {
                $permConsumed = $this->consumePermanentCredits(
                    $wallet, $remaining, $invoiceId, $invoiceNumber, $createdBy
                );
                $remaining -= $permConsumed;
            }

            $wallet->recalculate();

            $debited = (float) $amount - $remaining;
            return round(max(0, $debited), 2);
        });
    }

    // ── Refund / Reverse ─────────────────────────────────────────────────────

    /**
     * Credit wallet as a refund (permanent, no expiry).
     */
    public function refund(
        int    $patientId,
        float  $amount,
        int    $invoiceId,
        ?string $notes = null,
        ?int   $createdBy = null
    ): WalletTransaction {
        return DB::transaction(function () use ($patientId, $amount, $invoiceId, $notes, $createdBy) {
            $wallet        = Wallet::forPatient($patientId);
            $invoiceNumber = Invoice::find($invoiceId)?->invoice_number;

            $tx = WalletTransaction::create([
                'wallet_id'      => $wallet->id,
                'patient_id'     => $patientId,
                'direction'      => 'credit',
                'credit_type'    => 'permanent',
                'source'         => 'refund',
                'amount'         => $amount,
                'invoice_id'     => $invoiceId,
                'invoice_number' => $invoiceNumber,
                'notes'          => $notes ?? 'Refund',
                'created_by'     => $createdBy,
            ]);

            $wallet->recalculate();

            return $tx;
        });
    }

    /**
     * Reverse every wallet debit that was applied against an invoice.
     * MUST be called whenever an invoice is cancelled or deleted — otherwise
     * the debit stays on the ledger with no invoice to show what it paid for,
     * and the patient's usable balance is short by that amount permanently.
     *
     * Extracted 2026-07-14 from web BillingController::reverseInvoiceWalletDebit
     * so the API cancel path stops silently losing patient wallet credit.
     */
    public function reverseInvoiceDebit(Invoice $invoice, string $reason, ?int $createdBy = null): void
    {
        $debited = WalletTransaction::where('invoice_id', $invoice->id)
            ->where('source', 'invoice_debit')
            ->where('direction', 'debit')
            ->sum('amount');

        if ($debited > 0) {
            $this->credit(
                patientId:  $invoice->patient_id,
                amount:     (float) $debited,
                creditType: 'permanent',
                notes:      'Reversal — ' . $reason,
                createdBy:  $createdBy,
            );
        }
    }

    // ── Advance / Recharge (money INTO wallet, no invoice) ───────────────────

    /**
     * Deposit money into a patient's wallet as permanent credit.
     * Used for advance payments and wallet recharges — the patient pays now,
     * the balance is available for future invoices. Records how the cash moved.
     *
     * @param  string  $source  'advance' | 'admin_credit' (recharge) etc.
     */
    public function deposit(
        int     $patientId,
        float   $amount,
        string  $paymentMode,
        ?string $notes = null,
        ?int    $createdBy = null,
        string  $source = 'advance'
    ): WalletTransaction {
        return DB::transaction(function () use ($patientId, $amount, $paymentMode, $notes, $createdBy, $source) {
            $wallet = Wallet::forPatient($patientId);

            $tx = WalletTransaction::create([
                'wallet_id'    => $wallet->id,
                'patient_id'   => $patientId,
                'direction'    => 'credit',
                'credit_type'  => 'permanent',
                'source'       => $source,
                'amount'       => $amount,
                'payment_mode' => $paymentMode,
                'notes'        => $notes,
                'created_by'   => $createdBy,
            ]);

            $wallet->recalculate();

            return $tx;
        });
    }

    /**
     * Receive an advance payment into a patient's wallet WITH the full finance
     * chain: wallet deposit + FinanceTransaction income mirror + billing audit.
     *
     * Extracted 2026-07-14 from Finance\WalletController::receiveAdvance so the
     * mobile advance path stops bypassing the cashbook and audit log. Every
     * advance — web or mobile — must land in the finance ledger.
     */
    public function receiveAdvance(
        Patient $patient,
        float   $amount,
        string  $paymentMode,
        string  $paymentDate,
        ?string $notes = null,
        ?int    $createdBy = null
    ): WalletTransaction {
        return DB::transaction(function () use ($patient, $amount, $paymentMode, $paymentDate, $notes, $createdBy) {
            $tx = $this->deposit(
                patientId:   $patient->id,
                amount:      $amount,
                paymentMode: $paymentMode,
                notes:       $notes ?? 'Advance payment',
                createdBy:   $createdBy,
                source:      'advance',
            );

            // Finance mirror — real cash received (separable in reports via source_type).
            FinanceTransaction::create([
                'type'              => 'income',
                'direction'         => 'credit',
                'source_type'       => WalletTransaction::class,
                'source_id'         => $tx->id,
                'amount'            => $amount,
                'net_amount'        => $amount,
                'payment_mode'      => $paymentMode,
                'patient_id'        => $patient->id,
                'status'            => 'active',
                'transaction_date'  => $paymentDate,
                'notes'             => 'Advance deposit to wallet' . ($notes ? ' — ' . $notes : ''),
                'created_by'        => $createdBy,
            ]);

            BillingAuditLog::record('wallet_advance', $tx,
                'Advance Rs. ' . number_format($amount, 2) . ' (' . $paymentMode . ')',
                $createdBy, 'Wallet · ' . $patient->name);

            return $tx;
        });
    }

    // ── Withdraw / Refund-out (money OUT of wallet, back to patient) ──────────

    /**
     * Debit permanent wallet balance and hand the money back to the patient.
     * Capped at the available permanent balance. Returns the amount withdrawn.
     */
    public function withdraw(
        int     $patientId,
        float   $amount,
        string  $paymentMode,
        ?string $notes = null,
        ?int    $createdBy = null
    ): float {
        return DB::transaction(function () use ($patientId, $amount, $paymentMode, $notes, $createdBy) {
            // Row lock + fresh totals: withdrawal is a debit path too.
            $wallet = Wallet::forPatientLocked($patientId);
            $wallet->recalculate();
            $wallet->refresh();

            $take = round(min((float) $wallet->balance_permanent, max(0, $amount)), 2);
            if ($take <= 0) {
                return 0.0;
            }

            WalletTransaction::create([
                'wallet_id'    => $wallet->id,
                'patient_id'   => $patientId,
                'direction'    => 'debit',
                'credit_type'  => 'permanent',
                'source'       => 'withdrawal',
                'amount'       => $take,
                'payment_mode' => $paymentMode,
                'notes'        => $notes ?? 'Wallet refund to patient',
                'created_by'   => $createdBy,
            ]);

            $wallet->recalculate();

            return $take;
        });
    }

    // ── Adjustment (manual correction, credit or debit) ──────────────────────

    /**
     * Apply a manual permanent-balance correction. A debit is capped at balance.
     * $direction = 'credit' | 'debit'.
     */
    public function adjust(
        int     $patientId,
        float   $amount,
        string  $direction,
        string  $reason,
        ?int    $createdBy = null
    ): ?WalletTransaction {
        return DB::transaction(function () use ($patientId, $amount, $direction, $reason, $createdBy) {
            // Row lock + fresh totals: adjustment can be a debit path.
            $wallet = Wallet::forPatientLocked($patientId);
            $wallet->recalculate();
            $wallet->refresh();

            $amt = round(max(0, $amount), 2);
            if ($direction === 'debit') {
                $amt = round(min($amt, (float) $wallet->balance_permanent), 2);
            }
            if ($amt <= 0) {
                return null;
            }

            $tx = WalletTransaction::create([
                'wallet_id'    => $wallet->id,
                'patient_id'   => $patientId,
                'direction'    => $direction === 'debit' ? 'debit' : 'credit',
                'credit_type'  => 'permanent',
                'source'       => 'adjustment',
                'amount'       => $amt,
                'notes'        => $reason,
                'created_by'   => $createdBy,
            ]);

            $wallet->recalculate();

            return $tx;
        });
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    /**
     * Consume promotional credits FIFO by expiry_date.
     * Skips (hard-blocks) any credit whose applicable_treatments doesn't match $treatmentIds.
     */
    private function consumePromotionalCredits(
        Wallet  $wallet,
        float   $needed,
        int     $invoiceId,
        ?string $invoiceNumber,
        ?int    $createdBy,
        array   $treatmentIds
    ): float {
        // FIFO by earliest expiry; only non-expired credits
        $credits = $wallet->expiringCredits()->get();

        $consumed = 0.0;

        foreach ($credits as $credit) {
            if ($needed <= 0) break;

            // Hard-block: skip credits that aren't applicable for this invoice's treatments
            if (! $credit->isApplicableFor($treatmentIds)) {
                continue;
            }

            $take = min((float) $credit->amount, $needed);
            if ($take <= 0) continue;

            WalletTransaction::create([
                'wallet_id'      => $wallet->id,
                'patient_id'     => $wallet->patient_id,
                'direction'      => 'debit',
                'credit_type'    => 'promotional',
                'source'         => 'invoice_debit',
                'amount'         => $take,
                'invoice_id'     => $invoiceId,
                'invoice_number' => $invoiceNumber,
                'created_by'     => $createdBy,
            ]);

            $consumed += $take;
            $needed   -= $take;
        }

        return round($consumed, 2);
    }

    /**
     * Consume permanent credits in a single debit transaction.
     */
    private function consumePermanentCredits(
        Wallet  $wallet,
        float   $needed,
        int     $invoiceId,
        ?string $invoiceNumber,
        ?int    $createdBy
    ): float {
        $take = min((float) $wallet->balance_permanent, $needed);
        if ($take <= 0) return 0.0;

        WalletTransaction::create([
            'wallet_id'      => $wallet->id,
            'patient_id'     => $wallet->patient_id,
            'direction'      => 'debit',
            'credit_type'    => 'permanent',
            'source'         => 'invoice_debit',
            'amount'         => $take,
            'invoice_id'     => $invoiceId,
            'invoice_number' => $invoiceNumber,
            'created_by'     => $createdBy,
        ]);

        return round($take, 2);
    }

    // ── Balance summary ──────────────────────────────────────────────────────

    /**
     * Return wallet balance summary for a patient.
     * Creates wallet record if it doesn't exist.
     */
    public function summary(int $patientId): array
    {
        $wallet = Wallet::forPatient($patientId);
        $wallet->recalculate(); // ensure totals are fresh

        $expiringSoon = $wallet->transactions()
            ->where('direction', 'credit')
            ->where('credit_type', 'promotional')
            ->whereBetween('expiry_date', [today(), today()->addDays(30)])
            ->sum('amount');

        return [
            'balance_total'       => (float) $wallet->balance_total,
            'balance_promotional' => (float) $wallet->balance_promotional,
            'balance_permanent'   => (float) $wallet->balance_permanent,
            'expiring_soon'       => (float) $expiringSoon,
        ];
    }

    /**
     * Get eligible promotional balance for specific treatment IDs.
     * Used in billing to show how much promo can actually be applied.
     */
    public function eligiblePromoBalance(int $patientId, array $treatmentIds = []): float
    {
        $wallet = Wallet::forPatient($patientId);

        $credits = $wallet->expiringCredits()->get();

        return (float) $credits
            ->filter(fn($c) => $c->isApplicableFor($treatmentIds))
            ->sum('amount');
    }
}
