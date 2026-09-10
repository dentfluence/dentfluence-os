<?php

namespace App\Services\Notifications;

use App\Models\Consultation;
use App\Models\TreatmentVisit;
use App\Support\Handover;
use Illuminate\Support\Str;

/**
 * ChairsideNotifier — turns the two chairside facts the front desk must hear
 * about RIGHT NOW into notifications: a consultation was saved, a treatment
 * visit was saved. The patient is walking from the chair to the desk while
 * this fires.
 *
 * Text lives here, not in the dispatcher (which is deliberately blind to
 * modules) and not in the controllers (there are ten consultation write paths).
 *
 * The doctor's handover (N-2: Collect ₹ · Offer AOCP · X-ray · Book in N
 * days · note) is the FIRST thing in the message — it is the instruction;
 * everything after it is context.
 */
class ChairsideNotifier
{
    public function __construct(private readonly NotificationDispatcher $dispatcher)
    {
    }

    public function consultationSaved(Consultation $consultation): int
    {
        $consultation->loadMissing(['patient', 'doctor']);
        $patient = $consultation->patient;
        if (! $patient) {
            return 0;
        }

        $parts = [];
        if ($handover = Handover::summary($consultation->handover)) {
            $parts[] = $handover;
        }
        if ($consultation->chief_complaint) {
            $parts[] = Str::limit(trim((string) $consultation->chief_complaint), 80);
        }
        if ($consultation->next_visit_date) {
            $parts[] = 'Next visit ' . $consultation->next_visit_date->format('d M');
        }
        if ($consultation->doctor) {
            $parts[] = 'Dr. ' . $consultation->doctor->name;
        }

        return $this->dispatcher->fire('consultation.saved', [
            'title'        => $patient->name . ' — consultation done',
            'message'      => implode(' · ', $parts) ?: null,
            'action_url'   => route('patients.show', $patient),
            'action_label' => 'Open patient',
            'source'       => $consultation,
            'branch_id'    => $consultation->branch_id,
            'owner'        => $consultation->doctor_id,
        ]);
    }

    public function visitSaved(TreatmentVisit $visit): int
    {
        $visit->loadMissing(['patient', 'doctor', 'visitItems', 'billingPrompts']);
        $patient = $visit->patient;
        if (! $patient) {
            return 0;
        }

        $items = $visit->visitItems;

        $work = $items->map(function ($i) {
            $label = $i->treatment_name;
            if ($i->tooth_number) {
                $label .= ' (' . $i->tooth_number . ')';
            }

            return $label;
        })->filter()->take(3)->join(', ');
        if ($items->count() > 3) {
            $work .= ' +' . ($items->count() - 3);
        }

        $parts = [];
        if ($handover = Handover::summary($visit->handover)) {
            $parts[] = $handover;
        }
        $suggested = (float) $items->sum('suggested_price');
        if ($suggested > 0) {
            $parts[] = 'Bill ₹' . number_format($suggested, 0);
        }
        if ($visit->next_visit_date) {
            $parts[] = 'Next visit ' . $visit->next_visit_date->format('d M');
        }
        if ($visit->doctor) {
            $parts[] = 'Dr. ' . $visit->doctor->name;
        }

        // The pending billing prompt this visit produced IS the desk's next
        // click — land them on it, not on the patient's front page.
        $prompt = $visit->billingPrompts->firstWhere('status', 'pending');

        return $this->dispatcher->fire('visit.saved', [
            'title'        => $patient->name . ' — ' . ($work ?: 'treatment visit done'),
            'message'      => implode(' · ', $parts) ?: null,
            'action_url'   => $prompt
                ? route('billing.createFromPrompt', [$patient, $prompt])
                : route('patients.show', $patient),
            'action_label' => $prompt ? 'Build invoice' : 'Open patient',
            'source'       => $visit,
            'branch_id'    => $visit->branch_id ?? $patient->branch_id,
            'owner'        => $visit->doctor_id,
        ]);
    }
}
