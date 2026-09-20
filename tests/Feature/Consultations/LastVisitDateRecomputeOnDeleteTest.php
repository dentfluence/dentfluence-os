<?php

namespace Tests\Feature\Consultations;

use App\Models\Consultation;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * Signal Board row 2.8 — deleting a consultation must give its date back.
 *
 * ConsultationClinicalWiringObserver had created() and updated() only, and
 * advanceLastVisitDate() writes only when the new date is LATER. That
 * monotonic rule is deliberate and correct for a write: a backdated correction
 * must never drag a fresher visit date backwards. It is wrong for a delete.
 *
 * So deleting the consultation that set patients.last_visit_date left the
 * column pointing at a visit that no longer exists. The recall engine keys
 * ENTIRELY off that column, so the patient's recall stayed suppressed until
 * they happened to come back — silently, with nothing on any screen showing
 * it. A patient whose only consultation was deleted was suppressed forever.
 *
 * deleted() now RECOMPUTES from the patient's remaining consultations and is
 * allowed to move the date backwards, or to null it. restored() keeps the
 * monotonic advance, because a restore can only ever add a visit back.
 */
class LastVisitDateRecomputeOnDeleteTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    private function clinician(): User
    {
        return $this->userWithModulePerm('patients', true, true, false, 'Recompute Clinician ' . uniqid());
    }

    private function patient(): Patient
    {
        return Patient::create([
            'name'      => 'Recompute Patient',
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ]);
    }

    private function recordVisit(User $user, Patient $patient, string $date): Consultation
    {
        $this->actingAs($user)
            ->post(route('patients.consultations.store', $patient), [
                'doctor_id'         => $user->id,
                'consultation_type' => 'new',
                'chief_complaint'   => 'Visit on ' . $date,
                'consultation_date' => $date,
            ])
            ->assertSessionHasNoErrors();

        return Consultation::where('patient_id', $patient->id)
            ->where('consultation_date', $date)
            ->latest('id')->firstOrFail();
    }

    public function test_deleting_the_latest_consultation_falls_back_to_the_previous_one(): void
    {
        $user    = $this->clinician();
        $patient = $this->patient();

        $this->recordVisit($user, $patient, '2026-07-10');
        $latest = $this->recordVisit($user, $patient, '2026-09-01');

        $this->assertSame('2026-09-01', $patient->fresh()->last_visit_date?->toDateString());

        $latest->delete();

        $this->assertSame('2026-07-10', $patient->fresh()->last_visit_date?->toDateString(),
            'last_visit_date still points at a consultation that no longer exists.');
    }

    public function test_deleting_the_only_consultation_clears_the_date(): void
    {
        $user    = $this->clinician();
        $patient = $this->patient();

        $only = $this->recordVisit($user, $patient, '2026-08-20');
        $this->assertSame('2026-08-20', $patient->fresh()->last_visit_date?->toDateString());

        $only->delete();

        // Null is the honest answer: this patient has no recorded visit, which
        // is what the recall engine needs to hear.
        $this->assertNull($patient->fresh()->last_visit_date,
            'A patient with no consultations left still carries a visit date.');
    }

    public function test_deleting_an_older_consultation_leaves_the_date_alone(): void
    {
        $user    = $this->clinician();
        $patient = $this->patient();

        $older = $this->recordVisit($user, $patient, '2026-07-10');
        $this->recordVisit($user, $patient, '2026-09-01');

        $older->delete();

        $this->assertSame('2026-09-01', $patient->fresh()->last_visit_date?->toDateString());
    }

    /**
     * This one failed on the first run and was worth the run. Both handlers get
     * the SAME cached $consultation->patient, and the column writes are quiet —
     * they go round the model — so after the delete the in-memory Patient still
     * carried the pre-delete date. The restore compared against that stale value,
     * saw no change, and left the column on the older visit. The observer now
     * reads the stored column instead of the loaded model.
     */
    public function test_restoring_a_consultation_puts_its_date_back(): void
    {
        $user    = $this->clinician();
        $patient = $this->patient();

        $this->recordVisit($user, $patient, '2026-07-10');
        $latest = $this->recordVisit($user, $patient, '2026-09-01');

        $latest->delete();
        $this->assertSame('2026-07-10', $patient->fresh()->last_visit_date?->toDateString());

        $latest->restore();

        $this->assertSame('2026-09-01', $patient->fresh()->last_visit_date?->toDateString());
    }

    public function test_two_deletes_in_one_request_end_at_no_visit_date(): void
    {
        // The same stale-model trap from the other direction: the second delete
        // must not compare against the value the first one replaced.
        $user    = $this->clinician();
        $patient = $this->patient();

        $older  = $this->recordVisit($user, $patient, '2026-07-10');
        $latest = $this->recordVisit($user, $patient, '2026-09-01');

        $latest->delete();
        $older->delete();

        $this->assertNull($patient->fresh()->last_visit_date);
    }

    public function test_the_monotonic_forward_rule_on_write_is_untouched(): void
    {
        $user    = $this->clinician();
        $patient = $this->patient();

        $this->recordVisit($user, $patient, '2026-09-01');

        // A backdated entry recorded afterwards must NOT rewind the column.
        $this->recordVisit($user, $patient, '2026-06-15');

        $this->assertSame('2026-09-01', $patient->fresh()->last_visit_date?->toDateString());
    }
}
