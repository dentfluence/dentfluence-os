<?php

namespace App\Observers\Notifications;

use App\Models\Consultation;
use App\Services\Notifications\ChairsideNotifier;

/**
 * ConsultationNotificationObserver — N-1 (2026-09-09).
 *
 * Model-event choke point, same pattern as ConsultationClinicalWiringObserver:
 * all ten consultation write paths (5 web + 5 API — standard, minor visit,
 * same issue, specialty, standalone) reach `created` here, so the front desk
 * is told about EVERY saved consultation without touching a controller.
 *
 * Measured 9 Sep: before this, a full consultation save produced no billing
 * prompt and no notification at all — only Minor Visit with charges did.
 */
class ConsultationNotificationObserver
{
    public function __construct(private readonly ChairsideNotifier $notifier)
    {
    }

    public function created(Consultation $consultation): void
    {
        $this->notifier->consultationSaved($consultation);
    }
}
