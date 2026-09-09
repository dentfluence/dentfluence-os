<?php

namespace App\Observers\Notifications;

use App\Models\LabCase;
use App\Services\Notifications\NotificationDispatcher;

/**
 * LabCaseNotificationObserver — N-1 (2026-09-09).
 *
 * lab.draft_created → front desk + assistant (bell). A doctor who ticks the
 * lab section on the Visit Log creates a DRAFT case; until someone places the
 * order with the vendor nothing has left the building. The sidebar badge
 * already counts drafts (UX-07) — this row names the patient so the person
 * who sends lab work sees WHICH one, on the phone too.
 *
 * Status transitions (trial/final received, overdue, rejected) stay with
 * LabNotificationService, which already fires them and also messages the
 * patient on WhatsApp; migrating those onto the dispatcher is N-4 work.
 */
class LabCaseNotificationObserver
{
    public function __construct(private readonly NotificationDispatcher $dispatcher)
    {
    }

    public function created(LabCase $case): void
    {
        if ($case->status !== 'draft') {
            return;
        }

        $case->loadMissing(['patient', 'doctor']);

        $what = trim(implode(' ', array_filter([$case->work_category, $case->work_subtype])));

        $this->dispatcher->fire('lab.draft_created', [
            'title'        => 'Lab case to send — ' . ($case->patient?->name ?? 'Patient'),
            'message'      => trim(($what !== '' ? $what . ' · ' : '') . ($case->case_number ?? '')
                . ($case->doctor ? ' · Dr. ' . $case->doctor->name : '')),
            'action_url'   => route('lab.show', $case),
            'action_label' => 'Open lab case',
            'source'       => $case,
            'branch_id'    => $case->branch_id,
            'owner'        => $case->doctor_id,
        ]);
    }
}
