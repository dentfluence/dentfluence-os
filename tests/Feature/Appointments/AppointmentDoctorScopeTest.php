<?php

namespace Tests\Feature\Appointments;

use App\Models\Appointment;
use App\Models\AppSetting;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Appointments\Concerns\InteractsWithAppointments;
use Tests\Feature\Appointments\Concerns\InteractsWithPermissions;
use Tests\TestCase;

/**
 * Doctor appointment scope (2026-08-26).
 *
 * Covers the three things the change actually promises:
 *   1. A doctor's calendar OPENS on his own appointments (own_default).
 *   2. Front desk / owner see the whole branch, unchanged.
 *   3. `own_only` is a real boundary — the toggle cannot widen it, and the
 *      policy 403s a record outside it.
 * Plus the branch leak the policy closes on the way past.
 */
class AppointmentDoctorScopeTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithAppointments;
    use InteractsWithPermissions;

    private function lockScopeTo(string $mode): void
    {
        AppSetting::set('calendar_doctor_scope', $mode, 'calendar');
    }

    // ── 1. own_default: the calendar opens scoped ─────────────────────────

    public function test_doctor_calendar_shows_only_own_appointments_by_default(): void
    {
        $doctor = $this->userForSystemRole('doctor');
        $other  = $this->doctorUser();

        $mine    = $this->makeAppointment(['doctor_id' => $doctor->id]);
        $theirs  = $this->makeAppointment(['doctor_id' => $other->id]);

        $ids = $this->actingAs($doctor)
            ->getJson(route('appointments.index', ['json' => 1]))
            ->assertOk()
            ->json('*.id');

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($theirs->id, $ids);
    }

    public function test_doctor_counters_match_the_grid(): void
    {
        $doctor = $this->userForSystemRole('doctor');
        $other  = $this->doctorUser();

        $this->makeAppointment(['doctor_id' => $doctor->id, 'status' => 'scheduled']);
        $this->makeAppointment(['doctor_id' => $other->id,  'status' => 'scheduled']);
        $this->makeAppointment(['doctor_id' => $other->id,  'status' => 'scheduled']);

        $counts = $this->actingAs($doctor)
            ->getJson(route('appointments.status.counts'))
            ->assertOk()
            ->json();

        $this->assertSame(1, $counts['total']);
        $this->assertSame(1, $counts['scheduled']);
    }

    public function test_doctor_today_queue_is_scoped(): void
    {
        $doctor = $this->userForSystemRole('doctor');
        $other  = $this->doctorUser();

        $mine   = $this->makeAppointment(['doctor_id' => $doctor->id]);
        $theirs = $this->makeAppointment(['doctor_id' => $other->id]);

        $ids = $this->actingAs($doctor)
            ->getJson(route('appointments.queue.today'))
            ->assertOk()
            ->json('appointments.*.id');

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($theirs->id, $ids);
    }

    public function test_doctor_can_toggle_to_the_whole_clinic(): void
    {
        $doctor = $this->userForSystemRole('doctor');
        $other  = $this->doctorUser();

        $mine   = $this->makeAppointment(['doctor_id' => $doctor->id]);
        $theirs = $this->makeAppointment(['doctor_id' => $other->id]);

        $ids = $this->actingAs($doctor)
            ->getJson(route('appointments.index', ['json' => 1, 'all_doctors' => 1]))
            ->assertOk()
            ->json('*.id');

        $this->assertContains($mine->id, $ids);
        $this->assertContains($theirs->id, $ids);
    }

    // ── 2. everyone else is untouched ─────────────────────────────────────

    public function test_front_desk_sees_every_doctor(): void
    {
        $frontDesk = $this->userForSystemRole('front_desk');
        $a = $this->makeAppointment();
        $b = $this->makeAppointment();

        $ids = $this->actingAs($frontDesk)
            ->getJson(route('appointments.index', ['json' => 1]))
            ->assertOk()
            ->json('*.id');

        $this->assertContains($a->id, $ids);
        $this->assertContains($b->id, $ids);
    }

    public function test_owner_named_dr_is_not_scoped_down(): void
    {
        // The owner at Tulip is "Dr. Firke". isDoctor() pattern-matches a "Dr."
        // name prefix, so scoping by NAME instead of ROLE would lock the clinic
        // owner out of his own clinic's calendar. Guard against that regression.
        $owner = $this->userForSystemRole('admin', ['name' => 'Dr. Firke']);
        $a = $this->makeAppointment();
        $b = $this->makeAppointment();

        $ids = $this->actingAs($owner)
            ->getJson(route('appointments.index', ['json' => 1]))
            ->assertOk()
            ->json('*.id');

        $this->assertContains($a->id, $ids);
        $this->assertContains($b->id, $ids);
        $this->assertSame(User::APPT_SCOPE_ALL, $owner->appointmentScope());
    }

    // ── 3. own_only is a real boundary ────────────────────────────────────

    public function test_own_only_ignores_the_all_doctors_toggle(): void
    {
        $this->lockScopeTo(User::APPT_SCOPE_OWN_ONLY);

        $doctor = $this->userForSystemRole('doctor');
        $other  = $this->doctorUser();
        $theirs = $this->makeAppointment(['doctor_id' => $other->id]);

        $ids = $this->actingAs($doctor)
            ->getJson(route('appointments.index', ['json' => 1, 'all_doctors' => 1]))
            ->assertOk()
            ->json('*.id');

        $this->assertNotContains($theirs->id, $ids);
    }

    public function test_own_only_blocks_reading_another_doctors_appointment(): void
    {
        $this->lockScopeTo(User::APPT_SCOPE_OWN_ONLY);

        $doctor = $this->userForSystemRole('doctor');
        $other  = $this->doctorUser();
        $theirs = $this->makeAppointment(['doctor_id' => $other->id]);

        $this->actingAs($doctor)
            ->getJson(route('appointments.quick', $theirs))
            ->assertForbidden();
    }

    public function test_own_only_blocks_writing_another_doctors_appointment(): void
    {
        $this->lockScopeTo(User::APPT_SCOPE_OWN_ONLY);

        $doctor = $this->userForSystemRole('doctor');
        $other  = $this->doctorUser();
        $theirs = $this->makeAppointment(['doctor_id' => $other->id, 'status' => 'scheduled']);

        $this->actingAs($doctor)
            ->patchJson(route('appointments.updateStatus', $theirs), ['status' => 'checkin'])
            ->assertForbidden();

        $this->assertSame('scheduled', $theirs->fresh()->status);
    }

    public function test_own_default_doctor_may_still_open_a_colleagues_appointment(): void
    {
        // own_default is a VIEW default, not a permission. Asserted explicitly
        // so nobody later "tightens" it without a deliberate decision.
        $doctor = $this->userForSystemRole('doctor');
        $other  = $this->doctorUser();
        $theirs = $this->makeAppointment(['doctor_id' => $other->id]);

        $this->actingAs($doctor)
            ->getJson(route('appointments.quick', $theirs))
            ->assertOk();
    }

    // ── 4. cross-branch isolation (BranchScope, not this change) ──────────

    public function test_appointment_from_another_branch_is_not_readable_by_staff(): void
    {
        $otherBranch = Branch::create(['name' => 'Second Branch']);

        $foreignDoctor = $this->doctorUser(['branch_id' => $otherBranch->id]);

        $foreign = Appointment::create([
            'patient_id'       => $this->newPatient(['branch_id' => $otherBranch->id])->id,
            'doctor_id'        => $foreignDoctor->id,
            'branch_id'        => $otherBranch->id,
            // appointments.created_by is NOT NULL with no default.
            'created_by'       => $foreignDoctor->id,
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '11:00',
            'duration_minutes' => 30,
            'type'             => 'consultation',
            'status'           => 'scheduled',
        ]);

        // Non-admin staff: BranchScope filters the record out at route-model
        // binding, so this is a 404 (which also does not leak that the id
        // exists). This behaviour predates the doctor-scope change; asserted
        // here so a future edit to AppointmentPolicy cannot quietly weaken it.
        $this->actingAs($this->userForSystemRole('front_desk'))
            ->getJson(route('appointments.quick', $foreign))
            ->assertNotFound();

        $this->actingAs($this->userForSystemRole('doctor'))
            ->getJson(route('appointments.quick', $foreign))
            ->assertNotFound();
    }

    public function test_admin_may_cross_branches_by_design(): void
    {
        // BranchScope deliberately exempts admin / clinic owner ("admins see
        // every branch") because a multi-branch owner must be able to open
        // another branch's day sheet. AppointmentPolicy mirrors that exemption
        // rather than contradicting it. This test exists so the decision is
        // visible and deliberate, not accidental — if the product ever decides
        // owners must NOT cross branches, change BranchScope and this together.
        $otherBranch   = Branch::create(['name' => 'Third Branch']);
        $foreignDoctor = $this->doctorUser(['branch_id' => $otherBranch->id]);

        $foreign = Appointment::create([
            'patient_id'       => $this->newPatient(['branch_id' => $otherBranch->id])->id,
            'doctor_id'        => $foreignDoctor->id,
            'branch_id'        => $otherBranch->id,
            'created_by'       => $foreignDoctor->id,
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '12:00',
            'duration_minutes' => 30,
            'type'             => 'consultation',
            'status'           => 'scheduled',
        ]);

        $this->actingAs($this->userForSystemRole('admin'))
            ->getJson(route('appointments.quick', $foreign))
            ->assertOk();
    }
}
