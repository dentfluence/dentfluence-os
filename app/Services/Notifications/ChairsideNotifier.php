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
        // load(), NOT loadMissing(): on an edit the service hands us the same
        // model it has been holding through the save, whose visitItems were
        // read BEFORE the new items were written. loadMissing() would keep
        // that stale, empty collection and the desk would be told about work
        // that is already recorded but invisible here.
        $visit->load(['patient', 'doctor', 'visitItems', 'billingPrompts']);
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

        // N-10 (CEO ruling, 2026-09-10): reception reads THREE things and
        // nothing else — how much to collect, what to do next, when to book.
        // A note only if the doctor wrote one. Everything else is context and
        // belongs on the patient's page, not on a card read standing up.
        $h = (array) ($visit->handover ?? []);

        $collect = isset($h['collect_amount']) && (int) $h['collect_amount'] > 0
            ? (int) $h['collect_amount']
            : null;

        $actions = [];
        if (! empty($h['xray'])) {
            $actions[] = 'X-ray';
        }
        if (! empty($h['offer_aocp'])) {
            $actions[] = 'Offer AOCP';
        }

        // A date the doctor SET beats a "book in N days" instruction — one is
        // a decision, the other a rule of thumb.
        $appointment = $visit->next_visit_date
            ?: (! empty($h['book_in_days']) ? now()->addDays((int) $h['book_in_days']) : null);

        $note = ! empty($h['note']) ? trim($h['note']) : null;

        // 'Dr. Dr. Anushka Ayare' — the stored name often already carries the
        // title, so strip it before adding one.
        $doctor = $visit->doctor
            ? 'Dr. ' . preg_replace('/^\s*Dr\.?\s+/i', '', $visit->doctor->name)
            : null;

        $payload = [
            'collect'     => $collect,
            'action'      => $actions ? implode(' · ', $actions) : null,
            'appointment' => $appointment ? $appointment->format('d M (D)') : null,
            'note'        => $note,
            'doctor'      => $doctor,
        ];

        // The one-line fallback the bell list, the phone and a push body read.
        // Same three facts, same order, no paragraph.
        $parts = [];
        if ($collect) {
            $parts[] = 'Collect ₹' . number_format($collect);
        }
        if ($payload['action']) {
            $parts[] = $payload['action'];
        }
        if ($payload['appointment']) {
            $parts[] = 'Book ' . $payload['appointment'];
        }
        if ($note) {
            $parts[] = $note;
        }

        // The pending billing prompt this visit produced IS the desk's next
        // click — land them on it, not on the patient's front page.
        $prompt = $visit->billingPrompts->firstWhere('status', 'pending');

        return $this->dispatcher->fireOrRefresh('visit.saved', [
            'title'        => $patient->name . ' — ' . ($work ?: 'treatment visit done'),
            'message'      => implode(' · ', $parts) ?: null,
            'payload'      => $payload,
            // Nothing for the desk to DO → no interruption; it waits in the
            // bell. This is the guard against the empty cards of 10 Sep.
            'max_level'    => $parts ? null : NotificationCatalog::LEVEL_BELL,
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
