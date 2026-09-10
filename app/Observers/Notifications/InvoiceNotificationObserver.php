<?php

namespace App\Observers\Notifications;

use App\Models\Invoice;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Support\Facades\DB;

/**
 * InvoiceNotificationObserver — N-1 (2026-09-09).
 *
 * Two facts about an invoice matter to people who did not write it:
 *
 *  invoice.created   → the doctor whose work it bills (owner, bell).
 *     NOT fired on `created`: every writer (web, API, plan billing,
 *     membership) inserts the header with total 0 and only recalculate()
 *     after the lines are saved gives it a rupee figure. So we fire on the
 *     first `updated` that moves total_amount off zero. The dispatcher's
 *     dedupe key makes any later recalculate a no-op for this event.
 *
 *  invoice.cancelled → admin, POPUP. A cancel is destructive money movement
 *     (W-1 found cancel() with zero guards). The owner sees it the moment it
 *     happens, on every device, not on next month's report.
 *
 * 🪤 Helper names: Laravel binds EVERY observer method named after a model
 * event (created/updated/deleted…) regardless of visibility, so a private
 * created() here was hooked to Invoice::created and blew up as an illegal
 * private call. Helpers are announce*() for that reason — never name one
 * after an event.
 *
 * Owner = invoices.created_by is the biller, not the dentist. The doctor is
 * found through the visit items linked to this invoice's lines (W-8's
 * treatment_visit_items.invoice_item_id link); with no link, no owner and
 * the row is simply not written — never guessed.
 */
class InvoiceNotificationObserver
{
    public function __construct(private readonly NotificationDispatcher $dispatcher)
    {
    }

    public function updated(Invoice $invoice): void
    {
        if ($invoice->wasChanged('status') && $invoice->status === 'cancelled') {
            $this->announceCancelled($invoice);

            return;
        }

        if ($invoice->wasChanged('total_amount')
            && (float) $invoice->getOriginal('total_amount') <= 0
            && (float) $invoice->total_amount > 0
            && $invoice->status !== 'cancelled') {
            $this->announceCreated($invoice);
        }
    }

    private function announceCreated(Invoice $invoice): void
    {
        $invoice->loadMissing('patient');
        $doctorId = $this->doctorOf($invoice);
        if (! $doctorId) {
            return; // owner-only event; nobody to tell
        }

        $this->dispatcher->fire('invoice.created', [
            'title'        => ($invoice->patient?->name ?? 'Patient') . ' billed ₹' . number_format((float) $invoice->total_amount, 0),
            'message'      => $invoice->invoice_number,
            'action_url'   => route('billing.show', $invoice),
            'action_label' => 'View invoice',
            'source'       => $invoice,
            'branch_id'    => $invoice->patient?->branch_id,
            'owner'        => $doctorId,
        ]);
    }

    private function announceCancelled(Invoice $invoice): void
    {
        $invoice->loadMissing('patient');

        $reason = trim((string) $invoice->cancelled_reason);

        $this->dispatcher->fire('invoice.cancelled', [
            'title'        => 'Invoice cancelled — ' . ($invoice->patient?->name ?? 'Patient') . ' ₹' . number_format((float) $invoice->total_amount, 0),
            'message'      => $invoice->invoice_number . ($reason !== '' ? ' · ' . $reason : ''),
            'action_url'   => route('billing.show', $invoice),
            'action_label' => 'View invoice',
            'source'       => $invoice,
            'branch_id'    => $invoice->patient?->branch_id,
        ]);
    }

    /** The dentist behind the billed lines, via treatment_visit_items → treatment_visits.doctor_id. */
    private function doctorOf(Invoice $invoice): ?int
    {
        $id = DB::table('treatment_visit_items as tvi')
            ->join('invoice_items as ii', 'ii.id', '=', 'tvi.invoice_item_id')
            ->join('treatment_visits as tv', 'tv.id', '=', 'tvi.treatment_visit_id')
            ->where('ii.invoice_id', $invoice->id)
            ->whereNotNull('tv.doctor_id')
            ->orderBy('tv.doctor_id')
            ->value('tv.doctor_id');

        return $id ? (int) $id : null;
    }
}
