<?php

namespace App\Observers\Notifications;

use App\Enums\PaymentMode;
use App\Models\InvoicePayment;
use App\Services\Notifications\NotificationDispatcher;

/**
 * InvoicePaymentNotificationObserver — N-1 (2026-09-09).
 *
 * payment.received → admin (bell + push by default). Five writers create
 * InvoicePayment rows (web, service, allocation, wallet, backfill command);
 * the model event covers all of them. The backfill command runs with no
 * authenticated user and writes historical rows — those are excluded by
 * date so a one-off migration cannot flood the owner's phone.
 */
class InvoicePaymentNotificationObserver
{
    public function __construct(private readonly NotificationDispatcher $dispatcher)
    {
    }

    public function created(InvoicePayment $payment): void
    {
        // Historical / backfilled rows: not "received" now, not announced.
        $paidAt = $payment->payment_date ?? $payment->created_at;
        if ($paidAt && $paidAt->lt(now()->subDay())) {
            return;
        }

        $payment->loadMissing(['invoice.patient', 'patient']);
        $patient = $payment->patient ?? $payment->invoice?->patient;

        $mode = PaymentMode::labelFor($payment->payment_mode);

        $this->dispatcher->fire('payment.received', [
            'title'        => '₹' . number_format((float) $payment->amount, 0) . ' received — ' . ($patient?->name ?? 'Patient'),
            'message'      => trim($mode . ($payment->invoice ? ' · ' . $payment->invoice->invoice_number : '')),
            'action_url'   => $payment->invoice ? route('billing.show', $payment->invoice) : null,
            'action_label' => $payment->invoice ? 'View invoice' : null,
            'source'       => $payment,
            'branch_id'    => $patient?->branch_id,
        ]);
    }
}
