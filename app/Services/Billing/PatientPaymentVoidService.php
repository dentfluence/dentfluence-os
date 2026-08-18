<?php

namespace App\Services\Billing;

use App\Models\BillingAuditLog;
use App\Models\Finance\FinanceTransaction;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Patient;
use App\Models\Receipt;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * PatientPaymentVoidService — A2
 * =============================
 *
 * Corrects a consolidated PAY- tender that was recorded wrongly.
 *
 * VOID IS NOT REFUND. This service never returns money to anybody. It reverses
 * an incorrect accounting entry and leaves the money where it actually is. A
 * refund is a separate, separately-authorised decision (A1,
 * WalletService::refundFullPatientCredit) and nothing here triggers it.
 *
 * ALWAYS A FULL, PATIENT-LEVEL REVERSAL. A tender is one patient payment; the
 * InvoicePayment rows beneath it are allocation records produced by the FIFO
 * allocator, not separate payments. There is no partial reversal and no way to
 * move money between invoices by hand — invoice allocation is the allocator's
 * job, so "it went to the wrong invoice" is not a correction scenario at all.
 *
 * The ONE thing a human must tell us is a fact about the real world that the
 * database cannot know: DID THE MONEY ACTUALLY ARRIVE? The answer decides where
 * the reversed tender ends up, and there is no safe default:
 *
 *   RECEIVED      the money is genuinely in hand and still owed to the patient.
 *                 Allocations are reversed and the whole tender becomes PATIENT
 *                 CREDIT — which is what it is: the patient's money, held by the
 *                 clinic, not yet consumed. FIFO will apply it to the oldest open
 *                 invoice on the next payment. No cash moves; none ever did.
 *
 *   NOT_RECEIVED  the entry was a duplicate or a mistake; no money ever arrived.
 *                 Allocations are reversed and NO credit is created — inventing
 *                 credit would hand the patient a balance they never paid. Any
 *                 patient credit the original tender created is removed, and if
 *                 that credit has already been spent the whole correction is
 *                 BLOCKED rather than left half-done.
 *
 * NOTHING IS DELETED. The PAY- receipt is marked voided and stays visible.
 * Income FinanceTransactions move to status='voided' (the existing convention);
 * they are never removed. InvoicePayment rows are soft-deleted exactly as the
 * invoice-scoped void does, keeping their void audit columns.
 *
 * Atomic and idempotent: one transaction, receipt locked first, invoices locked
 * in id order. A second void request performs ZERO writes.
 */
class PatientPaymentVoidService
{
    /** The money is real and still owed to the patient → becomes patient credit. */
    public const RECEIVED     = 'received';

    /** Duplicate / mistaken entry → no money ever arrived, so no credit. */
    public const NOT_RECEIVED = 'not_received';

    public const CORRECTION_TYPES = [self::RECEIVED, self::NOT_RECEIVED];

    /**
     * @return array{voided: bool, already_voided: bool, receipt: Receipt,
     *               reversed: float, credit_held: float, credit_reversed: float,
     *               invoices: array<int,string>}
     *
     * @throws ValidationException
     */
    public function void(
        Receipt $receipt,
        string  $correctionType,
        string  $reason,
        ?int    $userId = null
    ): array {
        if (! in_array($correctionType, self::CORRECTION_TYPES, true)) {
            throw ValidationException::withMessages([
                'correction_type' => 'State whether the money was actually received or never '
                                   . 'arrived at all. Received money stays as patient credit; '
                                   . 'money that never arrived leaves no credit behind.',
            ]);
        }

        return DB::transaction(function () use ($receipt, $correctionType, $reason, $userId) {

            // ── Lock the receipt FIRST. This is the idempotency and concurrency
            //    gate: two simultaneous void attempts serialise here, and the
            //    second one sees voided_at already set.
            $locked = Receipt::whereKey($receipt->id)->lockForUpdate()->firstOrFail();

            if ($locked->voided_at !== null) {
                return [
                    'voided'          => false,
                    'already_voided'  => true,
                    'receipt'         => $locked,
                    'reversed'        => 0.0,
                    'credit_held'     => 0.0,
                    'credit_reversed' => 0.0,
                    'invoices'        => [],
                ];
            }

            if ($locked->invoice_id !== null) {
                throw ValidationException::withMessages([
                    'void' => 'This is an invoice receipt. Void it from the invoice instead.',
                ]);
            }

            $payments = InvoicePayment::where('receipt_id', $locked->id)
                ->orderBy('invoice_id')
                ->get();

            $breakdown = $locked->allocation_breakdown ?? [];
            $surplus   = round((float) ($breakdown['patient_credit'] ?? 0), 2);
            $settled   = round((float) ($breakdown['settled'] ?? 0), 2);

            // A receipt with allocations but no links is a pre-A2 record whose
            // payments could not be identified unambiguously. Refuse rather than
            // reverse a payment we guessed at.
            if ($payments->isEmpty() && $settled > 0.009) {
                throw ValidationException::withMessages([
                    'void' => 'This receipt predates payment-to-receipt linking and its individual '
                            . 'payments cannot be identified with certainty, so it cannot be '
                            . 'reversed automatically. Correct the affected invoices individually.',
                ]);
            }

            $paymentTotal = round((float) $payments->sum('amount'), 2);
            if (abs($paymentTotal - $settled) > 0.009) {
                throw ValidationException::withMessages([
                    'void' => 'The linked payments total Rs. ' . number_format($paymentTotal, 2)
                            . ' but this receipt settled Rs. ' . number_format($settled, 2)
                            . '. Refusing to reverse a partially-changed allocation.',
                ]);
            }

            // Resolved from the container, not `new`, so it is substitutable —
            // an untestable rollback path is an untested rollback path.
            $wallet         = app(WalletService::class);
            $creditReversed = 0.0;
            $creditHeld     = 0.0;

            // ── The surplus, BEFORE touching invoices. reverseUnusedCredit()
            //    throws when the credit has been spent, and doing it first means
            //    a blocked correction has changed nothing at all.
            if ($correctionType === self::NOT_RECEIVED && $surplus > 0.009) {
                $wallet->reverseUnusedCredit(
                    patientId: $locked->patient_id,
                    amount:    $surplus,
                    reference: $locked->receipt_number,
                    createdBy: $userId,
                );
                $creditReversed = $surplus;
            }

            // ── Reverse each allocation. Same mechanics as the invoice-scoped
            //    void: audit onto the payment, income voided (not deleted),
            //    payment soft-deleted, invoice recalculated.
            $invoiceNumbers = [];

            foreach ($payments as $payment) {
                $invoice = Invoice::whereKey($payment->invoice_id)->lockForUpdate()->first();

                $payment->update([
                    'void_reason'        => $reason,
                    'voided_by'          => $userId,
                    'void_refund_method' => 'no_refund',   // a correction never refunds
                    'void_refund_amount' => 0,
                ]);

                FinanceTransaction::where('source_type', InvoicePayment::class)
                    ->where('source_id', $payment->id)
                    ->where('status', 'active')
                    ->update(['status' => 'voided']);

                $payment->delete();   // soft-delete — history preserved

                if ($invoice) {
                    $invoice->refresh();
                    $invoice->recalculate();
                    $invoice->refresh();

                    if ($invoice->status !== 'paid' && $invoice->finalBill) {
                        $invoice->finalBill->update([
                            'deleted_reason' => 'Auto-invalidated: payment on receipt '
                                                . $locked->receipt_number . ' was reversed. ' . $reason,
                            'deleted_by'     => $userId,
                        ]);
                        $invoice->finalBill->delete();
                    }

                    $invoiceNumbers[] = $invoice->invoice_number;
                }
            }

            // ── Where the money now sits.
            if ($correctionType === self::RECEIVED && $settled > 0.009) {
                // Real money, still the patient's and still unconsumed.
                $wallet->holdTenderAsCredit(
                    patientId: $locked->patient_id,
                    amount:    $settled,
                    reference: $locked->receipt_number,
                    notes:     $reason,
                    createdBy: $userId,
                );
                $creditHeld = $settled;

                // The liability replaces the recognised revenue. NOT a refund and
                // NOT an expense — its own 'advance' type, matching U8.
                FinanceTransaction::create([
                    'type'              => 'advance',
                    'direction'         => 'credit',
                    'source_type'       => Receipt::class,
                    'source_id'         => $locked->id,
                    'amount'            => $settled,
                    'net_amount'        => $settled,
                    'payment_mode'      => $locked->payment_mode,
                    'patient_id'        => $locked->patient_id,
                    'status'            => 'active',
                    'transaction_date'  => now()->toDateString(),
                    'notes'             => 'Payment correction ' . $locked->receipt_number
                                           . ' — revenue reversed, tender held as patient credit. ' . $reason,
                    'created_by'        => $userId,
                ]);
            }

            if ($correctionType === self::NOT_RECEIVED && $surplus > 0.009) {
                // The advance liability was never real either.
                FinanceTransaction::where('type', 'advance')
                    ->where('source_type', WalletTransaction::class)
                    ->where('patient_id', $locked->patient_id)
                    ->where('status', 'active')
                    ->where('amount', $surplus)
                    ->whereDate('transaction_date', $locked->receipt_date)
                    ->limit(1)
                    ->update(['status' => 'voided']);
            }

            // ── Mark the receipt VOID. Never deleted.
            $locked->update([
                'voided_at'            => now(),
                'void_reason'          => $reason,
                'voided_by'            => $userId,
                'void_correction_type' => $correctionType,
            ]);

            if ($userId !== null) {
                $patient = Patient::find($locked->patient_id);
                BillingAuditLog::record(
                    'void_patient_payment',
                    $locked,
                    'Payment correction (' . $correctionType . '), Rs. '
                        . number_format((float) $locked->amount, 2) . '. ' . $reason,
                    $userId,
                    $locked->receipt_number . ($patient ? ' · ' . $patient->name : '')
                );
            }

            return [
                'voided'          => true,
                'already_voided'  => false,
                'receipt'         => $locked->fresh(),
                'reversed'        => $paymentTotal,
                'credit_held'     => $creditHeld,
                'credit_reversed' => $creditReversed,
                'invoices'        => $invoiceNumbers,
            ];
        });
    }
}
