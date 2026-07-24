<?php

namespace Tests\Feature\Appointments;

use App\Models\Appointment;
use App\Services\AppointmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Appointments\Concerns\InteractsWithAppointments;
use Tests\TestCase;

/**
 * Slice 9 — canonical appointment read scopes / read-model parity.
 *
 * Proves the consolidated read rules (model scopes + the web calendar/today
 * queries + delegated counts) return the SAME records/values as before.
 */
class AppointmentReadModelParityTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithAppointments;

    // ── Model scopes (the canonical read rules) ───────────────────────────

    public function test_visible_on_calendar_scope_excludes_hidden(): void
    {
        $this->actingAs($this->adminUser());
        $visible = $this->makeAppointment(['status' => 'scheduled']);
        $hidden  = $this->makeAppointment(['status' => 'cancelled', 'hidden_from_calendar' => true]);

        $ids = Appointment::visibleOnCalendar()->pluck('id');
        $this->assertTrue($ids->contains($visible->id));
        $this->assertFalse($ids->contains($hidden->id));
    }

    public function test_active_scope_excludes_cancelled_and_no_show(): void
    {
        $this->actingAs($this->adminUser());
        $scheduled = $this->makeAppointment(['status' => 'scheduled']);
        $cancelled = $this->makeAppointment(['status' => 'cancelled']);
        $noShow    = $this->makeAppointment(['status' => 'no_show']);

        $ids = Appointment::active()->pluck('id');
        $this->assertTrue($ids->contains($scheduled->id));
        $this->assertFalse($ids->contains($cancelled->id));
        $this->assertFalse($ids->contains($noShow->id));
    }

    public function test_for_date_and_in_date_range_scopes(): void
    {
        $this->actingAs($this->adminUser());
        $todayAppt = $this->makeAppointment(['appointment_date' => today()->toDateString()]);
        $future    = $this->makeAppointment(['appointment_date' => today()->addDays(10)->toDateString()]);

        $this->assertSame(
            [$todayAppt->id],
            Appointment::forDate(today()->toDateString())->pluck('id')->all()
        );

        $range = Appointment::inDateRange(today()->toDateString(), today()->addDays(20)->toDateString())->pluck('id');
        $this->assertTrue($range->contains($todayAppt->id));
        $this->assertTrue($range->contains($future->id));
    }

    public function test_for_doctor_scope(): void
    {
        $this->actingAs($this->adminUser());
        $d1 = $this->doctorUser();
        $d2 = $this->doctorUser();
        $a1 = $this->makeAppointment(['doctor_id' => $d1->id]);
        $this->makeAppointment(['doctor_id' => $d2->id]);

        $this->assertSame([$a1->id], Appointment::forDoctor($d1->id)->pluck('id')->all());
    }

    // ── Web read endpoints parity ─────────────────────────────────────────

    public function test_web_calendar_index_excludes_hidden_returns_branch_visible(): void
    {
        $admin   = $this->adminUser();
        $visible = $this->makeAppointment(['appointment_date' => today()->toDateString(), 'status' => 'scheduled']);
        $hidden  = $this->makeAppointment(['appointment_date' => today()->toDateString(), 'status' => 'cancelled', 'hidden_from_calendar' => true]);

        $payload = $this->actingAs($admin)
            ->getJson(route('appointments.index', ['date' => today()->toDateString(), 'json' => 1]))
            ->assertOk()
            ->json();

        $ids = collect($payload)->pluck('id');
        $this->assertTrue($ids->contains($visible->id), 'visible appointment included');
        $this->assertFalse($ids->contains($hidden->id), 'hidden appointment excluded');
    }

    public function test_web_status_counts_delegate_to_service_today_counts(): void
    {
        $admin = $this->adminUser();
        $this->makeAppointment(['appointment_date' => today()->toDateString(), 'status' => 'scheduled']);
        $this->makeAppointment(['appointment_date' => today()->toDateString(), 'status' => 'no_show']);
        $this->makeAppointment(['appointment_date' => today()->toDateString(), 'status' => 'checkin', 'is_walkin' => true]);

        $counts = $this->actingAs($admin)
            ->getJson(route('appointments.status.counts'))
            ->assertOk()
            ->json();

        $service = app(AppointmentService::class)->todayCounts($this->branchId);

        foreach (['total', 'scheduled', 'checkin', 'in_chair', 'done', 'cancelled', 'no_show', 'walkin'] as $key) {
            $this->assertSame($service[$key], $counts[$key], "count '{$key}' matches the canonical service");
        }
        // web-only chair-utilization KPI merged on top (unchanged)
        $this->assertArrayHasKey('chair_utilization_pct', $counts);
    }

    public function test_web_today_queue_returns_todays_branch_appointments(): void
    {
        $admin  = $this->adminUser();
        $today  = $this->makeAppointment(['appointment_date' => today()->toDateString()]);
        $future = $this->makeAppointment(['appointment_date' => today()->addDay()->toDateString()]);

        $resp = $this->actingAs($admin)
            ->getJson(route('appointments.queue.today'))
            ->assertOk()
            ->json();

        $ids = collect($resp['appointments'])->pluck('id');
        $this->assertTrue($ids->contains($today->id));
        $this->assertFalse($ids->contains($future->id));
        $this->assertArrayHasKey('counts', $resp);
    }
}
