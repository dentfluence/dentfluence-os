<?php

namespace App\Services\Billing;

use App\Models\BillingAuditLog;
use App\Models\EmiSchedule;
use App\Models\InvoicePayment;
use Illuminate\Support\Collection;

/**
 * EmiScheduleService
 * ------------------
 * Read + receivables-tracking for a Direct-EMI instalment schedule.
 *
 * IMPORTANT — this is pure follow-up bookkeeping, NOT a payment-recording
 * path. When a Direct EMI payment is first recorded
 * (InvoicePaymentService::recordPayment), the FULL principal is already
 * booked as invoice.paid_amount and mirrored once to FinanceTransaction as
 * revenue — "direct" EMI means the clinic itself finances the patient and
 * collects instalments outside the software (cash/UPI month to month), not
 * that the software collects them.
 *
 * Marking an instalment "paid" here must therefore NEVER touch invoice
 * totals, never call Invoice::recalculate(), and never write a new
 * FinanceTransaction — doing so would double-count revenue that was already
 * recognised in full at initial recording. This service only flips the
 * EmiSchedule row's own status/paid_date/reference so staff can track which
 * instalments the patient has actually paid the clinic back.
 */
class EmiScheduleService
{
    public function listForPayment(InvoicePayment $payment): Collection
    {
        return EmiSchedule::where('invoice_payment_id', $payment->id)
            ->orderBy('instalment_no')
            ->get();
    }

    /**
     * @throws \RuntimeException if the instalment is already marked paid.
     */
    public function markPaid(EmiSchedule $row, string $paidDate, ?string $reference, ?string $notes, int $userId): EmiSchedule
    {
        if ($row->status === 'paid') {
            throw new \RuntimeException('Instalment #' . $row->instalment_no . ' is already marked paid.');
        }

        $row->update([
            'status'            => 'paid',
            'paid_date'         => $paidDate,
            'payment_reference' => $reference,
            'notes'             => $notes ?? $row->notes,
        ]);

        BillingAuditLog::record(
            'emi_installment_paid',
            $row,
            'Instalment #' . $row->instalment_no . ' (Rs. ' . number_format((float) $row->emi_amount, 2) . ') marked collected.',
            $userId,
            'EMI #' . $row->instalment_no . ' · Invoice ' . ($row->invoice?->invoice_number ?? $row->invoice_id)
        );

        return $row->fresh();
    }
}
