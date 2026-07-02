<?php

namespace App\Observers;

use App\Models\CommunicationQueue;
use App\Models\CommActivityLog;
use App\Models\LabCase;

/**
 * LabCaseObserver — Phase 4 Communication OS
 *
 * Keeps B2B communications in sync with lab case status changes.
 *
 * On status change:
 *   1. Log a status update entry in the linked comm's activity log
 *   2. Auto-close linked comm when case reaches received / delivered / closed
 *
 * "Linked comm" = any comm where lab_case_id = this case's ID.
 * A new auto-comm is created on first status change if none exists yet.
 */
class LabCaseObserver
{
    /**
     * Called after any save (create or update).
     * We only care when status has changed.
     */
    public function updated(LabCase $case): void
    {
        if (!$case->wasChanged('status')) {
            return;
        }

        $oldStatus = $case->getOriginal('status');
        $newStatus = $case->status;

        $statusLabel    = LabCase::STATUS_LABELS[$newStatus]    ?? $newStatus;
        $oldStatusLabel = LabCase::STATUS_LABELS[$oldStatus]    ?? $oldStatus;

        // ── 1. Find or create a B2B comm for this lab case ───────────────
        $comm = CommunicationQueue::where('lab_case_id', $case->id)
            ->where('status', '!=', 'closed')
            ->latest()
            ->first();

        if (!$comm) {
            // Auto-create a tracking comm for this case if none exists
            $comm = $this->createAutoComm($case);
        }

        // ── 2. Log the status change ──────────────────────────────────────
        CommActivityLog::log(
            $comm->id,
            'lab_status_change',
            "Lab case status: {$oldStatusLabel} → {$statusLabel}",
            [
                'lab_case_id' => $case->id,
                'old_status'  => $oldStatus,
                'new_status'  => $newStatus,
            ]
        );

        // Also update the comm's response_notes with the latest status
        $comm->response_notes = "Lab case #{$case->case_number} — {$statusLabel} as of " . now()->format('d M Y H:i');
        $comm->save();

        // ── 3. Auto-close when lab case is done ───────────────────────────
        // Lab Module v2 terminal statuses (old values received/delivered/closed
        // no longer exist, so the comm never auto-closed before this fix).
        $closingStatuses = ['final_received', 'complete', 'rejected'];

        if (in_array($newStatus, $closingStatuses)) {
            $comm->autoClose(
                'case_received',
                "Lab case #{$case->case_number} status: {$statusLabel}"
            );
        }
    }

    /**
     * Auto-create a B2B comm when a lab case status changes and no open comm exists.
     * This handles cases that were in-flight before Phase 4 was deployed.
     */
    private function createAutoComm(LabCase $case): CommunicationQueue
    {
        $vendor = $case->labVendor;

        $comm = CommunicationQueue::create([
            'person_name'    => $vendor ? $vendor->name : "Lab Case #{$case->case_number}",
            'phone'          => $vendor?->phone ?? '',
            'channel'        => 'other',
            'contact_type'   => 'lab',
            'contact_id'     => $case->lab_vendor_id,
            'b2b_subtype'    => 'lab_case_status',
            'lab_case_id'    => $case->id,
            'source_engine'  => 'b2b',
            'status'         => 'waiting_for_patient',
            'priority'       => $case->priority === 'urgent' ? 'high' : 'medium',
            'note'           => "Auto-created by system — tracking Lab Case #{$case->case_number}",
            'sla_deadline'   => now()->addHours(4),
        ]);

        CommActivityLog::log(
            $comm->id,
            'auto_created',
            "Comm auto-created for Lab Case #{$case->case_number}",
            ['lab_case_id' => $case->id]
        );

        return $comm;
    }
}
