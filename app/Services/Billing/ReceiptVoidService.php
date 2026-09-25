<?php

namespace App\Services\Billing;

use App\Models\BillingAuditLog;
use App\Models\Finance\FinanceTransaction;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Receipt;
use App\Services\Inventory\RetailStockReversal;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * ReceiptVoidService — the ONE way money leaves an invoice
 * (INT-01 / INT-02 / INT-03, security audit 24 Sep 2026).
 *
 * Before this there were four paths — web void receipt, web cancel invoice,
 * mobile void/cancel, and a legacy "Cancel" button — and they disagreed:
 *
 *   INT-01  "Refund to wallet" credited the wallet as CLINIC-funded, i.e.
 *           promotional credit that can never be withdrawn. It was the
 *           patient's own money. Now WalletService::refund() (patient-funded).
 *   INT-02  Only web void knew that a WALLET-TENDER receipt is the patient's
 *           own credit and must be restored as credit whatever refund method
 *           is picked. Cancel (web and mobile) and mobile void paid it out as
 *           cash or wrote it off. Now every path goes through reverseLeg().
 *   INT-03  The legacy Cancel button reversed nothing but stock. It is gone;
 *           cancel always runs cancelInvoice() below.
 *
 * Refund methods: wallet | cash | bank_transfer | no_refund.
 * bank_transfer on a card receipt keeps a 2.5% clinic charge.
 */
class ReceiptVoidService
{
    public const METHODS = ['wallet', 'cash', 'bank_transfer', 'no_refund'];

    public static function chargeRate(?string $paymentMode): float
    {
        return in_array($paymentMode, ['card', 'debit_card'], true) ? 2.5 : 0.0;
    }

    /** Void one receipt and bring the invoice's totals and Final Bill back in line. */
    public function voidReceipt(Invoice $invoice, Receipt $receipt, string $method, string $reason, int $userId): array
    {
        return DB::transaction(function () use ($invoice, $receipt, $method, $reason, $userId) {
            // INT-08 — serialise with payments and other voids on this invoice,
            // and refuse a second void of the same receipt (double click).
            Invoice::whereKey($invoice->id)->lockForUpdate()->first();
            $live = Receipt::withTrashed()->whereKey($receipt->id)->lockForUpdate()->first();
            if (! $live || $live->trashed()) {
                throw ValidationException::withMessages(['receipt' => 'This receipt is already voided.']);
            }

            BillingAuditLog::record('void_receipt', $receipt, $reason . ' [refund: ' . $method . ']', $userId, $receipt->receipt_number);

            $result = $this->reverseLeg($invoice, $receipt, $method, $reason,
                'Voided receipt ' . $receipt->receipt_number . '. Reason: ' . $reason, $userId);

            $invoice->refresh();
            $invoice->recalculate();
            $invoice->refresh();

            if ($invoice->status !== 'paid' && $invoice->finalBill) {
                $invoice->finalBill->update([
                    'deleted_reason' => 'Auto-invalidated: linked receipt ' . $receipt->receipt_number . ' was voided. Reason: ' . $reason,
                    'deleted_by'     => $userId,
                ]);
                $invoice->finalBill->delete();
            }

            return $result;
        });
    }

    /**
     * Cancel an invoice: every active receipt reversed, Final Bill removed,
     * retail stock and promotional wallet debits given back, plan teeth
     * released, invoice marked cancelled and moved to Trash.
     */
    public function cancelInvoice(Invoice $invoice, string $method, string $reason, int $userId, string $auditAction = 'cancel_invoice'): void
    {
        DB::transaction(function () use ($invoice, $method, $reason, $userId, $auditAction) {
            // INT-08 — lock, and refuse to cancel twice (double click / two tabs).
            $live = Invoice::withTrashed()->whereKey($invoice->id)->lockForUpdate()->first();
            if (! $live || $live->trashed()) {
                throw ValidationException::withMessages(['invoice' => 'This invoice is already cancelled.']);
            }

            BillingAuditLog::record($auditAction, $invoice, $reason . ' [refund: ' . $method . ']', $userId, $invoice->invoice_number);

            $notes = 'Invoice ' . $invoice->invoice_number . ' cancelled. Reason: ' . $reason;
            foreach ($invoice->receipts()->whereNull('deleted_at')->get() as $receipt) {
                $this->reverseLeg($invoice, $receipt, $method, $reason, $notes, $userId);
            }

            if ($invoice->finalBill) {
                $invoice->finalBill->update(['deleted_reason' => $notes, 'deleted_by' => $userId]);
                $invoice->finalBill->delete();
            }

            RetailStockReversal::forInvoice($invoice, 'cancelled');
            (new WalletService())->reverseInvoiceDebit($invoice, 'invoice ' . $invoice->invoice_number . ' cancelled. ' . $reason, $userId);
            app(PlanBillingRollbackService::class)->rollbackInvoice($invoice);

            $invoice->update([
                'status'           => 'cancelled',
                'cancelled_reason' => $reason,
                'cancelled_by'     => $userId,
            ]);
            $invoice->delete(); // soft delete → Trash
        });
    }

    /**
     * Reverse one receipt: payment + its income voided, receipt soft-deleted,
     * and the refund recorded. Does NOT recalculate the invoice.
     *
     * @return array{method:string, amount:float, refund:float, charge:float}
     */
    private function reverseLeg(Invoice $invoice, Receipt $receipt, string $method, string $reason, string $notes, int $userId): array
    {
        $amount    = (float) $receipt->amount;
        $patientId = $invoice->patient_id;

        // U8 — a wallet-tender leg was paid with the patient's own credit. The
        // clinic never received cash for it, so it can only go back as credit.
        $isWalletTender = $receipt->payment_mode === 'wallet';
        $effective      = $isWalletTender ? 'wallet' : $method;

        $charge = $effective === 'bank_transfer' ? round($amount * self::chargeRate($receipt->payment_mode) / 100, 2) : 0.0;
        $refund = $effective === 'no_refund' ? 0.0 : $amount - $charge;

        if ($receipt->invoice_payment_id && ($payment = InvoicePayment::find($receipt->invoice_payment_id))) {
            $payment->update([
                'void_reason'          => $reason,
                'voided_by'            => $userId,
                'void_refund_method'   => $effective,
                'void_refund_amount'   => $refund,
                'void_charge_deducted' => $charge,
            ]);
            FinanceTransaction::where('source_type', InvoicePayment::class)
                ->where('source_id', $payment->id)
                ->where('status', 'active')
                ->update(['status' => 'voided']);
            $payment->delete();
        }

        $receipt->delete();

        if ($isWalletTender) {
            (new WalletService())->restorePatientCredit($patientId, $amount, $notes, $userId, $invoice->id);
            $label = 'Revenue reversed, patient credit restored';
        } elseif ($effective === 'wallet') {
            // INT-01 — patient money coming back: patient-funded, withdrawable.
            (new WalletService())->refund($patientId, $amount, $invoice->id, 'Wallet credit — ' . $notes, $userId);
            $label = 'Wallet credit';
        } else {
            $label = match ($effective) {
                'no_refund'     => 'No refund issued',
                'cash'          => 'Cash refund',
                'bank_transfer' => $charge > 0
                    ? 'Bank transfer refund (₹' . number_format($refund, 2) . ' to patient, ₹' . number_format($charge, 2) . ' clinic charge)'
                    : 'Bank transfer refund (no charge)',
                default         => ucfirst($effective) . ' refund',
            };
        }

        FinanceTransaction::create([
            'type'             => 'refund',
            'direction'        => 'debit',
            'source_type'      => Receipt::class,
            'source_id'        => $receipt->id,
            'amount'           => $amount,
            'net_amount'       => $refund,
            'payment_mode'     => $effective === 'cash' ? 'cash' : $receipt->payment_mode,
            'patient_id'       => $patientId,
            'status'           => 'active',
            'transaction_date' => now()->toDateString(),
            'notes'            => $label . ' — ' . $notes,
            'created_by'       => $userId,
        ]);

        return ['method' => $effective, 'amount' => $amount, 'refund' => $refund, 'charge' => $charge];
    }
}
