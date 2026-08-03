<?php

namespace Tests\Feature\Consultations;

use App\Models\Consultation;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * Consultations · Slice 5 — behavioural coverage for the STANDARD workflow
 * (create.blade.php → store()/update()), the module's highest-traffic path.
 *
 * Regression anchors:
 *  - Slice 1 (2026-08-01): edit must never reset consultation_date to today,
 *    and specialty findings must survive the edit round-trip.
 *  - Slice 2 (2026-08-03): edit must never silently reattribute doctor_id
 *    to the logged-in editor.
 *  - Slice 3 (2026-08-03): backdating allowed, future dates rejected.
 *
 * NOTE on assertions: many clinical text columns use encrypted casts
 * (App\Casts\Encrypted / EncryptedArray), so DB-level assertDatabaseHas on
 * those columns would compare against ciphertext. All clinical-content
 * assertions therefore go through fresh model reads.
 */
class StandardConsultationWorkflowTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    private function clinician(): User
    {
        return $this->userWithModulePerm('patients', true, true, false, 'Consult Clinician ' . uniqid());
    }

    private function patient(): Patient
    {
        return Patient::create([
            'name'      => 'Consult Std Patient',
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ]);
    }

    private function otherDoctor(): User
    {
        return User::factory()->create([
            'role'      => 'doctor',
            'branch_id' => 1,
            'is_active' => true,
        ]);
    }

    /** Minimal valid standard-store payload. */
    private function payload(User $doctor, array $overrides = []): array
    {
        return array_merge([
            'doctor_id'         => $doctor->id,
            'consultation_type' => 'new',
            'status'            => 'completed',
            'consultation_date' => now()->format('Y-m-d'),
            'chief_complaint'   => 'Sharp pain lower left molar',
            'hopi_final'        => 'Pain for 3 days, worse with cold.',
            'primary_diagnosis' => 'Irreversible pulpitis 36',
        ], $overrides);
    }

    // ── Store ────────────────────────────────────────────────────────────────

    public function test_store_creates_completed_consultation_with_chosen_doctor_and_backdate(): void
    {
        $user    = $this->clinician();
        $doctor  = $this->otherDoctor();
        $patient = $this->patient();
        $pastDay = now()->subDays(4)->format('Y-m-d');

        $this->actingAs($user)
            ->post(route('patients.consultations.store', $patient),
                $this->payload($doctor, ['consultation_date' => $pastDay]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $consultation = Consultation::latest('id')->first();
        $this->assertNotNull($consultation);
        $this->assertSame($patient->id, $consultation->patient_id);
        // Receptionist entering on behalf of a doctor must not self-attribute.
        $this->assertSame($doctor->id, $consultation->doctor_id);
        $this->assertSame('new', $consultation->consultation_type);
        $this->assertSame('completed', $consultation->status);
        // Backdating is a supported workflow (missed entries).
        $this->assertSame($pastDay, $consultation->consultation_date->toDateString());
        $this->assertSame('Sharp pain lower left molar', $consultation->chief_complaint);
    }

    public function test_store_rejects_future_consultation_date(): void
    {
        $user    = $this->clinician();
        $patient = $this->patient();

        $this->actingAs($user)
            ->from(route('patients.consultations.create', $patient))
            ->post(route('patients.consultations.store', $patient),
                $this->payload($user, ['consultation_date' => now()->addDay()->format('Y-m-d')]))
            ->assertSessionHasErrors('consultation_date');

        $this->assertSame(0, Consultation::count());
    }

    public function test_store_falls_back_to_logged_in_user_when_doctor_absent(): void
    {
        $user    = $this->clinician();
        $patient = $this->patient();

        $payload = $this->payload($user);
        unset($payload['doctor_id']);

        $this->actingAs($user)
            ->post(route('patients.consultations.store', $patient), $payload)
            ->assertSessionHasNoErrors();

        $this->assertSame($user->id, Consultation::latest('id')->first()->doctor_id);
    }

    // ── Update: the Slice 1 / Slice 2 regression anchors ─────────────────────

    public function test_update_preserves_backdated_consultation_date(): void
    {
        $user    = $this->clinician();
        $doctor  = $this->otherDoctor();
        $patient = $this->patient();
        $pastDay = now()->subDays(10)->format('Y-m-d');

        $consultation = Consultation::create([
            'patient_id'        => $patient->id,
            'doctor_id'         => $doctor->id,
            'branch_id'         => 1,
            'consultation_type' => 'new',
            'visit_type'        => 'routine',
            'status'            => 'completed',
            'consultation_date' => $pastDay,
            'chief_complaint'   => 'Original complaint',
        ]);

        // The edit form reposts the record's saved clinical date (Slice 1 fix).
        $this->actingAs($user)
            ->put(route('patients.consultations.update', [$patient, $consultation]),
                $this->payload($doctor, [
                    'consultation_date' => $pastDay,
                    'chief_complaint'   => 'Amended complaint wording',
                ]))
            ->assertSessionHasNoErrors();

        $fresh = $consultation->fresh();
        // THE Slice 1 regression: date must not silently become today.
        $this->assertSame($pastDay, $fresh->consultation_date->toDateString());
        $this->assertSame('Amended complaint wording', $fresh->chief_complaint);
    }

    public function test_update_does_not_reattribute_doctor_to_the_editor(): void
    {
        $editor  = $this->clinician();
        $doctor  = $this->otherDoctor();
        $patient = $this->patient();

        $consultation = Consultation::create([
            'patient_id'        => $patient->id,
            'doctor_id'         => $doctor->id,
            'branch_id'         => 1,
            'consultation_type' => 'new',
            'visit_type'        => 'routine',
            'status'            => 'completed',
            'consultation_date' => now()->subDay(),
            'chief_complaint'   => 'Attribution test',
        ]);

        // Slice 2 regression: the form posts the SAVED doctor (select defaults
        // to the record's doctor), so an assistant amending a typo must not
        // steal attribution.
        $this->actingAs($editor)
            ->put(route('patients.consultations.update', [$patient, $consultation]),
                $this->payload($doctor, ['consultation_date' => now()->subDay()->format('Y-m-d')]))
            ->assertSessionHasNoErrors();

        $this->assertSame($doctor->id, $consultation->fresh()->doctor_id);
    }

    public function test_update_without_doctor_key_leaves_attribution_unchanged(): void
    {
        $editor  = $this->clinician();
        $doctor  = $this->otherDoctor();
        $patient = $this->patient();

        $consultation = Consultation::create([
            'patient_id'        => $patient->id,
            'doctor_id'         => $doctor->id,
            'branch_id'         => 1,
            'consultation_type' => 'new',
            'visit_type'        => 'routine',
            'status'            => 'completed',
            'consultation_date' => now()->subDay(),
            'chief_complaint'   => 'Attribution test 2',
        ]);

        $payload = $this->payload($doctor, ['consultation_date' => now()->subDay()->format('Y-m-d')]);
        unset($payload['doctor_id']);

        $this->actingAs($editor)
            ->put(route('patients.consultations.update', [$patient, $consultation]), $payload)
            ->assertSessionHasNoErrors();

        // update() has no auth()->id() fallback by design.
        $this->assertSame($doctor->id, $consultation->fresh()->doctor_id);
    }

    // ── Specialty findings round-trip (Slice 1 blocker #2) ───────────────────

    public function test_specialty_findings_survive_the_edit_round_trip(): void
    {
        $user     = $this->clinician();
        $patient  = $this->patient();
        $findings = ['endo' => ['tooth' => '36', 'vitality' => 'non-vital']];

        $this->actingAs($user)
            ->post(route('patients.consultations.store', $patient), $this->payload($user, [
                'specialty_findings'   => json_encode($findings),
                'accepted_specialties' => json_encode(['endo']),
                'specialty_modules'    => [
                    ['specialty_tag' => 'endo', 'findings' => $findings['endo']],
                    ['specialty_tag' => 'perio', 'findings' => ['probing' => 'generalised 4mm']],
                ],
            ]))
            ->assertSessionHasNoErrors();

        $consultation = Consultation::latest('id')->first();
        $this->assertSame($findings, $consultation->specialty_findings);
        $this->assertCount(2, $consultation->specialtyModules);

        // Edit reposts endo only → perio must be soft-rejected, never deleted;
        // the findings payload itself must survive unchanged.
        $this->actingAs($user)
            ->put(route('patients.consultations.update', [$patient, $consultation]),
                $this->payload($user, [
                    'specialty_findings'   => json_encode($findings),
                    'accepted_specialties' => json_encode(['endo']),
                    'specialty_modules'    => [
                        ['specialty_tag' => 'endo', 'findings' => $findings['endo']],
                    ],
                ]))
            ->assertSessionHasNoErrors();

        $fresh = $consultation->fresh();
        $this->assertSame($findings, $fresh->specialty_findings, 'Edit blanked specialty findings (Slice 1 blocker #2 regression).');

        $endo  = $fresh->specialtyModules()->where('specialty_tag', 'endo')->first();
        $perio = $fresh->specialtyModules()->where('specialty_tag', 'perio')->first();
        $this->assertNull($endo->rejected_at);
        $this->assertNotNull($perio, 'Soft-reject must not delete the module row.');
        $this->assertNotNull($perio->rejected_at);
    }

    // ── Validation shape ─────────────────────────────────────────────────────

    public function test_routine_visit_requires_chief_complaint(): void
    {
        $user    = $this->clinician();
        $patient = $this->patient();

        $payload = $this->payload($user, ['visit_type' => 'routine']);
        unset($payload['chief_complaint']);

        $this->actingAs($user)
            ->from(route('patients.consultations.create', $patient))
            ->post(route('patients.consultations.store', $patient), $payload)
            ->assertSessionHasErrors('chief_complaint');
    }
}
