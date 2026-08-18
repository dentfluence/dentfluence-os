<?php

namespace App\Services\Billing;

use App\Models\BillingAuditLog;
use App\Models\FinalBill;
use App\Models\Finance\FinanceTransaction;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Receipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * ReceiptRestoreService
 * =====================
 *
 * Restores a receipt from Trash to the EXACT accounting state that existed
 * immediately before it was voided. No new financial record is ever created.
 *
 * WHY THIS EXISTS. Voiding a receipt touches six records: the InvoicePayment
 * (void audit + soft-delete), its income FinanceTransaction (status='voided'),
 * the Receipt (soft-delete), the FinalBill (soft-delete), an audit log, and a
 * compensating type='refund' FinanceTransaction. Restore was
 *
 *     Receipt::onlyTrashed()->findOrFail($id)->restore();
 *
 * — one record out of six. The result: the ledger reads receipts (the credit
 * reappears) while invoice.paid_amount reads invoice_payments (still deleted),
 * so a restored receipt showed as paid on one panel and unpaid on the other.
 * Real bug found at Tulip on INV-2026-00075 / RCP-2026-00064.
 *
 * WHAT IT WILL NOT DO. If the original payment or its finance row cannot be
 * found, it refuses and logs. Fabricating a payment to make an invoice look paid
 * would turn a visible inconsistency into an invisible one.
 */
class ReceiptRestoreService
{
    /**
     * @return array{restored: bool, already_active: bool, receipt: Receipt,
     *               payments_restored: int, amount: float, invoices: array<int,string>}
     *
     * @throws ValidationException
     */
    public function restore(int $receiptId, ?int $userId = null): array
    {
        return DB::transaction(function () use ($receiptId, $userId) {

            // Lock first — idempotency and concurrency gate in one.
            $receipt = Receipt::withTrashed()->whereKey($receiptId)->lockForUpdate()->firstOrFail();

            if ($receipt->deleted_at === null) {
                return [
                    'restored'          => false,
                    'already_active'    => true,
                    'receipt'           => $receipt,
                    'payments_restored' => 0,
                    'amount'            => (float) $receipt->amount,
                    'invoices'          => [],
                ];
            }

            // ── Refuse when the void actually returned money or credit ───────
            // The void writes a compensating type='refund' row. net_amount = 0
            // means "no_refund" — nothing left the clinic, so the void was a pure
            // correction and is safe to undo. net_amount > 0 means cash, a bank
            // transfer or wallet credit was genuinely handed over; un-voiding that
            // would re-recognise revenue for money the clinic no longer holds, and
            // the credit may already have been spent.
            $compensating = FinanceTransaction::where('source_type', Receipt::class)
                ->where('source_id', $receipt->id)
                ->where('type', 'refund')
                ->where('status', 'active')
                ->get();

            if ($compensating->where('net_amount', '>', 0.009)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'restore' => 'This receipt cannot be restored: a refund was issued when it was '
                               . 'voided, so the money is no longer with the clinic. Record a new '
                               . 'payment instead — restoring would re-recognise revenue that does '
                               . 'not exist.',
                ]);
            }

            // ── Find the original allocation rows. Never invent them. ────────
            // Invoice-linked receipts point at one payment; a consolidated PAY-
            // receipt owns N payments through invoice_payments.receipt_id.
            $payments = InvoicePayment::withTrashed()
                ->where(function ($q) use ($receipt) {
                    $q->where('receipt_id', $receipt->id);
                    if ($receipt->invoice_payment_id) {
                        $q->orWhere('id', $receipt->invoice_payment_id);
                    }
                })
                ->get();

            if ($payments->isEmpty() && (float) $receipt->amount > 0.009) {
                Log::error('Receipt restore blocked — no payment rows found', [
                    'receipt_id'     => $receipt->id,
                    'receipt_number' => $receipt->receipt_number,
                    'amount'         => (float) $receipt->amount,
                ]);

                throw ValidationException::withMessages([
                    'restore' => 'This receipt cannot be restored automatically: the payment record '
                               . 'it belonged to could not be found, so there is nothing to '
                               . 'reinstate. The problem has been logged. Record a fresh payment '
                               . 'instead — nothing will be reconstructed from guesswork.',
                ]);
            }

            $invoiceNumbers = [];
            $restoredCount  = 0;

            foreach ($payments as $payment) {
                // Its income row must exist. We reactivate it; we never create one.
                $income = FinanceTransaction::where('source_type', InvoicePayment::class)
                    ->where('source_id', $payment->id)
                    ->whereIn('status', ['voided', 'active'])
                    ->get();

                if ($income->isEmpty()) {
                    Log::error('Receipt restore blocked — payment has no finance transaction', [
                        'receipt_id' => $receipt->id,
                        'payment_id' => $payment->id,
                    ]);

                    throw ValidationException::withMessages([
                        'restore' => 'This receipt cannot be restored: the finance entry for its '
                                   . 'payment is missing, so the books cannot be put back exactly '
                                   . 'as they were. The problem has been logged.',
                    ]);
                }

                FinanceTransaction::whereIn('id', $income->pluck('id'))
                    ->where('status', 'voided')
                    ->update(['status' => 'active']);

                if ($payment->trashed()) {
                    $payment->restore();
                    $restoredCount++;
                }

                // Clear the void audit — this payment is live again.
                // void_charge_deducted is NOT NULL with default 0 (see
                // 2026_06_13_200001), so it resets to 0, not null. The others are
                // nullable, and null is the honest value: no void is in force.
                $payment->update([
                    'void_reason'          => null,
                    'voided_by'            => null,
                    'void_refund_method'   => null,
                    'void_refund_amount'   => null,
                    'void_charge_deducted' => 0,
                ]);

                $invoice = Invoice::whereKey($payment->invoice_id)->lockForUpdate()->first();
                if ($invoice) {
                    $invoice->refresh();
                    $invoice->recalculate();
                    $invoice->refresh();

                    // Put the Final Bill back rather than minting a second one.
                    if ($invoice->isFullyPaid()) {
                        $trashedBill = FinalBill::onlyTrashed()->where('invoice_id', $invoice->id)->first();
                        if ($trashedBill) {
                            $trashedBill->restore();
                            $trashedBill->update(['deleted_reason' => null, 'deleted_by' => null]);
                        } elseif (! $invoice->hasFinalBill() && $userId !== null) {
                            FinalBill::generateFromInvoice($invoice, $userId);
                        }
                    }

                    $invoiceNumbers[] = $invoice->invoice_number;
                }
            }

            // ── The compensating refund row is itself now void. Mark, don't delete.
            if ($compensating->isNotEmpty()) {
                FinanceTransaction::whereIn('id', $compensating->pluck('id'))
                    ->update(['status' => 'voided']);
            }

            // ── Restore the receipt and clear any A2 correction marking.
            $receipt->restore();
            $receipt->update([
                'voided_at'            => null,
                'void_reason'          => null,
                'voided_by'            => null,
                'void_correction_type' => null,
            ]);

            if ($userId !== null) {
                BillingAuditLog::record(
                    'restore_receipt',
                    $receipt,
                    'Receipt restored from trash — payment, finance entry and invoice balance '
                        . 'reinstated to their pre-void state.',
                    $userId,
                    $receipt->receipt_number
                );
            }

            return [
                'restored'          => true,
                'already_active'    => false,
                'receipt'           => $receipt->fresh(),
                'payments_restored' => $restoredCount,
                'amount'            => (float) $receipt->amount,
                'invoices'          => $invoiceNumbers,
            ];
        });
    }
}
