<?php

namespace App\Services\Billing;

use App\Models\BillingAuditLog;
use App\Models\Finance\FinanceTransaction;
use App\Models\Receipt;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Samiksha added Rs. 500 today, realised it was a mistake, marks it a wrong
 * entry, and it leaves the patient's wallet." One action for the operator.
 *
 * Underneath, an advance is never one record. receiveAdvance() writes three:
 *
 *   1. a wallet credit      (funding = patient — the patient's money)
 *   2. an ADV- receipt      (the patient's proof that cash was handed over)
 *   3. a FinanceTransaction (type 'advance' — cash in, liability up, NOT revenue)
 *
 * Undoing only the wallet row would leave the cashbook holding money that is
 * not in the drawer and a live receipt for cash that never arrived. So all
 * three are reversed together, in one transaction, or none are.
 *
 * WHY THIS IS NOT A REFUND. A refund records cash going back OUT to the
 * patient. For an entry that was simply never real, no cash moved in either
 * direction, and recording an outflow would put a second lie in the cashbook
 * to cover the first. This marks the entry as never having happened.
 *
 * WHY NOT PatientPaymentVoidService. That service reverses consolidated PAY-
 * receipts and reads the credit leg out of allocation_breakdown. A standalone
 * ADV- receipt has no breakdown, so its NOT_RECEIVED branch finds nothing to
 * do — which is exactly why the receipt view hides its form for advances.
 *
 * NOTHING IS DELETED. The wallet credit stays and gains a linked debit; the
 * receipt keeps its number and is marked void; the FinanceTransaction moves to
 * status 'voided'. Every one of them stays readable afterwards.
 */
class AdvanceReversalService
{
    /**
     * $userId is required, not optional. This moves the cashbook and voids a
     * patient's receipt, so the audit row must name a real person — there is no
     * sensible "system" actor here, and a 0 placeholder just breaks the foreign
     * key at write time.
     *
     * @return array{reversed: float, debit: WalletTransaction, receipt: ?Receipt}
     *
     * @throws ValidationException when the advance is not reversible, or when
     *         any part of it has already been spent.
     */
    public function reverse(
        WalletTransaction $advance,
        string  $reason,
        int     $userId
    ): array {
        return DB::transaction(function () use ($advance, $reason, $userId) {

            // Lock the wallet first, then re-read the row under it. Two
            // operators clicking at once serialise here; the second one sees
            // the reversal already written and is refused.
            $wallet = Wallet::forPatientLocked($advance->patient_id);
            $advance = WalletTransaction::whereKey($advance->id)->lockForUpdate()->firstOrFail();

            if ($advance->direction !== 'credit' || $advance->source !== 'advance') {
                throw ValidationException::withMessages([
                    'reversal' => 'Only an advance entry can be marked as a wrong entry.',
                ]);
            }

            if (WalletTransaction::where('reversal_of_transaction_id', $advance->id)->exists()) {
                throw ValidationException::withMessages([
                    'reversal' => 'This entry has already been marked as a wrong entry.',
                ]);
            }

            $wallet->recalculate();
            $wallet->refresh();

            $amount = (float) $advance->amount;

            // All or nothing, matching the wallet refund (A1) and payment
            // correction (A2) rules. If part of this money has already settled
            // an invoice, reversing it here would leave the wallet and that
            // invoice telling different stories.
            if ((float) $wallet->balance_patient_credit + 0.009 < $amount) {
                throw ValidationException::withMessages([
                    'reversal' => 'Rs. ' . number_format($amount, 2) . ' was received as an advance but only Rs. '
                        . number_format((float) $wallet->balance_patient_credit, 2) . ' is still unused — '
                        . 'the rest has already been applied to an invoice. Correct that invoice first, '
                        . 'or issue a refund if the money really was received.',
                ]);
            }

            $debit = WalletTransaction::create([
                'wallet_id'                  => $wallet->id,
                'patient_id'                 => $advance->patient_id,
                'direction'                  => 'debit',
                'credit_type'                => $advance->credit_type,
                'funding'                    => $advance->funding,
                'source'                     => 'credit_reversal',
                'amount'                     => $amount,
                'payment_mode'               => $advance->payment_mode,
                'reversal_of_transaction_id' => $advance->id,
                'notes'                      => 'Wrong entry — advance reversed. ' . $reason,
                'created_by'                 => $userId,
            ]);

            // The cashbook entry. receiveAdvance() stamped source_id with this
            // wallet row, so this is an exact link and never a match on
            // patient + amount + date, which can hit the wrong day's entry.
            FinanceTransaction::where('source_type', WalletTransaction::class)
                ->where('source_id', $advance->id)
                ->where('status', 'active')
                ->update(['status' => 'voided']);

            // The patient's receipt. Number preserved — statutory numbering
            // must not develop holes.
            $receipt = Receipt::where('wallet_transaction_id', $advance->id)
                ->whereNull('voided_at')
                ->lockForUpdate()
                ->first();

            $receipt?->update([
                'voided_at'            => now(),
                'void_reason'          => $reason,
                'voided_by'            => $userId,
                'void_correction_type' => PatientPaymentVoidService::NOT_RECEIVED,
            ]);

            $wallet->recalculate();

            BillingAuditLog::record(
                'advance_wrong_entry',
                $debit,
                'Advance of Rs. ' . number_format($amount, 2) . ' marked as a wrong entry'
                    . ($receipt ? ' (receipt ' . $receipt->receipt_number . ' voided)' : '')
                    . '. ' . $reason,
                $userId,
                'Wallet · ' . ($advance->patient?->name ?? ('Patient #' . $advance->patient_id))
            );

            return [
                'reversed' => $amount,
                'debit'    => $debit,
                'receipt'  => $receipt,
            ];
        });
    }
}
