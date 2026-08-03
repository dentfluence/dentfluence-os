<?php

namespace Tests\Feature\Consultations;

use App\Models\BillingPrompt;
use App\Models\Consultation;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * Consultations · Slice 5 — behavioural coverage for the TYPED workflows:
 * Same Issue, Minor Visit (store + edit), Emergency.
 *
 * Regression anchors:
 *  - Slice 1 (2026-08-01): Minor Visit advice must land in `advice` from
 *    whichever branch (clinic-related / external) was actually active.
 *  - Slice 3 (2026-08-03): future dates rejected on every typed write path;
 *    Emergency's ceiling is `now` (datetime-local), not midnight.
 *  - Minor Visit charges fire exactly ONE BillingPrompt at creation and are
 *    deliberately not reprocessed on update.
 */
class TypedConsultationWorkflowsTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    private function clinician(): User
    {
        return $this->userWithModulePerm('patients', true, true, false, 'Typed Clinician ' . uniqid());
    }

    private function patient(): Patient
    {
        return Patient::create([
            'name'      => 'Typed Flow Patient',
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ]);
    }

    // ── Same Issue ───────────────────────────────────────────────────────────

    public function test_same_issue_store_creates_typed_consultation(): void
    {
        $user    = $this->clinician();
        $patient = $this->patient();

        $this->actingAs($user)
            ->post(route('patients.consultations.same-issue.store', $patient), [
                'doctor_id'           => $user->id,
                'consultation_date'   => now()->subDay()->format('Y-m-d'),
                'update_notes'        => 'Pain unchanged since last visit.',
                'additional_findings' => 'Slight swelling buccal 36.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $consultation = Consultation::latest('id')->first();
        $this->assertSame('same_issue', $consultation->consultation_type);
        $this->assertSame('Pain unchanged since last visit.', $consultation->update_notes);
        $this->assertSame('Slight swelling buccal 36.', $consultation->additional_findings);
    }

    public function test_same_issue_edit_round_trip_keeps_type_and_updates_fields(): void
    {
        $user    = $this->clinician();
        $patient = $this->patient();

        $this->actingAs($user)
            ->post(route('patients.consultations.same-issue.store', $patient), [
                'doctor_id'    => $user->id,
                'update_notes' => 'Original update.',
            ])
            ->assertSessionHasNoErrors();

        $consultation = Consultation::latest('id')->first();

        // Slice 6: Same Issue now has its own edit pair (was: generic form,
        // which has no update_notes input — silent corruption risk).
        $this->actingAs($user)
            ->put(route('patients.consultations.same-issue.update', [$patient, $consultation]), [
                'doctor_id'           => $user->id,
                'consultation_date'   => now()->subDay()->format('Y-m-d'),
                'update_notes'        => 'Revised update after review.',
                'additional_findings' => 'New finding 46.',
            ])
            ->assertSessionHasNoErrors();

        $fresh = $consultation->fresh();
        $this->assertSame('same_issue', $fresh->consultation_type);
        $this->assertSame('Revised update after review.', $fresh->update_notes);
        $this->assertSame('New finding 46.', $fresh->additional_findings);
    }

    public function test_same_issue_legacy_shape_record_stays_editable(): void
    {
        $user    = $this->clinician();
        $patient = $this->patient();

        // A record created by the RETIRED create.blade.php same_issue chip:
        // content in chief_complaint, no update_notes.
        $legacy = Consultation::create([
            'patient_id'        => $patient->id,
            'doctor_id'         => $user->id,
            'branch_id'         => 1,
            'consultation_type' => 'same_issue',
            'visit_type'        => 'routine',
            'status'            => 'completed',
            'consultation_date' => now()->subWeek(),
            'chief_complaint'   => 'Legacy progress note.',
        ]);

        // update_notes is required_without:chief_complaint — a legacy-shape
        // edit posting only its own fields must still pass.
        $this->actingAs($user)
            ->put(route('patients.consultations.same-issue.update', [$patient, $legacy]), [
                'doctor_id'       => $user->id,
                'chief_complaint' => 'Legacy progress note — corrected.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Legacy progress note — corrected.', $legacy->fresh()->chief_complaint);
    }

    public function test_standard_store_refuses_to_write_same_issue_records(): void
    {
        $user    = $this->clinician();
        $patient = $this->patient();

        // Slice 6 one-writer guard: the standard store must never create a
        // same_issue record again (that was the schema-incompatible chip path).
        $this->actingAs($user)
            ->post(route('patients.consultations.store', $patient), [
                'doctor_id'         => $user->id,
                'consultation_type' => 'same_issue',
                'chief_complaint'   => 'Chip-shaped payload',
            ])
            ->assertRedirect(route('patients.consultations.same-issue.create', $patient));

        $this->assertSame(0, Consultation::count());
    }

    public function test_generic_edit_redirects_typed_records_to_their_own_screens(): void
    {
        $user    = $this->clinician();
        $patient = $this->patient();

        $sameIssue = Consultation::create([
            'patient_id' => $patient->id, 'doctor_id' => $user->id, 'branch_id' => 1,
            'consultation_type' => 'same_issue', 'visit_type' => 'routine',
            'status' => 'completed', 'consultation_date' => now()->subDay(),
            'update_notes' => 'x',
        ]);
        $minorVisit = Consultation::create([
            'patient_id' => $patient->id, 'doctor_id' => $user->id, 'branch_id' => 1,
            'consultation_type' => 'minor_visit', 'visit_type' => 'routine',
            'status' => 'completed', 'consultation_date' => now()->subDay(),
            'procedure_performed' => 'x',
        ]);

        // Direct URL entry to the generic edit form must bounce to the typed
        // screen — link-level routing alone doesn't cover bookmarks.
        $this->actingAs($user)
            ->get(route('consultations.edit', $sameIssue))
            ->assertRedirect(route('patients.consultations.same-issue.edit', [$patient, $sameIssue]));

        $this->actingAs($user)
            ->get(route('consultations.edit', $minorVisit))
            ->assertRedirect(route('patients.consultations.minor-visit.edit', [$patient, $minorVisit]));
    }

    public function test_same_issue_requires_update_notes_and_rejects_future_date(): void
    {
        $user    = $this->clinician();
        $patient = $this->patient();

        $this->actingAs($user)
            ->post(route('patients.consultations.same-issue.store', $patient), [
                'doctor_id' => $user->id,
            ])
            ->assertSessionHasErrors('update_notes');

        $this->actingAs($user)
            ->post(route('patients.consultations.same-issue.store', $patient), [
                'doctor_id'         => $user->id,
                'update_notes'      => 'x',
                'consultation_date' => now()->addDays(2)->format('Y-m-d'),
            ])
            ->assertSessionHasErrors('consultation_date');

        $this->assertSame(0, Consultation::count());
    }

    // ── Minor Visit ──────────────────────────────────────────────────────────

    private function minorVisitPayload(User $doctor, array $overrides = []): array
    {
        return array_merge([
            'doctor_id'                   => $doctor->id,
            'consultation_date'           => now()->format('Y-m-d'),
            'related_to_clinic_treatment' => '1',
            'procedure_performed'         => 'Suture removal',
            'advice_clinic_related'       => 'Warm saline rinses.',
            'advice_external'             => 'SHOULD BE IGNORED on the clinic path',
        ], $overrides);
    }

    public function test_minor_visit_clinic_path_saves_clinic_advice(): void
    {
        $user    = $this->clinician();
        $patient = $this->patient();

        $this->actingAs($user)
            ->post(route('patients.consultations.minor-visit.store', $patient),
                $this->minorVisitPayload($user))
            ->assertSessionHasNoErrors();

        $consultation = Consultation::latest('id')->first();
        $this->assertSame('minor_visit', $consultation->consultation_type);
        // Slice 1 blocker #4: advice must come from the ACTIVE branch.
        $this->assertSame('Warm saline rinses.', $consultation->advice);
    }

    public function test_minor_visit_external_path_saves_external_advice(): void
    {
        $user    = $this->clinician();
        $patient = $this->patient();

        $this->actingAs($user)
            ->post(route('patients.consultations.minor-visit.store', $patient),
                $this->minorVisitPayload($user, [
                    'related_to_clinic_treatment' => '0',
                    'advice_clinic_related'       => 'SHOULD BE IGNORED on the external path',
                    'advice_external'              => 'Refer back to treating dentist.',
                ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Refer back to treating dentist.', Consultation::latest('id')->first()->advice);
    }

    public function test_minor_visit_charges_fire_exactly_one_billing_prompt(): void
    {
        $user    = $this->clinician();
        $patient = $this->patient();

        $this->actingAs($user)
            ->post(route('patients.consultations.minor-visit.store', $patient),
                $this->minorVisitPayload($user, ['charges' => 500]))
            ->assertSessionHasNoErrors();

        $consultation = Consultation::latest('id')->first();

        $prompts = BillingPrompt::where('trigger_type', 'consultation')
            ->where('trigger_id', $consultation->id)->get();
        $this->assertCount(1, $prompts);
        $this->assertSame($patient->id, $prompts->first()->patient_id);
        $this->assertSame('pending', $prompts->first()->status);

        // Editing the visit must NOT queue a duplicate prompt (update path
        // deliberately does not accept charges).
        $this->actingAs($user)
            ->put(route('patients.consultations.minor-visit.update', [$patient, $consultation]),
                $this->minorVisitPayload($user, ['charges' => 500]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, BillingPrompt::where('trigger_type', 'consultation')
            ->where('trigger_id', $consultation->id)->count());
    }

    public function test_minor_visit_update_remerges_advice_and_keeps_type(): void
    {
        $user    = $this->clinician();
        $patient = $this->patient();

        $this->actingAs($user)
            ->post(route('patients.consultations.minor-visit.store', $patient),
                $this->minorVisitPayload($user))
            ->assertSessionHasNoErrors();

        $consultation = Consultation::latest('id')->first();

        $this->actingAs($user)
            ->put(route('patients.consultations.minor-visit.update', [$patient, $consultation]),
                $this->minorVisitPayload($user, [
                    'related_to_clinic_treatment' => '0',
                    'advice_external'             => 'Updated external advice.',
                ]))
            ->assertSessionHasNoErrors();

        $fresh = $consultation->fresh();
        $this->assertSame('minor_visit', $fresh->consultation_type);
        $this->assertSame('Updated external advice.', $fresh->advice);
    }

    public function test_minor_visit_rejects_future_date(): void
    {
        $user    = $this->clinician();
        $patient = $this->patient();

        $this->actingAs($user)
            ->post(route('patients.consultations.minor-visit.store', $patient),
                $this->minorVisitPayload($user, ['consultation_date' => now()->addDay()->format('Y-m-d')]))
            ->assertSessionHasErrors('consultation_date');

        $this->assertSame(0, Consultation::count());
    }

    // ── Emergency ────────────────────────────────────────────────────────────

    private function emergencyPayload(User $doctor, array $overrides = []): array
    {
        return array_merge([
            'doctor_id'                    => $doctor->id,
            'consultation_date'            => now()->subHour()->format('Y-m-d\TH:i'),
            'chief_complaint'              => 'Avulsed tooth 11 after fall',
            'emergency_treatment_rendered' => 'Reimplanted and splinted 11.',
            'advice'                       => 'Soft diet, review 48h.',
        ], $overrides);
    }

    public function test_emergency_store_creates_typed_consultation(): void
    {
        $user    = $this->clinician();
        $patient = $this->patient();

        $this->actingAs($user)
            ->post(route('patients.consultations.emergency.store', $patient),
                $this->emergencyPayload($user))
            ->assertSessionHasNoErrors();

        $consultation = Consultation::latest('id')->first();
        $this->assertSame('emergency', $consultation->consultation_type);
        $this->assertSame('emergency', $consultation->visit_type);
        $this->assertSame('Reimplanted and splinted 11.', $consultation->emergency_treatment_rendered);
    }

    public function test_emergency_requires_complaint_and_treatment_rendered(): void
    {
        $user    = $this->clinician();
        $patient = $this->patient();

        $this->actingAs($user)
            ->post(route('patients.consultations.emergency.store', $patient), [
                'doctor_id' => $user->id,
            ])
            ->assertSessionHasErrors(['chief_complaint', 'emergency_treatment_rendered']);
    }

    public function test_emergency_edit_round_trip_keeps_type_and_updates_fields(): void
    {
        $user    = $this->clinician();
        $patient = $this->patient();

        $this->actingAs($user)
            ->post(route('patients.consultations.emergency.store', $patient),
                $this->emergencyPayload($user))
            ->assertSessionHasNoErrors();

        $consultation = Consultation::latest('id')->first();

        // Slice 9: Emergency now has its own edit pair (was: generic form,
        // which has no emergency_treatment_rendered input).
        $this->actingAs($user)
            ->put(route('patients.consultations.emergency.update', [$patient, $consultation]),
                $this->emergencyPayload($user, [
                    'emergency_treatment_rendered' => 'Splint checked and adjusted.',
                    'advice'                       => 'Continue soft diet.',
                ]))
            ->assertSessionHasNoErrors();

        $fresh = $consultation->fresh();
        $this->assertSame('emergency', $fresh->consultation_type);
        $this->assertSame('Splint checked and adjusted.', $fresh->emergency_treatment_rendered);
        $this->assertSame('Continue soft diet.', $fresh->advice);

        // Direct URL entry to the generic edit form bounces to the typed screen.
        $this->actingAs($user)
            ->get(route('consultations.edit', $consultation))
            ->assertRedirect(route('patients.consultations.emergency.edit', [$patient, $consultation]));
    }

    public function test_emergency_store_creates_real_prescription_from_rx_panel(): void
    {
        $user    = $this->clinician();
        $patient = $this->patient();

        // Slice 9: Rx on the Emergency screen goes through the SAME
        // PrescriptionQuickSaveService engine as the standard form — a real
        // Prescription row keyed by consultation_id, never a dead JSON column.
        $this->actingAs($user)
            ->post(route('patients.consultations.emergency.store', $patient),
                $this->emergencyPayload($user, [
                    'prescriptions_data' => json_encode([[
                        'drug'      => 'Amoxicillin 500mg',
                        'form_type' => 'tablet',
                        'food'      => 'after',
                        'sos'       => false,
                        'morn'      => true,
                        'noon'      => false,
                        'night'     => true,
                        'duration'  => '5',
                        'unit'      => 'days',
                    ]]),
                ]))
            ->assertSessionHasNoErrors();

        $consultation = Consultation::latest('id')->first();
        $rx = \App\Models\Prescription\Prescription::where('consultation_id', $consultation->id)->first();
        $this->assertNotNull($rx, 'Emergency Rx panel did not create a Prescription row.');
        $this->assertSame($patient->id, $rx->patient_id);

        // An empty panel must be a no-op, not an empty prescription record.
        $this->actingAs($user)
            ->post(route('patients.consultations.emergency.store', $patient),
                $this->emergencyPayload($user))
            ->assertSessionHasNoErrors();

        $second = Consultation::latest('id')->first();
        $this->assertNull(\App\Models\Prescription\Prescription::where('consultation_id', $second->id)->first());
    }

    public function test_emergency_accepts_today_time_but_rejects_future(): void
    {
        $user    = $this->clinician();
        $patient = $this->patient();

        // Slice 3 nuance: emergency posts datetime-local, so an entry made
        // "today at 14:30" must pass — the rule is before_or_equal:now, NOT
        // before_or_equal:today (midnight), which would reject it.
        $this->actingAs($user)
            ->post(route('patients.consultations.emergency.store', $patient),
                $this->emergencyPayload($user, [
                    'consultation_date' => now()->subMinutes(5)->format('Y-m-d\TH:i'),
                ]))
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->post(route('patients.consultations.emergency.store', $patient),
                $this->emergencyPayload($user, [
                    'consultation_date' => now()->addHours(3)->format('Y-m-d\TH:i'),
                ]))
            ->assertSessionHasErrors('consultation_date');

        $this->assertSame(1, Consultation::count());
    }
}
