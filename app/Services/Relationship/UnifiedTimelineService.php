<?php

namespace App\Services\Relationship;

use App\Models\Activity;
use App\Models\Appointment;
use App\Models\ClinicalFile;
use App\Models\ConsentLog;
use App\Models\Consultation;
use App\Models\Finance\FinancePatientMembership;
use App\Models\Finance\MembershipBenefitLog;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\LabCaseEvent;
use App\Models\LeadActivity;
use App\Models\Patient;
use App\Models\PatientNote;
use App\Models\Relationship;
use App\Models\Review;
use App\Models\Task;
use App\Models\TreatmentPlan;
use App\Models\TreatmentVisit;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UnifiedTimelineService — Phase 1 · Sprint 2/3 (Workstream B).
 *
 * Assembles a person's complete history into ONE chronological timeline by
 * merging the Activity ledger with the legacy activity/communication sources.
 *
 * This is a FAITHFUL MIRROR of ProfileController::buildTimeline — identical
 * sources, per-source limits, ordering, final cap, and entry formatting — so
 * the Sprint 3 cutover (profile reads via this service behind the
 * `activity.single_ledger_reads` flag) is invisible to the user and provably
 * at parity (see relationship:timeline-parity).
 *
 * Read-only. Never throws — each source is guarded, so one bad source can
 * never blank the timeline.
 *
 * Entry shape (identical to the legacy builder):
 *   ['date' => Carbon, 'type' => string, 'icon_type' => string,
 *    'title' => string, 'description' => ?string, 'actor' => ?string, 'meta' => ?string]
 */
class UnifiedTimelineService
{
    /** @return Collection<int, array<string,mixed>> newest-first, capped at 100. */
    public function for(Relationship $relationship, int $limit = 100): Collection
    {
        $entries = collect();

        // Use the SAME lead/patient selection as the profile (hasOne relations),
        // so household relationships (multiple patients) resolve identically.
        $lead    = $relationship->lead;
        $patient = $relationship->patient;

        $this->addActivities($entries, $relationship);
        if ($lead) {
            $this->addLeadActivities($entries, $lead);
        }
        if ($patient) {
            $this->addAppointments($entries, $patient);
            $this->addPatientCommunications($entries, $patient);
            $this->addTasks($entries, $patient);
            $this->addNotes($entries, $patient);
        }

        return $entries
            ->filter(fn ($e) => $e['date'] instanceof Carbon)
            ->sortByDesc('date')
            ->values()
            ->take($limit);
    }

    private function addActivities(Collection $entries, Relationship $relationship): void
    {
        $this->guard(function () use ($entries, $relationship) {
            Activity::where('relationship_id', $relationship->id)
                ->orderBy('occurred_at', 'desc')->limit(60)->get()
                ->each(function ($act) use ($entries) {
                    $entries->push([
                        'date'        => $act->occurred_at,
                        'type'        => 'activity',
                        'icon_type'   => $this->iconForEvent((string) $act->event),
                        'title'       => $act->description ?: ucfirst(str_replace(['.', '_'], ' ', (string) $act->event)),
                        'description' => null,
                        'actor'       => $act->actor_type ? $this->resolveActorName($act) : 'System',
                        'meta'        => $act->metadata ? $this->formatMeta((array) $act->metadata) : null,
                    ]);
                });
        });
    }

    private function addLeadActivities(Collection $entries, $lead): void
    {
        $this->guard(function () use ($entries, $lead) {
            LeadActivity::where('lead_id', $lead->id)
                ->orderBy('activity_date', 'desc')->limit(30)->get()
                ->each(function ($la) use ($entries) {
                    $entries->push([
                        'date'        => $this->toCarbon($la->activity_date ?? $la->created_at),
                        'type'        => 'communication',
                        'icon_type'   => $la->type ?? 'call',
                        'title'       => $la->label ?? ucfirst((string) ($la->type ?? 'Activity')),
                        'description' => $la->note,
                        'actor'       => $la->by,
                        'meta'        => $la->outcome,
                    ]);
                });
        });
    }

    private function addAppointments(Collection $entries, $patient, ?Carbon $before = null): void
    {
        $this->guard(function () use ($entries, $patient, $before) {
            Appointment::where('patient_id', $patient->id)
                ->when($before, fn ($q) => $q->where('appointment_date', '<', $before))
                ->orderBy('appointment_date', 'desc')->limit(30)->get()
                ->each(function ($appt) use ($entries) {
                    $entries->push([
                        'date'        => $this->toCarbon($appt->appointment_date),
                        'type'        => 'appointment',
                        'icon_type'   => 'appointment',
                        'title'       => 'Appointment — ' . ucfirst((string) ($appt->type ?? 'Visit')),
                        'description' => $appt->notes ?? null,
                        'actor'       => $appt->doctor_id ? $this->userName($appt->doctor_id) : null,
                        'meta'        => ucfirst((string) ($appt->status ?? '')),
                    ]);
                });
        });
    }

    private function addPatientCommunications(Collection $entries, $patient, ?Carbon $before = null): void
    {
        $this->guard(function () use ($entries, $patient, $before) {
            if (! Schema::hasTable('patient_communications')) {
                return;
            }
            DB::table('patient_communications')
                ->where('patient_id', $patient->id)
                ->when($before, fn ($q) => $q->where('created_at', '<', $before))
                ->orderBy('created_at', 'desc')->limit(20)->get()
                ->each(function ($comm) use ($entries) {
                    $entries->push([
                        'date'        => $this->toCarbon($comm->sent_at ?? $comm->created_at),
                        'type'        => 'communication',
                        'icon_type'   => $comm->type ?? 'call',
                        'title'       => ucfirst((string) ($comm->type ?? 'Communication')) . ' — ' . ucfirst((string) ($comm->direction ?? '')),
                        'description' => $comm->message ?? null,
                        'actor'       => $comm->staff_name ?? null,
                        'meta'        => ucfirst((string) ($comm->status ?? '')),
                    ]);
                });
        });
    }

    private function addTasks(Collection $entries, $patient, ?Carbon $before = null): void
    {
        $this->guard(function () use ($entries, $patient, $before) {
            Task::where('patient_id', $patient->id)
                ->when($before, fn ($q) => $q->where('created_at', '<', $before))
                ->orderBy('created_at', 'desc')->limit(20)->get()
                ->each(function ($task) use ($entries) {
                    $entries->push([
                        'date'        => $this->toCarbon($task->due_date ?? $task->created_at),
                        'type'        => 'task',
                        'icon_type'   => 'task',
                        'title'       => $task->title ?? $task->task_title ?? 'Task',
                        'description' => $task->description ?? null,
                        'actor'       => null,
                        'meta'        => ucfirst((string) ($task->status ?? '')),
                    ]);
                });
        });
    }

    private function addNotes(Collection $entries, $patient, ?Carbon $before = null): void
    {
        $this->guard(function () use ($entries, $patient, $before) {
            PatientNote::where('patient_id', $patient->id)
                ->when($before, fn ($q) => $q->where('created_at', '<', $before))
                ->orderBy('created_at', 'desc')->limit(10)->get()
                ->each(function ($note) use ($entries) {
                    $entries->push([
                        'date'        => $this->toCarbon($note->created_at),
                        'type'        => 'note',
                        'icon_type'   => 'note',
                        'title'       => 'Note — ' . ucfirst((string) ($note->note_type ?? 'General')),
                        'description' => $note->note,
                        'actor'       => null,
                        'meta'        => null,
                    ]);
                });
        });
    }

    // ══════════════════════════════════════════════════════════════════════
    // CLINICAL SCOPE — Patients Module Phase 4 (Journey Timeline).
    //
    // forPatient() is the read side used by PatientJourneyService (the
    // canonical patient-history read model). It merges the ledger + the four
    // patient-scoped comms sources above with the clinical adapters below.
    //
    // The relationship-scoped for() above is UNTOUCHED — the PRE profile
    // timeline stays byte-identical (verified by relationship:timeline-parity).
    //
    // Clinical entries extend the base shape with:
    //   'group'      — filter bucket: clinical|financial|comms|consent|reviews|milestone
    //   'permission' — "module.action" the viewer needs to see the entry
    //   'link'       — ?string URL for "open the record" (null = not clickable)
    //   'color'      — UI accent key (blade maps it to classes)
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Full clinical journey for one patient, newest-first. Each source is
     * capped and guarded; $before enables cursor pagination ("load older").
     * Group/permission filtering and the final page cap belong to the caller
     * (PatientJourneyService) so filters never eat into the page size.
     *
     * @return Collection<int, array<string,mixed>>
     */
    public function forPatient(Patient $patient, ?Carbon $before = null): Collection
    {
        $entries = collect();

        // Ledger activities (payments.received, treatment_plan.accepted, recall.queued …)
        if ($patient->relationship_id) {
            $this->guard(function () use ($entries, $patient, $before) {
                Activity::where('relationship_id', $patient->relationship_id)
                    ->when($before, fn ($q) => $q->where('occurred_at', '<', $before))
                    ->orderBy('occurred_at', 'desc')->limit(40)->get()
                    ->each(function ($act) use ($entries) {
                        $entries->push([
                            'date'        => $act->occurred_at,
                            'type'        => 'activity',
                            'icon_type'   => $this->iconForEvent((string) $act->event),
                            'title'       => $act->description ?: ucfirst(str_replace(['.', '_'], ' ', (string) $act->event)),
                            'description' => null,
                            'actor'       => $act->actor_type ? $this->resolveActorName($act) : 'System',
                            'meta'        => $act->metadata ? $this->formatMeta((array) $act->metadata) : null,
                            'group'       => $this->groupForLedgerEvent((string) $act->event),
                            'permission'  => str_starts_with((string) $act->event, 'payment') ? 'billing.view' : 'patients.view',
                            'link'        => null,
                            'color'       => 'slate',
                        ]);
                    });
            });
        }

        // Patient-scoped comms sources shared with the relationship scope.
        $this->addAppointments($entries, $patient, $before);
        $this->addPatientCommunications($entries, $patient, $before);
        $this->addTasks($entries, $patient, $before);
        $this->addNotes($entries, $patient, $before);

        // Clinical adapters (Phase 4).
        $this->addPatientCreated($entries, $patient, $before);
        $this->addConsultations($entries, $patient, $before);
        $this->addTreatmentPlanEvents($entries, $patient, $before);
        $this->addTreatmentVisits($entries, $patient, $before);
        $this->addPrescriptionless($entries); // no-op hook, reserved
        $this->addInvoices($entries, $patient, $before);
        $this->addPayments($entries, $patient, $before);
        $this->addClinicalFiles($entries, $patient, $before);
        $this->addLabEvents($entries, $patient, $before);
        $this->addMemberships($entries, $patient, $before);
        $this->addReviews($entries, $patient, $before);
        $this->addConsentLogs($entries, $patient, $before);

        return $entries
            ->filter(fn ($e) => $e['date'] instanceof Carbon)
            ->when($before, fn ($c) => $c->filter(fn ($e) => $e['date']->lt($before)))
            ->map(fn ($e) => $e + $this->clinicalDefaults($e)) // fill group/permission/link/color on legacy entries
            ->sortByDesc('date')
            ->values();
    }

    // ── Clinical adapters ────────────────────────────────────────────────────

    private function addPatientCreated(Collection $entries, Patient $patient, ?Carbon $before): void
    {
        $this->guard(function () use ($entries, $patient, $before) {
            if ($before && ! $patient->created_at?->lt($before)) {
                return;
            }
            $entries->push([
                'date'        => $patient->created_at,
                'type'        => 'patient.created',
                'icon_type'   => 'patient',
                'title'       => 'Patient registered',
                'description' => $patient->patient_id ? 'TDC ' . $patient->patient_id : null,
                'actor'       => $patient->created_by ? $this->userName($patient->created_by) : null,
                'meta'        => null,
                'group'       => 'milestone',
                'permission'  => 'patients.view',
                'link'        => null,
                'color'       => 'slate',
            ]);
        });
    }

    private function addConsultations(Collection $entries, Patient $patient, ?Carbon $before): void
    {
        $this->guard(function () use ($entries, $patient, $before) {
            Consultation::where('patient_id', $patient->id)
                ->when($before, fn ($q) => $q->where('consultation_date', '<', $before))
                ->orderBy('consultation_date', 'desc')->limit(30)->get()
                ->each(function ($c) use ($entries, $patient) {
                    $isCoha = ($c->consultation_type ?? null) === 'coha';
                    $entries->push([
                        'date'        => $this->toCarbon($c->consultation_date ?? $c->created_at),
                        'type'        => $isCoha ? 'coha' : 'consultation',
                        'icon_type'   => 'consultation',
                        'title'       => $isCoha
                            ? 'COHA report'
                            : (($c->visit_type ? ucwords(str_replace('_', ' ', $c->visit_type)) . ' ' : '') . 'Consultation'),
                        'description' => $c->chief_complaint,
                        'actor'       => $c->doctor_id ? $this->userName($c->doctor_id) : null,
                        'meta'        => ucfirst((string) ($c->status ?? '')),
                        'group'       => 'clinical',
                        'permission'  => 'patients.view',
                        'link'        => route('patients.consultations.show', [$patient->id, $c->id]),
                        'color'       => 'teal',
                    ]);
                });
        });
    }

    private function addTreatmentPlanEvents(Collection $entries, Patient $patient, ?Carbon $before): void
    {
        $this->guard(function () use ($entries, $patient, $before) {
            $link = route('patients.show', $patient->id) . '#treatment-plan';

            TreatmentPlan::where('patient_id', $patient->id)
                ->with('decisions')   // Slice 2.3d — real patient decisions
                ->orderBy('plan_date', 'desc')->limit(30)->get()
                ->each(function ($plan) use ($entries, $before, $link) {
                    $name  = $plan->plan_name ?: 'Treatment plan';
                    $total = $plan->total ? ' · Rs. ' . number_format((float) $plan->total, 0) : '';

                    // Created
                    $created = $this->toCarbon($plan->plan_date ?? $plan->created_at);
                    if ($created && (! $before || $created->lt($before))) {
                        $entries->push([
                            'date' => $created, 'type' => 'treatment_plan', 'icon_type' => 'plan',
                            'title' => 'Treatment plan created — ' . $name, 'description' => trim($total, ' ·') ?: null,
                            'actor' => $plan->doctor_id ? $this->userName($plan->doctor_id) : ($plan->created_by ? $this->userName($plan->created_by) : null),
                            'meta' => ucfirst((string) $plan->status),
                            'group' => 'clinical', 'permission' => 'patients.view', 'link' => $link, 'color' => 'violet',
                        ]);
                    }

                    // Presented (Phase 2 · Slice 2.2) — a distinct clinical fact
                    // from "created". Historical plans have no presented_at and
                    // simply show no presentation entry; nothing is backfilled.
                    if ($plan->presented_at) {
                        $entries->push([
                            'date' => $this->toCarbon($plan->presented_at), 'type' => 'treatment.presented', 'icon_type' => 'plan',
                            'title' => 'Treatment plan presented to patient — ' . $name,
                            'description' => trim($total, ' ·') ?: null,
                            'actor' => $plan->doctor_id ? $this->userName($plan->doctor_id) : null,
                            'meta' => $plan->accepted_at ? null : 'Awaiting decision',
                            'group' => 'clinical', 'permission' => 'patients.view', 'link' => $link, 'color' => 'blue',
                        ]);
                    }

                    // Accepted (Amendment 1, event 18)
                    if ($plan->accepted_at) {
                        $entries->push([
                            'date' => $plan->accepted_at, 'type' => 'treatment.accepted', 'icon_type' => 'accepted',
                            'title' => 'Treatment plan accepted — ' . $name, 'description' => trim($total, ' ·') ?: null,
                            'actor' => $plan->doctor_id ? $this->userName($plan->doctor_id) : null, 'meta' => null,
                            'group' => 'clinical', 'permission' => 'patients.view', 'link' => $link, 'color' => 'green',
                        ]);
                    }

                    // ── Slice 2.3d — REAL patient decisions from the ledger.
                    // Every decision appears, so the timeline reads
                    // Presented → Deferred → later Accepted rather than only
                    // showing the latest state. Append-only means history is
                    // genuinely here to display.
                    foreach ($plan->decisions as $decision) {
                        if ($decision->decision === \App\Models\PlanDecision::ACCEPTED) {
                            continue;   // already rendered from accepted_at below
                        }

                        [$title, $color, $icon] = match ($decision->decision) {
                            \App\Models\PlanDecision::PARTIALLY_ACCEPTED =>
                                ['Treatment plan partially accepted — ' . $name, 'green', 'accepted'],
                            \App\Models\PlanDecision::DEFERRED =>
                                ['Treatment plan deferred by patient — ' . $name, 'amber', 'deferred'],
                            \App\Models\PlanDecision::REJECTED =>
                                ['Treatment plan rejected by patient — ' . $name, 'red', 'rejected'],
                            default => [null, null, null],
                        };

                        if (! $title) {
                            continue;
                        }

                        $when = $this->toCarbon($decision->created_at);
                        if (! $when || ($before && ! $when->lt($before))) {
                            continue;
                        }

                        $entries->push([
                            'date' => $when, 'type' => 'treatment.decision', 'icon_type' => $icon,
                            'title' => $title,
                            'description' => $decision->notes ?: (trim($total, ' ·') ?: null),
                            'actor' => $decision->recorded_by ? $this->userName($decision->recorded_by) : null,
                            'meta'  => $decision->defer_until
                                ? 'Review on ' . $decision->defer_until->format('d M Y')
                                : ($decision->is_open_ended_deferral ? 'No review date agreed' : null),
                            'group' => 'clinical', 'permission' => 'patients.view', 'link' => $link, 'color' => $color,
                        ]);
                    }

                    // Rejected / cancelled without acceptance (Amendment 1, event 19)
                    // LEGACY INFERENCE — reads a cancelled plan as a rejection.
                    // Cancelled is administrative; rejection is a patient
                    // decision. Kept for pre-2.3 rows only; retiring it belongs
                    // with the Cancelled protection work in Slice 2.3e.
                    if ($plan->status === 'cancelled' && ! $plan->accepted_at) {
                        $entries->push([
                            'date' => $this->toCarbon($plan->updated_at), 'type' => 'treatment.rejected', 'icon_type' => 'rejected',
                            'title' => 'Treatment plan rejected — ' . $name, 'description' => null,
                            'actor' => $plan->created_by ? $this->userName($plan->created_by) : null, 'meta' => null,
                            'group' => 'clinical', 'permission' => 'patients.view', 'link' => $link, 'color' => 'red',
                        ]);
                    }

                    // Pending decision — derived at read time (Amendment 1, event 20).
                    // Slice 2.2: this always described itself as "days after
                    // presentation" while actually measuring from plan_date (the
                    // creation date). Now it prefers the real presented_at when
                    // one exists and only falls back to plan_date for historical
                    // plans that predate presentation truth.
                    $planDate = $this->toCarbon($plan->presented_at) ?? $this->toCarbon($plan->plan_date);
                    if ($plan->status === 'pending' && ! $plan->accepted_at
                        && $planDate && $planDate->lte(now()->subDays(14))) {
                        $entries->push([
                            'date' => $planDate->copy()->addDays(14), 'type' => 'treatment.deferred', 'icon_type' => 'deferred',
                            'title' => 'Treatment plan pending decision — ' . $name,
                            'description' => 'No acceptance ' . (int) $planDate->diffInDays(now()) . ' days after presentation',
                            'actor' => null, 'meta' => null,
                            'group' => 'clinical', 'permission' => 'patients.view', 'link' => $link, 'color' => 'amber',
                        ]);
                    }
                });
        });
    }

    private function addTreatmentVisits(Collection $entries, Patient $patient, ?Carbon $before): void
    {
        $this->guard(function () use ($entries, $patient, $before) {
            TreatmentVisit::where('patient_id', $patient->id)
                ->when($before, fn ($q) => $q->where('visit_date', '<', $before))
                ->orderBy('visit_date', 'desc')->limit(30)->get()
                ->each(function ($v) use ($entries) {
                    $entries->push([
                        'date'        => $this->toCarbon($v->visit_date ?? $v->created_at),
                        'type'        => 'treatment_visit',
                        'icon_type'   => 'treatment',
                        'title'       => $v->treatment_name ?: 'Treatment visit',
                        'description' => $v->chief_complaint ?: null,
                        'actor'       => $v->doctor_id ? $this->userName($v->doctor_id) : null,
                        'meta'        => trim(($v->tooth_number ? 'Tooth ' . $v->tooth_number . ' · ' : '')
                                        . ($v->cost > 0 ? 'Rs. ' . number_format((float) $v->cost, 0) : ''), ' ·') ?: null,
                        'group'       => 'clinical',
                        'permission'  => 'patients.view',
                        'link'        => route('visits.print', $v->id),
                        'color'       => 'violet',
                    ]);
                });
        });
    }

    /** Reserved hook — prescriptions become a source when Rx print/history pages settle. */
    private function addPrescriptionless(Collection $entries): void
    {
    }

    private function addInvoices(Collection $entries, Patient $patient, ?Carbon $before): void
    {
        $this->guard(function () use ($entries, $patient, $before) {
            Invoice::where('patient_id', $patient->id)
                ->when($before, fn ($q) => $q->where('invoice_date', '<', $before))
                ->orderBy('invoice_date', 'desc')->limit(30)->get()
                ->each(function ($inv) use ($entries) {
                    $entries->push([
                        'date'        => $this->toCarbon($inv->invoice_date ?? $inv->created_at),
                        'type'        => 'invoice',
                        'icon_type'   => 'invoice',
                        'title'       => 'Invoice ' . ($inv->invoice_number ?? ('#' . $inv->id))
                                         . ' — Rs. ' . number_format((float) ($inv->total_amount ?? 0), 0),
                        'description' => null,
                        'actor'       => $inv->created_by ? $this->userName($inv->created_by) : null,
                        'meta'        => ucfirst((string) ($inv->status ?? '')),
                        'group'       => 'financial',
                        'permission'  => 'billing.view',
                        'link'        => route('billing.print', $inv->id),
                        'color'       => 'indigo',
                    ]);
                });
        });
    }

    private function addPayments(Collection $entries, Patient $patient, ?Carbon $before): void
    {
        $this->guard(function () use ($entries, $patient, $before) {
            InvoicePayment::where('patient_id', $patient->id)
                ->when($before, fn ($q) => $q->where('payment_date', '<', $before))
                ->orderBy('payment_date', 'desc')->limit(30)->get()
                ->each(function ($p) use ($entries) {
                    $entries->push([
                        'date'        => $this->toCarbon($p->payment_date ?? $p->created_at),
                        'type'        => 'payment',
                        'icon_type'   => 'payment',
                        'title'       => 'Payment received — Rs. ' . number_format((float) $p->amount, 0),
                        'description' => null,
                        'actor'       => $p->created_by ? $this->userName($p->created_by) : null,
                        'meta'        => ucfirst(str_replace('_', ' ', (string) ($p->payment_mode ?? ''))),
                        'group'       => 'financial',
                        'permission'  => 'billing.view',
                        'link'        => $p->invoice_id ? route('billing.print', $p->invoice_id) : null,
                        'color'       => 'green',
                    ]);
                });
        });
    }

    private function addClinicalFiles(Collection $entries, Patient $patient, ?Carbon $before): void
    {
        $this->guard(function () use ($entries, $patient, $before) {
            ClinicalFile::where('patient_id', $patient->id)
                ->when($before, fn ($q) => $q->where('captured_at', '<', $before))
                ->orderBy('captured_at', 'desc')->limit(20)->get()
                ->each(function ($f) use ($entries, $patient) {
                    $entries->push([
                        'date'        => $this->toCarbon($f->captured_at ?? $f->created_at),
                        'type'        => 'media',
                        'icon_type'   => 'media',
                        'title'       => ($f->title ?: 'Clinical file') . ($f->file_type ? ' — ' . strtoupper((string) $f->file_type) : ''),
                        'description' => null,
                        'actor'       => $f->uploaded_by ? $this->userName($f->uploaded_by) : null,
                        'meta'        => null,
                        'group'       => 'clinical',
                        'permission'  => 'patients.view',
                        'link'        => route('patients.show', $patient->id) . '#documents',
                        'color'       => 'cyan',
                    ]);
                });
        });
    }

    private function addLabEvents(Collection $entries, Patient $patient, ?Carbon $before): void
    {
        $this->guard(function () use ($entries, $patient, $before) {
            LabCaseEvent::whereHas('labCase', fn ($q) => $q->where('patient_id', $patient->id))
                ->with('labCase:id,patient_id')
                ->when($before, fn ($q) => $q->where('created_at', '<', $before))
                ->orderBy('created_at', 'desc')->limit(20)->get()
                ->each(function ($e) use ($entries) {
                    $entries->push([
                        'date'        => $this->toCarbon($e->created_at),
                        'type'        => 'lab',
                        'icon_type'   => 'lab',
                        'title'       => 'Lab — ' . ucfirst(str_replace('_', ' ', (string) ($e->event_type ?? 'update'))),
                        'description' => $e->description,
                        'actor'       => $e->user_id ? $this->userName($e->user_id) : null,
                        'meta'        => $e->to_status ? ucfirst(str_replace('_', ' ', (string) $e->to_status)) : null,
                        'group'       => 'clinical',
                        'permission'  => 'lab.view',
                        'link'        => $e->lab_case_id ? route('lab.show', $e->lab_case_id) : null,
                        'color'       => 'orange',
                    ]);
                });
        });
    }

    private function addMemberships(Collection $entries, Patient $patient, ?Carbon $before): void
    {
        $this->guard(function () use ($entries, $patient, $before) {
            FinancePatientMembership::where('patient_id', $patient->id)
                ->with('plan:id,name')
                ->when($before, fn ($q) => $q->where('start_date', '<', $before))
                ->orderBy('start_date', 'desc')->limit(10)->get()
                ->each(function ($m) use ($entries, $patient) {
                    $entries->push([
                        'date'        => $this->toCarbon($m->start_date),
                        'type'        => 'membership',
                        'icon_type'   => 'membership',
                        'title'       => 'Membership enrolled' . ($m->plan?->name ? ' — ' . $m->plan->name : ''),
                        'description' => $m->end_date ? 'Valid till ' . $this->toCarbon($m->end_date)?->format('d M Y') : null,
                        'actor'       => $m->created_by ? $this->userName($m->created_by) : null,
                        'meta'        => ucfirst((string) $m->status),
                        'group'       => 'financial',
                        'permission'  => 'patients.view',
                        'link'        => route('patients.show', $patient->id) . '#membership',
                        'color'       => 'amber',
                    ]);
                });

            MembershipBenefitLog::where('patient_id', $patient->id)
                ->when($before, fn ($q) => $q->where('availed_at', '<', $before))
                ->orderBy('availed_at', 'desc')->limit(15)->get()
                ->each(function ($b) use ($entries) {
                    $entries->push([
                        'date'        => $this->toCarbon($b->availed_at),
                        'type'        => 'membership',
                        'icon_type'   => 'membership',
                        'title'       => 'Membership benefit availed — ' . ucfirst(str_replace('_', ' ', (string) $b->benefit_type)),
                        'description' => $b->amount_saved ? 'Saved Rs. ' . number_format((float) $b->amount_saved, 0) : null,
                        'actor'       => $b->created_by ? $this->userName($b->created_by) : null,
                        'meta'        => null,
                        'group'       => 'financial',
                        'permission'  => 'patients.view',
                        'link'        => null,
                        'color'       => 'amber',
                    ]);
                });
        });
    }

    private function addReviews(Collection $entries, Patient $patient, ?Carbon $before): void
    {
        $this->guard(function () use ($entries, $patient, $before) {
            Review::where('patient_id', $patient->id)
                ->orderBy('requested_at', 'desc')->limit(20)->get()
                ->each(function ($r) use ($entries, $before) {
                    $requested = $this->toCarbon($r->requested_at ?? $r->created_at);
                    if ($requested && (! $before || $requested->lt($before))) {
                        $entries->push([
                            'date' => $requested, 'type' => 'review', 'icon_type' => 'review',
                            'title' => 'Review requested', 'description' => null,
                            'actor' => $r->requested_by_id ? $this->userName($r->requested_by_id) : null,
                            'meta' => ucfirst((string) ($r->channel ?? '')),
                            'group' => 'reviews', 'permission' => 'patients.view', 'link' => null, 'color' => 'yellow',
                        ]);
                    }
                    if ($r->responded_at) {
                        $entries->push([
                            'date' => $this->toCarbon($r->responded_at), 'type' => 'review', 'icon_type' => 'review',
                            'title' => 'Review received' . ($r->rating ? ' — ' . $r->rating . '★' : ''),
                            'description' => $r->comment, 'actor' => null, 'meta' => null,
                            'group' => 'reviews', 'permission' => 'patients.view', 'link' => null, 'color' => 'yellow',
                        ]);
                    }
                });
        });
    }

    private function addConsentLogs(Collection $entries, Patient $patient, ?Carbon $before): void
    {
        $this->guard(function () use ($entries, $patient, $before) {
            ConsentLog::where('patient_id', $patient->id)
                ->when($before, fn ($q) => $q->where('created_at', '<', $before))
                ->orderBy('created_at', 'desc')->limit(30)->get()
                ->each(function ($log) use ($entries) {
                    $entries->push([
                        'date'        => $this->toCarbon($log->created_at),
                        'type'        => 'consent',
                        'icon_type'   => 'consent',
                        'title'       => 'Consent ' . str_replace('_', ' ', (string) $log->event)
                                         . ($log->purpose_key ? ' — ' . str_replace('_', ' ', (string) $log->purpose_key) : ''),
                        'description' => null,
                        'actor'       => $log->captured_by ? $this->userName($log->captured_by) : null,
                        'meta'        => $log->capture_method ? ucfirst((string) $log->capture_method) : null,
                        'group'       => 'consent',
                        'permission'  => 'consent.view',
                        'link'        => null,
                        'color'       => 'amber',
                    ]);
                });
        });
    }

    // ── Clinical helpers ─────────────────────────────────────────────────────

    /** Defaults for entries produced by the legacy shared sources. */
    private function clinicalDefaults(array $e): array
    {
        $group = match ($e['type'] ?? '') {
            'appointment'                       => 'clinical',
            'communication', 'note', 'task'     => 'comms',
            default                             => 'comms',
        };

        return [
            'group'      => $e['group'] ?? $group,
            'permission' => $e['permission'] ?? 'patients.view',
            'link'       => $e['link'] ?? null,
            'color'      => $e['color'] ?? 'slate',
        ];
    }

    private function groupForLedgerEvent(string $event): string
    {
        return match (true) {
            str_starts_with($event, 'payment')                                    => 'financial',
            str_starts_with($event, 'treatment'), str_starts_with($event, 'plan') => 'clinical',
            str_starts_with($event, 'consent')                                    => 'consent',
            str_starts_with($event, 'review')                                     => 'reviews',
            default                                                               => 'comms',
        };
    }

    // ── helpers (mirror ProfileController) ────────────────────────────────────

    private function guard(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            // a single failing source must not break the timeline
        }
    }

    private function toCarbon($value): ?Carbon
    {
        if ($value === null) {
            return null;
        }
        try {
            return $value instanceof Carbon ? $value : Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function iconForEvent(string $event): string
    {
        return match (true) {
            str_starts_with($event, 'call')        => 'call',
            str_starts_with($event, 'whatsapp')    => 'whatsapp',
            str_starts_with($event, 'appointment') => 'appointment',
            str_starts_with($event, 'payment')     => 'payment',
            str_starts_with($event, 'lead')        => 'lead',
            str_starts_with($event, 'recall')      => 'recall',
            str_starts_with($event, 'task')        => 'task',
            str_starts_with($event, 'note')        => 'note',
            default                                 => 'activity',
        };
    }

    private function resolveActorName($act): string
    {
        if (str_contains($act->actor_type ?? '', 'User')) {
            return $this->userName($act->actor_id) ?? 'Staff';
        }
        return 'System';
    }

    private function formatMeta(array $meta): ?string
    {
        $parts = [];
        foreach ($meta as $k => $v) {
            if (is_scalar($v) && strlen((string) $v) < 40) {
                $parts[] = ucfirst(str_replace('_', ' ', $k)) . ': ' . $v;
            }
            if (count($parts) >= 2) {
                break;
            }
        }
        return $parts ? implode(' · ', $parts) : null;
    }

    private function userName($userId): ?string
    {
        return DB::table('users')->where('id', $userId)->value('name');
    }
}
