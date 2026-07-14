<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Patient;
use App\Models\Receipt;
use App\Models\FinalBill;
use App\Models\EmiScheme;
use App\Models\EmiSchedule;
use App\Models\AppSetting;
use App\Models\Finance\FinanceBankAccount;
use App\Models\Finance\FinanceTransaction;
use App\Models\Wallet;
use App\Services\Relationship\ActivityEngine;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * InvoicePaymentService
 * ---------------------
 * Shared "brain" for recording a payment against an invoice.
 *
 * This is a verbatim replica of the web BillingController@recordPayment +
 * @markProviderPaid logic (the 7-step finance chain). It is called by the
 * mobile Api/V1/BillingController so a payment recorded on mobile produces
 * byte-for-byte identical records to one recorded on web:
 *   InvoicePayment -> invoice->recalculate() -> Receipt -> (FinalBill if fully
 *   paid) -> FinanceTransaction. Direct EMI also writes an EmiSchedule; provider
 *   EMI uses the split-receipt model.
 *
 * The web controller is intentionally left untouched (same approach taken for
 * MembershipBenefitService) to avoid risk to working finance code. The logic is
 * duplicated but identical; unify later if desired.
 */
class InvoicePaymentService
{
    /**
     * Record a payment. $in is already-validated input with keys matching the
     * web form (amount, payment_mode, payment_date, reference_no, notes,
     * clinic_account_id, bank_name, cheque_no, cheque_date, convenience_fee,
     * emi_type, emi_provider, emi_tenure, emi_interest_rate, emi_start_date,
     * emi_provider_scheme_id, emi_upfront_amount).
     *
     * @return array{payment: InvoicePayment, receipt: ?Receipt, invoice: Invoice, message: string}
     */
    public function recordPayment(Invoice $invoice, array $in, int $userId): array
    {
        $mode    = $in['payment_mode'];
        $emiType = $in['emi_type'] ?? 'direct'; // 'direct' or 'provider'

        // ── Credit Card: convenience fee (configurable in Settings → Billing) ─
        // Fee applies per-transaction: when THIS payment amount exceeds the
        // threshold, add (rate %) of the amount. Threshold & rate come from
        // AppSetting (billing group), defaulting to ₹10,000 and 2.5%.
        $convenienceFee = 0;
        if ($mode === 'card') {
            $threshold = (float) AppSetting::get('cc_convenience_threshold', 10000);
            $rate      = (float) AppSetting::get('cc_convenience_rate', 2.5);
            $amount    = (float) $in['amount'];

            if ($amount > $threshold) {
                $convenienceFee = round($amount * $rate / 100, 2);
            }

            // Server value is authoritative; never trust a lower submitted value
            $submitted      = (float) ($in['convenience_fee'] ?? 0);
            $convenienceFee = max($convenienceFee, $submitted);
        }

        // ── Provider EMI: load scheme & compute breakdown ────────────────────
        $providerScheme    = null;
        $providerBreakdown = null;
        if ($mode === 'emi' && $emiType === 'provider') {
            $providerScheme    = EmiScheme::findOrFail($in['emi_provider_scheme_id']);
            $invoiceTotal      = (float) $invoice->total_amount;
            $providerBreakdown = $providerScheme->breakdown($invoiceTotal);

            // Convenience fee from scheme (override if submitted value differs)
            if ($providerScheme->pass_cost_to_patient) {
                $convenienceFee = $providerBreakdown['convenience_charge'];
            }
        }

        $receipt = null;
        $payment = null;

        DB::transaction(function () use (
            $in, $invoice, $mode, $emiType, $userId,
            $convenienceFee, $providerScheme, $providerBreakdown,
            &$receipt, &$payment
        ) {
            // ── Concurrency guard (parity with web recordPayment) ────────────
            // Lock the invoice row so concurrent submissions serialize, then
            // re-check state on fresh data and reject an identical resubmission
            // (double-tap / network retry) within a short window.
            Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $invoice->refresh();

            if ($invoice->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'amount' => 'Cannot record payment on a cancelled invoice.',
                ]);
            }

            $isDuplicate = InvoicePayment::where('invoice_id', $invoice->id)
                ->where('amount', (float) $in['amount'])
                ->where('payment_mode', $mode)
                ->whereDate('payment_date', $in['payment_date'])
                ->where('created_at', '>=', now()->subSeconds(20))
                ->exists();
            if ($isDuplicate) {
                throw ValidationException::withMessages([
                    'amount' => 'An identical payment was recorded seconds ago — this looks like a duplicate submission. Refresh and verify before retrying.',
                ]);
            }

            $paidBefore = (float) $invoice->paid_amount;

            // ── Payment allocation: consume wallet credit first ──────────────
            // Parity with web recordPayment — only active when the caller
            // explicitly passes wallet_used (the mobile app may add this later;
            // absent key = unchanged behaviour).
            if ((float) ($in['wallet_used'] ?? 0) > 0) {
                $wallet = Wallet::forPatient($invoice->patient_id);
                $cap    = min((float) $in['wallet_used'], (float) $wallet->balance_total, (float) $invoice->balance_due);
                if ($cap > 0) {
                    $debited = (new WalletService())->debit(
                        patientId: $invoice->patient_id,
                        amount:    $cap,
                        invoiceId: $invoice->id,
                        createdBy: $userId,
                    );
                    if ($debited > 0) {
                        $invoice->update(['wallet_applied' => (float) $invoice->wallet_applied + $debited]);
                        $invoice->recalculate();
                        $invoice->refresh();
                    }
                }
            }

            // ── Direct EMI: compute instalment amount ────────────────────────
            $emiAmount = null;
            if ($mode === 'emi' && $emiType === 'direct' && ($in['emi_tenure'] ?? 0) > 0) {
                $schedule  = EmiSchedule::buildSchedule(
                    (float) $in['amount'],
                    (float) $in['emi_interest_rate'],
                    (int)   $in['emi_tenure'],
                    $in['emi_start_date']
                );
                $emiAmount = $schedule[0]['emi_amount'] ?? null;
            }

            // ── Provider EMI: derive stored values ───────────────────────────
            $clinicNetAmount  = null;
            $emiUpfrontAmount = null;
            if ($mode === 'emi' && $emiType === 'provider' && $providerBreakdown) {
                $clinicNetAmount  = $providerBreakdown['clinic_net_amount'];
                $emiUpfrontAmount = $in['emi_upfront_amount'] ?? $providerBreakdown['patient_upfront_amount'];
            }

            // 1. Save payment record
            // Resolve clinic account name for display caching
            $clinicAccountName = null;
            if (! empty($in['clinic_account_id'])) {
                $clinicAccountName = FinanceBankAccount::find($in['clinic_account_id'])?->account_name;
            }

            $payment = InvoicePayment::create([
                'invoice_id'             => $invoice->id,
                'patient_id'             => $invoice->patient_id,
                'amount'                 => $in['amount'],
                'payment_mode'           => $mode,
                'payment_date'           => $in['payment_date'],
                'reference_no'           => $in['reference_no'] ?? null,
                'notes'                  => $in['notes'] ?? null,
                'created_by'             => $userId,
                // Clinic account received in (Phase 2)
                'clinic_account_id'      => ($in['clinic_account_id'] ?? null) ?: null,
                'clinic_account_name'    => $clinicAccountName,
                // Cheque
                'bank_name'              => $in['bank_name'] ?? null,
                'cheque_no'              => $in['cheque_no'] ?? null,
                'cheque_date'            => $in['cheque_date'] ?? null,
                'cheque_status'          => $mode === 'cheque' ? 'pending' : null,
                // Credit card / Provider EMI convenience
                'convenience_fee'        => $convenienceFee,
                // Direct EMI fields
                'emi_type'               => $mode === 'emi' ? $emiType : null,
                'emi_provider'           => $mode === 'emi' && $emiType === 'direct' ? ($in['emi_provider'] ?? null) : null,
                'emi_tenure'             => $mode === 'emi' && $emiType === 'direct' ? $in['emi_tenure'] : ($providerScheme?->tenure_months),
                'emi_interest_rate'      => $mode === 'emi' && $emiType === 'direct' ? $in['emi_interest_rate'] : null,
                'emi_amount'             => $emiAmount ?? ($providerBreakdown ? $providerBreakdown['patient_monthly_emi'] : null),
                'emi_start_date'         => $mode === 'emi' && $emiType === 'direct' ? $in['emi_start_date'] : now()->toDateString(),
                // Provider EMI fields
                'emi_provider_scheme_id' => $providerScheme?->id,
                'emi_upfront_amount'     => $emiUpfrontAmount,
                'clinic_net_amount'      => $clinicNetAmount,
            ]);

            // 2. Generate instalment schedule (Direct EMI only)
            // Provider EMI: provider handles collection — no schedule needed here
            if ($mode === 'emi' && $emiType === 'direct' && ($in['emi_tenure'] ?? 0) > 0) {
                $schedule = EmiSchedule::buildSchedule(
                    (float) $in['amount'],
                    (float) $in['emi_interest_rate'],
                    (int)   $in['emi_tenure'],
                    $in['emi_start_date']
                );
                foreach ($schedule as $row) {
                    EmiSchedule::create([
                        'invoice_payment_id' => $payment->id,
                        'invoice_id'         => $invoice->id,
                        'patient_id'         => $invoice->patient_id,
                        'instalment_no'      => $row['instalment_no'],
                        'due_date'           => $row['due_date'],
                        'principal'          => $row['principal'],
                        'interest'           => $row['interest'],
                        'emi_amount'         => $row['emi_amount'],
                        'status'             => 'pending',
                        'created_by'         => $userId,
                    ]);
                }
            }

            // 3. Recalculate invoice totals
            $invoice->recalculate();
            $invoice->refresh();
            $balanceAfter = (float) $invoice->balance_due;

            // 3b. Excess payment → wallet credit (parity with web recordPayment).
            // If the patient paid more than the invoice total, the surplus becomes
            // permanent wallet credit (usable on future invoices). The full cash is
            // already recorded as income, so no extra finance entry is needed here.
            if ((float) $invoice->paid_amount > (float) $invoice->total_amount) {
                $excess = round((float) $invoice->paid_amount - (float) $invoice->total_amount, 2);
                if ($excess >= 0.01) {
                    (new WalletService())->deposit(
                        patientId:   $invoice->patient_id,
                        amount:      $excess,
                        paymentMode: $mode,
                        notes:       'Excess payment from ' . $invoice->invoice_number,
                        createdBy:   $userId,
                        source:      'advance',
                    );
                }
            }

            // 4. Generate receipt(s)
            //
            // Provider EMI — split receipt model:
            //   Receipt #1 (now)  → patient upfront amount only (emi_upfront_amount)
            //   Receipt #2 (later)→ clinic_net_amount, created by markProviderPaid()
            //
            // All other modes → single receipt for the full payment amount.

            if ($mode === 'emi' && $emiType === 'provider') {
                // Only issue Receipt #1 if the patient actually pays something upfront today.
                if ($emiUpfrontAmount > 0) {
                    $receipt = Receipt::create([
                        'receipt_number'     => Receipt::nextNumber(),
                        'invoice_id'         => $invoice->id,
                        'invoice_payment_id' => $payment->id,
                        'patient_id'         => $invoice->patient_id,
                        'amount'             => $emiUpfrontAmount,
                        'payment_mode'       => $mode,
                        'receipt_date'       => $in['payment_date'],
                        'reference_no'       => $in['reference_no'] ?? null,
                        'invoice_total'      => $invoice->total_amount,
                        'amount_paid_before' => $paidBefore,
                        'balance_after'      => $balanceAfter,
                        'notes'              => $in['notes'] ?? null,
                        'created_by'         => $userId,
                        'receipt_type'       => 'patient_upfront',
                    ]);
                }
                // Receipt #2 (provider_settlement) is created later via markProviderPaid().
            } else {
                // Standard single-receipt flow for cash / card / UPI / cheque / direct EMI.
                $receipt = Receipt::create([
                    'receipt_number'     => Receipt::nextNumber(),
                    'invoice_id'         => $invoice->id,
                    'invoice_payment_id' => $payment->id,
                    'patient_id'         => $invoice->patient_id,
                    'amount'             => (float) $in['amount'],
                    'payment_mode'       => $mode,
                    'receipt_date'       => $in['payment_date'],
                    'reference_no'       => $in['reference_no'] ?? null,
                    'invoice_total'      => $invoice->total_amount,
                    'amount_paid_before' => $paidBefore,
                    'balance_after'      => $balanceAfter,
                    'notes'              => $in['notes'] ?? null,
                    'created_by'         => $userId,
                ]);
            }

            // 6. Auto-generate FinalBill when fully paid
            if ($invoice->isFullyPaid() && !$invoice->hasFinalBill()) {
                FinalBill::generateFromInvoice($invoice, $userId);
            }

            // 7. Finance Mirror
            // For Provider EMI, net_amount reflects what clinic actually receives
            $financeNetAmount = ($mode === 'emi' && $emiType === 'provider' && $clinicNetAmount !== null)
                ? $clinicNetAmount
                : (float) $in['amount'];

            FinanceTransaction::create([
                'type'              => 'income',
                'direction'         => 'credit',
                'source_type'       => InvoicePayment::class,
                'source_id'         => $payment->id,
                'amount'            => (float) $in['amount'],
                'net_amount'        => $financeNetAmount,
                'payment_mode'      => $mode,
                'payment_reference' => $in['reference_no'] ?? null,
                'patient_id'        => $invoice->patient_id,
                'status'            => 'active',
                'transaction_date'  => $in['payment_date'],
                'notes'             => $in['notes'] ?? null,
                'created_by'        => $userId,
            ]);

            // Additive Activity log (docs/backend-orchestration-plan.md §2.9) —
            // no rule currently matches 'payment.received', feeds Insights only.
            // Covers the mobile API path (the only caller of this service today).
            app(ActivityEngine::class)->log(
                subject:        $payment,
                event:          'payment.received',
                actor:          null,
                metadata:       ['patient_id' => $invoice->patient_id, 'invoice_id' => $invoice->id, 'amount' => (float) $in['amount']],
                relationshipId: Patient::find($invoice->patient_id)?->relationship_id,
                description:    'Payment recorded on invoice ' . $invoice->invoice_number,
            );
        });

        // Build flash-style message (mirrors the web controller wording)
        if ($mode === 'emi' && $emiType === 'provider' && $providerBreakdown) {
            $upfrontFmt = number_format($providerBreakdown['patient_upfront_amount'], 2);
            $netFmt     = number_format($providerBreakdown['clinic_net_amount'], 2);

            if ($receipt) {
                $msg = 'Provider EMI recorded. Upfront receipt ' . $receipt->receipt_number . ' (Rs. ' . $upfrontFmt . ') generated.';
            } else {
                $msg = 'Provider EMI recorded — no upfront payment (0 upfront EMIs).';
            }
            $msg .= ' Clinic net: Rs. ' . $netFmt . '. Mark provider payment received to generate settlement receipt.';
            if ($convenienceFee > 0) {
                $msg .= ' Convenience charge Rs. ' . number_format($convenienceFee, 2) . ' included in patient loan.';
            }
        } else {
            $msg = 'Rs. ' . number_format((float) $in['amount'], 2) . ' recorded. Receipt ' . $receipt->receipt_number . ' generated.';
            if ($convenienceFee > 0) {
                $msg .= ' Convenience fee Rs. ' . number_format($convenienceFee, 2) . ' applied.';
            }
        }

        $invoice->refresh();
        if ($invoice->isFullyPaid()) {
            $msg .= ' Invoice fully paid — Final Bill generated.';
        }

        return [
            'payment' => $payment,
            'receipt' => $receipt,
            'invoice' => $invoice,
            'message' => $msg,
        ];
    }

    /**
     * Mark a Provider-EMI payment as received from the provider. Generates
     * Receipt #2 (provider_settlement) for the clinic_net_amount and stamps the
     * payment. Verbatim replica of BillingController@markProviderPaid (minus the
     * HTTP redirects / guards, which the controller performs before calling).
     *
     * @return Receipt the settlement receipt
     */
    public function markProviderPaid(Invoice $invoice, InvoicePayment $payment, array $in, int $userId): Receipt
    {
        return DB::transaction(function () use ($invoice, $payment, $in, $userId) {
            $receipt = Receipt::create([
                'receipt_number'     => Receipt::nextNumber(),
                'invoice_id'         => $invoice->id,
                'invoice_payment_id' => $payment->id,
                'patient_id'         => $invoice->patient_id,
                'amount'             => $payment->clinic_net_amount,
                'payment_mode'       => 'emi',
                'receipt_date'       => $in['provider_paid_date'],
                'reference_no'       => $in['provider_reference'] ?? null,
                'invoice_total'      => $invoice->total_amount,
                'amount_paid_before' => (float) $invoice->paid_amount,
                'balance_after'      => (float) $invoice->balance_due,
                'notes'              => 'Provider EMI settlement receipt.',
                'created_by'         => $userId,
                'receipt_type'       => 'provider_settlement',
            ]);

            $payment->update(['provider_paid_at' => now()]);

            return $receipt;
        });
    }

    /**
     * Correct the date on an already-recorded payment. There was previously no
     * way to do this at all — once saved, payment_date/receipt_date rendered
     * as static text with no edit path anywhere in the app.
     *
     * Cascades to the linked Receipt(s) and FinanceTransaction so the payment
     * date, the receipt date, and the finance ledger date always agree —
     * otherwise a corrected receipt would silently disagree with the books.
     *
     * @return InvoicePayment the updated payment (fresh)
     */
    public function updatePaymentDate(InvoicePayment $payment, string $newDate, int $userId): InvoicePayment
    {
        DB::transaction(function () use ($payment, $newDate, $userId) {
            $payment->update(['payment_date' => $newDate]);

            Receipt::where('invoice_payment_id', $payment->id)
                ->update(['receipt_date' => $newDate]);

            FinanceTransaction::where('source_type', InvoicePayment::class)
                ->where('source_id', $payment->id)
                ->update(['transaction_date' => $newDate]);
        });

        return $payment->fresh();
    }
}
