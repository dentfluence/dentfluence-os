<?php

namespace App\Services;

use App\Models\BillingAuditLog;
use App\Models\Finance\FinanceTransaction;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Patient;
use App\Models\Receipt;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * WalletService
 * =============
 *
 * U8 — ADVANCE / PATIENT CREDIT (frozen business model, 2026-08-15).
 *
 * Two economically opposite things live in this ledger and they are now kept
 * apart by the `funding` column, not by `credit_type`:
 *
 *   funding = 'patient'  Cash the patient handed over (advance, overpayment
 *                        sweep, refund-to-credit). A LIABILITY of the clinic.
 *                        Spendable as a PAYMENT TENDER. Cash-refundable.
 *                        NEVER expires (rule 11).
 *
 *   funding = 'clinic'   Money the clinic issued (promotional campaign,
 *                        referral reward, admin gift). NOT a liability.
 *                        NOT cash-refundable (rule 12). May expire.
 *
 * The frozen rules this class implements:
 *   2  an advance is not revenue
 *   3  an advance creates cash inflow + liability + patient credit + a receipt
 *   4/5 an advance creates NO invoice and NO invoice-payment
 *   8  patient credit settles an invoice as a TENDER, like cash
 *   9  a wallet payment NEVER reduces the invoice total
 *  11  patient credit is refundable and never expires
 *  14  revenue is recognised on delivery, not on receipt of the advance
 *
 * SCOPE NOTE: promotional / clinic-funded credit still flows through debit()
 * into invoices.wallet_applied (a discount). That is deliberate and unchanged —
 * re-homing concessions is U2/U7, not U8. Invoice::recalculate() is untouched.
 */
class WalletService
{
    public const FUNDING_PATIENT = 'patient';
    public const FUNDING_CLINIC  = 'clinic';

    // ── Credit ───────────────────────────────────────────────────────────────

    /**
     * Add credit to a patient's wallet.
     *
     * U8: $funding defaults to 'clinic'. Every caller of this method is a
     * clinic-funded issuance (promotional campaign, referral reward, admin
     * gift, invoice-debit reversal of promotional credit). Cash-backed money
     * arrives through deposit()/receiveAdvance() instead, which default to
     * 'patient'. Defaulting to 'clinic' here is the safe direction: a
     * mis-tagged credit becomes non-refundable rather than silently
     * cash-withdrawable.
     *
     * @param  string  $funding  self::FUNDING_CLINIC | self::FUNDING_PATIENT
     */
    public function credit(
        int     $patientId,
        float   $amount,
        string  $creditType = 'permanent',
        ?string $expiryDate = null,
        ?string $notes = null,
        ?int    $createdBy = null,
        ?string $campaignName = null,
        ?array  $applicableTreatments = null,
        string  $funding = self::FUNDING_CLINIC
    ): WalletTransaction {
        return DB::transaction(function () use (
            $patientId, $amount, $creditType, $expiryDate, $notes,
            $createdBy, $campaignName, $applicableTreatments, $funding
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
                'funding'               => $funding,
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

    // ── Debit (CONCESSION path — promotional only) ───────────────────────────

    /**
     * Debit CLINIC-FUNDED (promotional) credit against an invoice.
     *
     * U8 rule 9: patient credit must never reduce an invoice total, so it no
     * longer flows through here. This path now consumes promotional credit
     * only — FIFO by expiry, respecting treatment restrictions — and its
     * result continues to land in invoices.wallet_applied as a concession.
     *
     * Patient credit is settled by settleInvoiceFromCredit() instead.
     *
     * @return float  Actual amount debited (capped by eligible promo balance)
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

            if ($wallet->balance_promotional <= 0 || $amount <= 0) {
                return 0.0;
            }

            $consumed = $this->consumePromotionalCredits(
                $wallet, (float) $amount, $invoiceId, $invoiceNumber, $createdBy, $treatmentIds
            );

            $wallet->recalculate();

            return round(max(0, $consumed), 2);
        });
    }

    // ── U8 — Patient Credit as a PAYMENT TENDER ──────────────────────────────

    /**
     * Settle part or all of an invoice from the patient's own credit.
     *
     * This is the heart of U8. Patient credit behaves exactly like cash:
     *
     *   - it creates an InvoicePayment (payment_mode = 'wallet')  [rule 8]
     *   - it creates a Receipt                                    [rule 15]
     *   - it recognises revenue: FinanceTransaction type = income,
     *     payment_mode = 'wallet'                                 [rule 14]
     *   - it releases the liability that the advance created
     *   - it moves NO cash                                        [rule 15]
     *   - it does NOT touch invoices.wallet_applied, so the invoice
     *     total is unchanged                                      [rule 9]
     *
     * Capped at min(requested, patient credit, invoice balance due).
     *
     * @return array{debited: float, payment: ?InvoicePayment, receipt: ?Receipt}
     */
    public function settleInvoiceFromCredit(
        Invoice $invoice,
        float   $amount,
        ?int    $createdBy = null,
        ?string $paymentDate = null,
        ?string $notes = null
    ): array {
        return DB::transaction(function () use ($invoice, $amount, $createdBy, $paymentDate, $notes) {
            // Same lock order as recordPayment: invoice row, then wallet row.
            Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $invoice->refresh();

            $wallet = Wallet::forPatientLocked($invoice->patient_id);
            $wallet->recalculate();
            $wallet->refresh();

            $cap = round(min(
                (float) $amount,
                (float) $wallet->balance_patient_credit,
                (float) $invoice->balance_due
            ), 2);

            if ($cap <= 0) {
                return ['debited' => 0.0, 'payment' => null, 'receipt' => null];
            }

            $paidBefore  = (float) $invoice->paid_amount;
            $paymentDate = $paymentDate ?: now()->toDateString();

            // 1. Consume the patient's credit (oldest first).
            $debited = $this->consumePatientCredit($wallet, $cap, $invoice, $createdBy);
            if ($debited <= 0) {
                return ['debited' => 0.0, 'payment' => null, 'receipt' => null];
            }
            $wallet->recalculate();

            // 2. A real payment row — this is what makes it a tender, not a discount.
            $payment = InvoicePayment::create([
                'invoice_id'   => $invoice->id,
                'patient_id'   => $invoice->patient_id,
                'amount'       => $debited,
                'payment_mode' => 'wallet',
                'payment_date' => $paymentDate,
                'notes'        => $notes ?? 'Settled from patient credit',
                'created_by'   => $createdBy,
            ]);

            // 3. Invoice totals. recalculate() sums payments — the invoice TOTAL
            //    is untouched; only paid_amount / balance_due / status move.
            $invoice->recalculate();
            $invoice->refresh();

            // 4. Receipt for the settlement.
            $receipt = Receipt::create([
                'receipt_number'     => Receipt::nextNumber(),
                'receipt_kind'       => 'payment',
                'invoice_id'         => $invoice->id,
                'invoice_payment_id' => $payment->id,
                'patient_id'         => $invoice->patient_id,
                'amount'             => $debited,
                'payment_mode'       => 'wallet',
                'receipt_date'       => $paymentDate,
                'invoice_total'      => $invoice->total_amount,
                'amount_paid_before' => $paidBefore,
                'balance_after'      => (float) $invoice->balance_due,
                'notes'              => $notes ?? 'Paid from patient credit',
                'created_by'         => $createdBy,
            ]);

            // 5. Revenue recognised. NO cash movement — payment_mode='wallet'
            //    keeps this out of every cash/till report while still counting
            //    as income, which is exactly rule 14 + rule 15.
            FinanceTransaction::create([
                'type'             => 'income',
                'direction'        => 'credit',
                'source_type'      => InvoicePayment::class,
                'source_id'        => $payment->id,
                'amount'           => $debited,
                'net_amount'       => $debited,
                'payment_mode'     => 'wallet',
                'patient_id'       => $invoice->patient_id,
                'status'           => 'active',
                'transaction_date' => $paymentDate,
                'notes'            => 'Revenue recognised — settled from patient credit'
                                      . ' (' . $invoice->invoice_number . ')',
                'created_by'       => $createdBy,
            ]);

            return ['debited' => $debited, 'payment' => $payment, 'receipt' => $receipt];
        });
    }

    /**
     * U8 — restore patient credit when a wallet-tender payment is reversed.
     * Used by the void/refund path so a wallet settlement round-trips exactly.
     */
    public function restorePatientCredit(
        int     $patientId,
        float   $amount,
        ?string $notes = null,
        ?int    $createdBy = null,
        ?int    $invoiceId = null
    ): ?WalletTransaction {
        if ($amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($patientId, $amount, $notes, $createdBy, $invoiceId) {
            $wallet = Wallet::forPatient($patientId);

            $tx = WalletTransaction::create([
                'wallet_id'      => $wallet->id,
                'patient_id'     => $patientId,
                'direction'      => 'credit',
                'credit_type'    => 'permanent',
                'funding'        => self::FUNDING_PATIENT,
                'source'         => 'refund',
                'amount'         => $amount,
                'invoice_id'     => $invoiceId,
                'invoice_number' => $invoiceId ? Invoice::find($invoiceId)?->invoice_number : null,
                'notes'          => $notes ?? 'Wallet payment reversed — credit restored',
                'created_by'     => $createdBy,
            ]);

            $wallet->recalculate();

            return $tx;
        });
    }

    // ── Refund / Reverse ─────────────────────────────────────────────────────

    /**
     * Credit wallet as a refund of money the patient actually paid.
     * U8: this is patient money coming back, so it is patient-funded.
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
                'funding'        => self::FUNDING_PATIENT,
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
     * U8: these debits are now promotional-only (see debit()), so the credit
     * is restored as clinic-funded — it must not become cash-refundable
     * (rule 12). credit() defaults to FUNDING_CLINIC, which is what we want.
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
                funding:    self::FUNDING_CLINIC,
            );
        }
    }

    // ── Advance / Recharge (money INTO wallet, no invoice) ───────────────────

    /**
     * Deposit money into a patient's wallet as permanent credit.
     * Used for advance payments, wallet recharges and overpayment sweeps —
     * in every case the patient's own cash, so it is patient-funded.
     */
    public function deposit(
        int     $patientId,
        float   $amount,
        string  $paymentMode,
        ?string $notes = null,
        ?int    $createdBy = null,
        string  $source = 'advance',
        string  $funding = self::FUNDING_PATIENT
    ): WalletTransaction {
        return DB::transaction(function () use ($patientId, $amount, $paymentMode, $notes, $createdBy, $source, $funding) {
            $wallet = Wallet::forPatient($patientId);

            $tx = WalletTransaction::create([
                'wallet_id'    => $wallet->id,
                'patient_id'   => $patientId,
                'direction'    => 'credit',
                'credit_type'  => 'permanent',
                'funding'      => $funding,
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
     * Receive an advance payment into a patient's wallet.
     *
     * U8 rules 2, 3, 4, 5, 14 — this is the decisive change in this slice:
     *
     *   - the finance entry is type = 'advance', NOT 'income'. The clinic has
     *     not earned this money; it owes it. Revenue is recognised later, when
     *     the treatment is delivered and the credit is spent.
     *   - an ADVANCE RECEIPT is issued. Real cash changed hands, so the patient
     *     leaves with a document. Previously none was created at all.
     *   - NO invoice and NO invoice-payment are created.
     */
    public function receiveAdvance(
        Patient $patient,
        float   $amount,
        string  $paymentMode,
        string  $paymentDate,
        ?string $notes = null,
        ?int    $createdBy = null,
        bool    $createReceipt = true
    ): WalletTransaction {
        return DB::transaction(function () use ($patient, $amount, $paymentMode, $paymentDate, $notes, $createdBy, $createReceipt) {
            $tx = $this->deposit(
                patientId:   $patient->id,
                amount:      $amount,
                paymentMode: $paymentMode,
                notes:       $notes ?? 'Advance payment',
                createdBy:   $createdBy,
                source:      'advance',
                funding:     self::FUNDING_PATIENT,
            );

            // U8 rule 3 — the patient's proof that they handed over cash.
            // No invoice, no invoice_payment: this document stands alone.
            //
            // $createReceipt = false is used ONLY by patient-level allocation,
            // where the surplus is one leg of a larger tender that already has a
            // single consolidated PAY- receipt covering the whole amount. The
            // wallet credit and the liability entry below are still written, so
            // the accounting is identical either way — only the paper differs.
            if ($createReceipt) {
            Receipt::create([
                'receipt_number'     => Receipt::nextAdvanceNumber(),
                'receipt_kind'       => 'advance',
                'invoice_id'         => null,
                'invoice_payment_id' => null,
                'patient_id'         => $patient->id,
                'amount'             => $amount,
                'payment_mode'       => $paymentMode,
                'receipt_date'       => $paymentDate,
                'invoice_total'      => 0,
                'amount_paid_before' => 0,
                'balance_after'      => 0,
                'notes'              => 'Advance received' . ($notes ? ' — ' . $notes : ''),
                'created_by'         => $createdBy,
            ]);
            }

            // U8 rule 2 — cash in, liability up. NOT revenue.
            FinanceTransaction::create([
                'type'              => 'advance',
                'direction'         => 'credit',
                'source_type'       => WalletTransaction::class,
                'source_id'         => $tx->id,
                'amount'            => $amount,
                'net_amount'        => $amount,
                'payment_mode'      => $paymentMode,
                'patient_id'        => $patient->id,
                'status'            => 'active',
                'transaction_date'  => $paymentDate,
                'notes'             => 'Advance received — patient liability, not revenue'
                                       . ($notes ? ' — ' . $notes : ''),
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
     * Hand patient credit back to the patient as cash.
     *
     * U8 rules 11 + 12 — capped at the PATIENT-FUNDED balance. Clinic-funded
     * credit (promotional, referral reward, admin gift) can never be withdrawn:
     * the clinic never received that money, so it cannot pay it out.
     */
    /**
     * A2 — a corrected tender becomes patient credit, NOT a refund.
     *
     * Used when a payment was really received but allocated to the wrong invoice.
     * The cash never left the clinic, so nothing is refunded: the money reverts to
     * what it actually is — the patient's money, held by the clinic, available for
     * the correct invoice. source='payment_reversal' so it can never be mistaken
     * for a refund by the ledger (which reads source='withdrawal') or by reports.
     */
    public function holdTenderAsCredit(
        int     $patientId,
        float   $amount,
        string  $reference,
        ?string $notes = null,
        ?int    $createdBy = null
    ): ?WalletTransaction {
        if ($amount < 0.01) {
            return null;
        }

        return DB::transaction(function () use ($patientId, $amount, $reference, $notes, $createdBy) {
            $wallet = Wallet::forPatient($patientId);

            $tx = WalletTransaction::create([
                'wallet_id'    => $wallet->id,
                'patient_id'   => $patientId,
                'direction'    => 'credit',
                'credit_type'  => 'permanent',
                'funding'      => self::FUNDING_PATIENT,
                'source'       => 'payment_reversal',
                'amount'       => $amount,
                'notes'        => 'Payment corrected (' . $reference . ') — tender held as patient credit'
                                  . ($notes ? '. ' . $notes : ''),
                'created_by'   => $createdBy,
            ]);

            $wallet->recalculate();

            return $tx;
        });
    }

    /**
     * A2 — remove patient credit that was never real money (duplicate / wrong
     * entry being reversed).
     *
     * Refuses if the credit has since been consumed: reversing less than the full
     * amount would leave the wallet and the invoices telling different stories.
     * The caller is expected to block the whole correction on this exception.
     *
     * @throws ValidationException when the credit is no longer available.
     */
    public function reverseUnusedCredit(
        int     $patientId,
        float   $amount,
        string  $reference,
        ?int    $createdBy = null
    ): ?WalletTransaction {
        if ($amount < 0.01) {
            return null;
        }

        return DB::transaction(function () use ($patientId, $amount, $reference, $createdBy) {
            $wallet = Wallet::forPatientLocked($patientId);
            $wallet->recalculate();
            $wallet->refresh();

            if ((float) $wallet->balance_patient_credit + 0.009 < $amount) {
                throw ValidationException::withMessages([
                    'void' => 'This payment created Rs. ' . number_format($amount, 2)
                            . ' of patient credit and only Rs. '
                            . number_format((float) $wallet->balance_patient_credit, 2)
                            . ' remains — the rest has already been used on another invoice. '
                            . 'It cannot be reversed. Handle the difference as a separate, '
                            . 'authorised refund or credit adjustment instead.',
                ]);
            }

            $tx = WalletTransaction::create([
                'wallet_id'    => $wallet->id,
                'patient_id'   => $patientId,
                'direction'    => 'debit',
                'credit_type'  => 'permanent',
                'funding'      => self::FUNDING_PATIENT,
                'source'       => 'payment_reversal',
                'amount'       => $amount,
                'notes'        => 'Payment reversed (' . $reference . ') — credit was never received',
                'created_by'   => $createdBy,
            ]);

            $wallet->recalculate();

            return $tx;
        });
    }

    /**
     * A1 — FULL refund of a patient's refundable, patient-funded wallet credit.
     *
     * FROZEN BUSINESS RULE (Tulip Dental): wallet refunds are all-or-nothing.
     * There is no partial refund. The operation therefore takes NO amount from
     * the caller — it determines the refundable balance itself, under a row
     * lock, and refunds exactly that. $expectedAmount is a confirmation value
     * only: if a client sends one and it does not match the real refundable
     * balance, the refund is REJECTED rather than quietly rounded to the full
     * amount (an operator who asked for 5,000 must never be handed 10,000).
     *
     * Eligibility is balance_patient_credit and nothing else — money the
     * patient actually handed over (U8 `funding='patient'`). Promotional and
     * clinic-funded credit is a concession, never the patient's money, and can
     * never leave the building as cash. It stays in wallet history untouched.
     *
     * ACCOUNTING. A refund reverses an advance; it is not a cost of doing
     * business:
     *
     *   Patient advance / liability   -X   (wallet debit, funding='patient')
     *   Cash / bank                   -X   (FinanceTransaction type='refund')
     *   Revenue                        0   (no income row is written)
     *   Expense                        0   (finance_expenses is never touched;
     *                                       'refund' is its own reported type)
     *
     * ATOMICITY. Everything below happens in ONE transaction and every failure
     * path THROWS rather than returning a falsy value. The predecessor
     * (withdraw() + controller-side finance row) could silently return 0.0 and
     * leave the caller reporting success with no ledger entry at all — cash out
     * of the drawer, nothing on the books. That shape is deliberately gone:
     * either all four records exist, or none do.
     *
     * @return array{refunded: float, transaction: WalletTransaction, finance_transaction: FinanceTransaction}
     *
     * @throws ValidationException when there is nothing refundable, or when a
     *         partial amount was requested.
     */
    public function refundFullPatientCredit(
        int     $patientId,
        string  $paymentMode,
        string  $refundDate,
        string  $reason,
        ?int    $createdBy = null,
        ?float  $expectedAmount = null
    ): array {
        return DB::transaction(function () use (
            $patientId, $paymentMode, $refundDate, $reason, $createdBy, $expectedAmount
        ) {
            // Row lock FIRST, then recompute from the ledger: two counters
            // clicking Refund at the same moment must serialise, or the same
            // credit is paid out twice.
            $wallet = Wallet::forPatientLocked($patientId);
            $wallet->recalculate();
            $wallet->refresh();

            $refundable = round((float) $wallet->balance_patient_credit, 2);

            if ($refundable < 0.01) {
                throw ValidationException::withMessages([
                    'refund' => 'This patient has no refundable credit. '
                              . 'Only money the patient actually paid in can be refunded — '
                              . 'promotional and clinic-funded credit never can.',
                ]);
            }

            if ($expectedAmount !== null && abs(round($expectedAmount, 2) - $refundable) > 0.009) {
                throw ValidationException::withMessages([
                    'amount' => 'Partial wallet refunds are not supported. The full refundable '
                              . 'balance is Rs. ' . number_format($refundable, 2)
                              . ' and a refund must be for exactly that amount.',
                ]);
            }

            $patient = Patient::findOrFail($patientId);

            // 1. Wallet debit — the liability comes down.
            $tx = WalletTransaction::create([
                'wallet_id'    => $wallet->id,
                'patient_id'   => $patientId,
                'direction'    => 'debit',
                'credit_type'  => 'permanent',
                'funding'      => self::FUNDING_PATIENT,
                'source'       => 'withdrawal',
                'amount'       => $refundable,
                'payment_mode' => $paymentMode,
                'notes'        => 'Refund: ' . $reason,
                'created_by'   => $createdBy,
            ]);

            $wallet->recalculate();
            $wallet->refresh();

            // 2. Post-condition. A full refund must leave nothing refundable.
            //    If it does, something raced us despite the lock — roll the whole
            //    thing back rather than hand out cash against a stale number.
            if ((float) $wallet->balance_patient_credit > 0.009) {
                throw new \RuntimeException(
                    'Wallet refund aborted: patient credit did not settle to zero '
                    . '(left Rs. ' . number_format($wallet->balance_patient_credit, 2) . ').'
                );
            }

            // 3. Finance mirror — cash leaving. NOT an expense, NOT negative
            //    revenue: its own 'refund' type, reported in its own column.
            $financeTx = FinanceTransaction::create([
                'type'              => 'refund',
                'direction'         => 'debit',
                'source_type'       => WalletTransaction::class,
                'source_id'         => $tx->id,
                'amount'            => $refundable,
                'net_amount'        => $refundable,
                'payment_mode'      => $paymentMode,
                'patient_id'        => $patientId,
                'status'            => 'active',
                'transaction_date'  => $refundDate,
                'notes'             => 'Wallet refund — reversal of patient advance. ' . $reason,
                'created_by'        => $createdBy,
            ]);

            // 4. Audit.
            if ($createdBy !== null) {
                BillingAuditLog::record('wallet_refund', $tx,
                    'Full refund Rs. ' . number_format($refundable, 2)
                    . ' (' . $paymentMode . '). ' . $reason,
                    $createdBy, 'Wallet · ' . $patient->name);
            }

            return [
                'refunded'            => $refundable,
                'transaction'         => $tx,
                'finance_transaction' => $financeTx,
            ];
        });
    }

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

            $take = round(min((float) $wallet->balance_patient_credit, max(0, $amount)), 2);
            if ($take <= 0) {
                return 0.0;
            }

            WalletTransaction::create([
                'wallet_id'    => $wallet->id,
                'patient_id'   => $patientId,
                'direction'    => 'debit',
                'credit_type'  => 'permanent',
                'funding'      => self::FUNDING_PATIENT,
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
     * Apply a manual patient-credit correction. A debit is capped at the
     * patient-funded balance. $direction = 'credit' | 'debit'.
     */
    public function adjust(
        int     $patientId,
        float   $amount,
        string  $direction,
        string  $reason,
        ?int    $createdBy = null,
        string  $funding = self::FUNDING_PATIENT
    ): ?WalletTransaction {
        return DB::transaction(function () use ($patientId, $amount, $direction, $reason, $createdBy, $funding) {
            // Row lock + fresh totals: adjustment can be a debit path.
            $wallet = Wallet::forPatientLocked($patientId);
            $wallet->recalculate();
            $wallet->refresh();

            $amt = round(max(0, $amount), 2);
            if ($direction === 'debit') {
                $amt = round(min($amt, (float) $wallet->balance_patient_credit), 2);
            }
            if ($amt <= 0) {
                return null;
            }

            $tx = WalletTransaction::create([
                'wallet_id'    => $wallet->id,
                'patient_id'   => $patientId,
                'direction'    => $direction === 'debit' ? 'debit' : 'credit',
                'credit_type'  => 'permanent',
                'funding'      => $funding,
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
                'funding'        => self::FUNDING_CLINIC,
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
     * U8 — consume PATIENT-FUNDED credit for an invoice settlement.
     * Written as a single debit row against the patient-credit pot. No expiry
     * filter: patient credit never expires (rule 11).
     */
    private function consumePatientCredit(
        Wallet  $wallet,
        float   $needed,
        Invoice $invoice,
        ?int    $createdBy
    ): float {
        $take = round(min((float) $wallet->balance_patient_credit, $needed), 2);
        if ($take <= 0) {
            return 0.0;
        }

        WalletTransaction::create([
            'wallet_id'      => $wallet->id,
            'patient_id'     => $wallet->patient_id,
            'direction'      => 'debit',
            'credit_type'    => 'permanent',
            'funding'        => self::FUNDING_PATIENT,
            'source'         => 'invoice_payment',
            'amount'         => $take,
            'invoice_id'     => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'notes'          => 'Patient credit used as payment tender',
            'created_by'     => $createdBy,
        ]);

        return $take;
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
            'balance_total'          => (float) $wallet->balance_total,
            'balance_promotional'    => (float) $wallet->balance_promotional,
            'balance_permanent'      => (float) $wallet->balance_permanent,
            'balance_patient_credit' => (float) $wallet->balance_patient_credit,
            'expiring_soon'          => (float) $expiringSoon,
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
