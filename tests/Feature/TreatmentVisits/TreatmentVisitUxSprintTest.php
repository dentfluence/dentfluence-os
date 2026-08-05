<?php

namespace Tests\Feature\TreatmentVisits;

use App\Http\Middleware\CheckModulePermission;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\TreatmentVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\TreatmentVisits\Concerns\BuildsVisitFixtures;
use Tests\TestCase;

/**
 * Phase 2 UX Implementation Sprint (2026-08-05) — new behavior introduced by
 * the approved Freeze Spec. Everything here validates NEW code paths only;
 * pre-existing behavior stays covered by the original 33-test suite.
 *
 *   G1  — invoiced-items delete guard (new business rule mandated by the spec)
 *   UX-04 — "No Treatment Done Today" recorded answer endpoint
 *   UX-04 — visit_gate flash after consultation save
 *   UX-07 — role-scoped nav badge counts endpoint
 */
class TreatmentVisitUxSprintTest extends TestCase
{
    use RefreshDatabase;
    use BuildsVisitFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(CheckModulePermission::class);
    }

    private function makeTreatmentAppointmentToday(Patient $patient, $doctor): Appointment
    {
        return Appointment::create([
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'branch_id'        => 1,
            'created_by'       => $doctor->id,
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '10:00',
            'type'             => 'treatment',
            'status'           => 'scheduled',
        ]);
    }

    // ── G1: invoiced-items delete guard ──────────────────────────────────────

    public function test_a_visit_with_invoiced_items_cannot_be_deleted(): void
    {
        $user    = $this->makeUser();
        $patient = $this->makePatient();
        $visit   = $patient->treatmentVisits()->create($this->baseVisitPayload());
        $visit->visitItems()->create([
            'patient_id'     => $patient->id,
            'treatment_name' => 'Crown',
            'billing_status' => 'invoiced',
        ]);

        $this->actingAs($user)
            ->deleteJson(route('visits.destroy', $visit))
            ->assertStatus(422);

        // Nothing was touched: visit alive, item intact.
        $this->assertNull($visit->fresh()->deleted_at);
        $this->assertDatabaseHas('treatment_visit_items', [
            'treatment_visit_id' => $visit->id,
            'billing_status'     => 'invoiced',
        ]);
    }

    // ── UX-04: recorded "No Treatment Done Today" answer ─────────────────────

    public function test_none_today_answer_is_recorded_as_an_activity(): void
    {
        $user    = $this->makeUser();
        $patient = $this->makePatient();

        $this->actingAs($user)
            ->postJson(route('visits.none-today', $patient), [])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('activities', [
            'subject_type' => Patient::class,
            'subject_id'   => $patient->id,
            'event'        => 'treatment_visit.none_today',
        ]);
    }

    // ── UX-04: consultation-save gate flash ──────────────────────────────────

    public function test_consultation_save_flashes_the_visit_gate_for_an_unvisited_treatment_appointment(): void
    {
        $user    = $this->makeUser();
        $patient = $this->makePatient();
        $appt    = $this->makeTreatmentAppointmentToday($patient, $user);

        $response = $this->actingAs($user)->post(
            route('patients.consultations.store', $patient),
            ['chief_complaint' => 'Pain in lower left molar']
        );

        $response->assertRedirect();
        $response->assertSessionHas('visit_gate');
        $gate = session('visit_gate');
        $this->assertSame($patient->id, $gate['patient_id']);
        $this->assertSame($appt->id, $gate['appointment_id']);
    }

    public function test_gate_does_not_fire_when_a_visit_was_already_recorded_today(): void
    {
        $user    = $this->makeUser();
        $patient = $this->makePatient();
        $this->makeTreatmentAppointmentToday($patient, $user);
        $patient->treatmentVisits()->create($this->baseVisitPayload());

        $response = $this->actingAs($user)->post(
            route('patients.consultations.store', $patient),
            ['chief_complaint' => 'Follow-up check']
        );

        $response->assertRedirect();
        $this->assertNull(session('visit_gate'));
    }

    public function test_gate_does_not_fire_after_none_today_was_answered(): void
    {
        $user    = $this->makeUser();
        $patient = $this->makePatient();
        $this->makeTreatmentAppointmentToday($patient, $user);

        $this->actingAs($user)->postJson(route('visits.none-today', $patient), [])->assertOk();

        $response = $this->actingAs($user)->post(
            route('patients.consultations.store', $patient),
            ['chief_complaint' => 'Sensitivity review']
        );

        $response->assertRedirect();
        $this->assertNull(session('visit_gate'));
    }

    public function test_gate_does_not_fire_for_a_consultation_type_appointment(): void
    {
        $user    = $this->makeUser();
        $patient = $this->makePatient();
        Appointment::create([
            'patient_id'       => $patient->id,
            'doctor_id'        => $user->id,
            'branch_id'        => 1,
            'created_by'       => $user->id,
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '11:00',
            'type'             => 'consultation',
            'status'           => 'scheduled',
        ]);

        $response = $this->actingAs($user)->post(
            route('patients.consultations.store', $patient),
            ['chief_complaint' => 'New patient exam']
        );

        $response->assertRedirect();
        $this->assertNull(session('visit_gate'));
    }

    // ── UX-07: nav badge counts endpoint ─────────────────────────────────────

    public function test_nav_badges_returns_pending_prompt_and_draft_lab_counts_for_an_admin(): void
    {
        $user    = $this->makeUser(); // admin — canAccess everything
        $patient = $this->makePatient();

        // One visit with an item → creates exactly one pending billing prompt.
        $this->actingAs($user)->postJson(route('visits.store', $patient), $this->baseVisitPayload([
            'visit_items' => [['treatment_name' => 'Scaling']],
        ]))->assertOk();

        $this->actingAs($user)
            ->getJson(route('notifications.navBadges'))
            ->assertOk()
            ->assertJsonPath('billing_prompts', 1)
            ->assertJsonPath('lab_drafts', 0);
    }
}
