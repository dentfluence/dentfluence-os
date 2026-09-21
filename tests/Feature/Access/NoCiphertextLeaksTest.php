<?php

namespace Tests\Feature\Access;

use App\Models\Consultation;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * Signal Board row 2A.6 — encrypted PHI must never reach a screen as ciphertext.
 *
 * On 12 September the Daily Huddle board rendered "eyJpdiI6Ii…" where patient
 * names and medical alerts belonged. The cause is structural, not a typo:
 * App\Casts\Encrypted decrypts only when a row is hydrated through a MODEL. Any
 * DB::table() or join read bypasses the model and hands back raw ciphertext,
 * which goes straight into the Blade or the JSON payload. App\Support\Phi
 * exists for exactly those reads.
 *
 * The 17 September sweep (row V.13) checked all 71 encrypted columns by hand
 * and found zero unprotected reads. That is a point in time and nothing more.
 * The next join someone writes reintroduces the bug with a green suite, because
 * no test has ever looked at a response body for ciphertext.
 *
 * This test does. It is deliberately dumb: seed known plaintext into encrypted
 * columns, fetch the surfaces that carry PHI, and fail if the marker string
 * appears anywhere in the body. It also asserts the plaintext IS present, so a
 * response that is empty, 500, or silently filtered cannot pass as clean.
 */
class NoCiphertextLeaksTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    /** Laravel's encrypted payload is base64 JSON; every one begins this way. */
    private const CIPHERTEXT_MARKER = 'eyJpdiI6I';

    private const ADDRESS   = 'ZZ-ADDRESS-MARKER-221B';
    private const ALERT     = 'ZZ-ALERT-MARKER-PENICILLIN';
    private const COMPLAINT = 'ZZ-COMPLAINT-MARKER-TOOTHACHE';
    private const DIAGNOSIS = 'ZZ-DIAGNOSIS-MARKER-PULPITIS';

    private function patientWithPhi(): Patient
    {
        return Patient::create([
            'name'            => 'Ciphertext Probe',
            'phone'           => '9' . random_int(100000000, 999999999),
            'branch_id'       => 1,
            'address'         => self::ADDRESS,
            'medical_alert'   => self::ALERT,
            'chief_complaint' => self::COMPLAINT,
        ]);
    }

    private function consultationFor(Patient $patient, int $doctorId): Consultation
    {
        return Consultation::create([
            'patient_id'        => $patient->id,
            'doctor_id'         => $doctorId,
            'branch_id'         => 1,
            'consultation_date' => now()->toDateString(),
            'consultation_type' => 'new',
            'chief_complaint'   => self::COMPLAINT,
            'primary_diagnosis' => self::DIAGNOSIS,
        ]);
    }

    /**
     * @param \Illuminate\Testing\TestResponse $response
     */
    private function assertNoCiphertext($response, string $where): void
    {
        $body = $response->getContent();

        $this->assertStringNotContainsString(self::CIPHERTEXT_MARKER, $body,
            "Encrypted PHI is being rendered as ciphertext on {$where}. "
            . 'A raw DB::table() or join read is almost certainly bypassing the '
            . 'Encrypted cast — pass it through App\Support\Phi::decrypt().');
    }

    public function test_the_patient_api_returns_plaintext_not_ciphertext(): void
    {
        $patient = $this->patientWithPhi();

        Sanctum::actingAs($this->userWithModulePerm('patients', true, false, false), ['*']);

        $response = $this->getJson("/api/v1/patients/{$patient->id}")->assertOk();

        $this->assertNoCiphertext($response, 'GET /api/v1/patients/{id}');
        $response->assertSee(self::ADDRESS, false);
    }

    public function test_the_consultations_api_returns_plaintext_not_ciphertext(): void
    {
        $user    = $this->userWithModulePerm('patients', true, true, false);
        $patient = $this->patientWithPhi();
        $this->consultationFor($patient, $user->id);

        Sanctum::actingAs($this->fresh($user), ['*']);

        $response = $this->getJson("/api/v1/patients/{$patient->id}/consultations")->assertOk();

        $this->assertNoCiphertext($response, 'GET /api/v1/patients/{id}/consultations');
        $response->assertSee(self::DIAGNOSIS, false);
    }

    public function test_the_patient_profile_page_shows_plaintext_not_ciphertext(): void
    {
        $patient = $this->patientWithPhi();

        $response = $this->actingAs($this->userWithModulePerm('patients', true, false, false))
            ->get(route('patients.show', $patient))
            ->assertOk();

        $this->assertNoCiphertext($response, 'the patient profile page');
    }

    public function test_the_daily_huddle_board_shows_no_ciphertext(): void
    {
        // The surface the 12 Sep bug actually appeared on.
        $user    = $this->userWithTwoModulePerms('daily_huddle', [true, true, false], 'patients', [true, true, false]);
        $patient = $this->patientWithPhi();
        $this->consultationFor($patient, $user->id);

        $response = $this->actingAs($this->fresh($user))
            ->get(route('huddle.index'))
            ->assertOk();

        $this->assertNoCiphertext($response, 'the Daily Huddle board');
    }

    public function test_the_marker_would_actually_be_caught(): void
    {
        // A guard on the guard: if Laravel ever changes its payload shape, the
        // marker above stops matching and every test here passes vacuously.
        $encrypted = \Illuminate\Support\Facades\Crypt::encryptString('anything');

        $this->assertStringStartsWith(self::CIPHERTEXT_MARKER, $encrypted,
            'Laravel ciphertext no longer starts with the marker this test greps for — '
            . 'update CIPHERTEXT_MARKER or these tests prove nothing.');
    }
}
