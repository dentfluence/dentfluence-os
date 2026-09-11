<?php

namespace Tests\Feature\Appointments;

use App\Models\DoctorBlockedSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Appointments\Concerns\InteractsWithAppointments;
use Tests\Feature\Appointments\Concerns\InteractsWithPermissions;
use Tests\TestCase;

/**
 * Calendar live refresh (2026-09-11): GET /appointments/version returns a
 * change token for a date range. The calendar polls it every second and
 * re-reads the range only when it moves, so every kind of write the grid
 * shows must move it, a plain read must not, and it must be scoped exactly
 * like the calendar itself.
 */
class AppointmentCalendarVersionTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithAppointments;
    use InteractsWithPermissions;

    private function token($user, array $extra = []): string
    {
        return $this->actingAs($user)
            ->getJson(route('appointments.version', array_merge([
                'start' => today()->toDateString(),
                'end'   => today()->addDays(7)->toDateString(),
            ], $extra)))
            ->assertOk()
            ->json('v');
    }

    public function test_every_calendar_write_moves_the_token_and_a_read_does_not(): void
    {
        $admin = $this->adminUser();
        $t0    = $this->token($admin);

        $appt = $this->makeAppointment(['appointment_date' => today()->addDay()->toDateString()]);
        $t1   = $this->token($admin);
        $this->assertNotSame($t0, $t1, 'create must move the token');

        // updated_at has one-second resolution; a same-second update would be
        // invisible by design, so step the clock as the real desk would.
        $this->travel(5)->seconds();
        $appt->update(['appointment_time' => '11:30']);
        $t2 = $this->token($admin);
        $this->assertNotSame($t1, $t2, 'reschedule must move the token');

        $this->travel(5)->seconds();
        $appt->update(['status' => 'checkin']);
        $t3 = $this->token($admin);
        $this->assertNotSame($t2, $t3, 'status change must move the token');

        $this->travel(5)->seconds();
        $appt->update(['hidden_from_calendar' => true]);
        $t4 = $this->token($admin);
        $this->assertNotSame($t3, $t4, 'hiding from the calendar must move the token');

        $this->travel(5)->seconds();
        DoctorBlockedSlot::create([
            'doctor_id'  => $appt->doctor_id,
            'block_date' => today()->addDays(2)->toDateString(),
            'start_time' => '14:00',
            'end_time'   => '15:00',
            'block_type' => 'break',
            'created_by' => $admin->id,
        ]);
        $t5 = $this->token($admin);
        $this->assertNotSame($t4, $t5, 'a new block must move the token');

        $this->assertSame($t5, $this->token($admin), 'a read must not move the token');
    }

    public function test_a_write_outside_the_range_does_not_move_the_token(): void
    {
        $admin = $this->adminUser();
        $t0    = $this->token($admin);

        $this->makeAppointment(['appointment_date' => today()->addDays(30)->toDateString()]);

        $this->assertSame($t0, $this->token($admin));
    }

    public function test_token_is_scoped_like_the_calendar(): void
    {
        // The ACTING doctor must carry real module permissions — doctorUser()
        // is a bare legacy role string with no role_id, so the middleware 403s
        // it. userForSystemRole('doctor') is the seeded persona the rest of the
        // doctor-scope suite acts as: own_default, and allowed to toggle wide.
        $me    = $this->userForSystemRole('doctor');
        $other = $this->doctorUser();   // only owns a booking; never acts
        $admin = $this->adminUser();

        $mine   = $this->token($me);
        $whole  = $this->token($me, ['all_doctors' => 1]);
        $desk   = $this->token($admin);

        $this->makeAppointment([
            'doctor_id'        => $other->id,
            'appointment_date' => today()->addDay()->toDateString(),
        ]);

        $this->assertSame($mine, $this->token($me), 'a colleague\'s booking must not move my own-list token');
        $this->assertNotSame($whole, $this->token($me, ['all_doctors' => 1]), 'but it moves the whole-clinic token');
        $this->assertNotSame($desk, $this->token($admin), 'and the front desk sees it');
    }

    public function test_the_range_is_required(): void
    {
        $this->actingAs($this->adminUser())
            ->getJson(route('appointments.version'))
            ->assertStatus(422);
    }
}
