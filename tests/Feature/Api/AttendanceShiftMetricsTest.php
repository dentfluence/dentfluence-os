<?php

namespace Tests\Feature\Api;

use App\Models\HrShift;
use App\Models\HrStaffShift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * M-14 (CEO 9 Sep 2026) — late arrival and overtime, measured against the
 * shift the staffer is assigned. hr_shifts / hr_staff_shifts have existed
 * since 18 June and nothing had ever read them for attendance.
 *
 * Each case sits on a way the rule could be quietly undone: an unassigned
 * staffer must NOT get a guessed working day, someone still in the clinic
 * must NOT be "in overtime", and an overnight shift must not read as a
 * negative day.
 */
class AttendanceShiftMetricsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-09 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function staff(): User
    {
        return User::factory()->create(['role' => 'front_desk', 'branch_id' => 1, 'is_active' => true]);
    }

    private function assignShift(User $u, string $start, string $end): HrShift
    {
        $shift = HrShift::create([
            'name' => 'Test Shift', 'start_time' => $start, 'end_time' => $end,
            'branch_id' => 1, 'is_active' => true,
        ]);
        HrStaffShift::create([
            'user_id' => $u->id, 'shift_id' => $shift->id,
            'effective_from' => '2026-09-01', 'effective_to' => null,
        ]);

        return $shift;
    }

    public function test_no_shift_assigned_means_no_numbers_not_a_guessed_day(): void
    {
        Sanctum::actingAs($this->staff(), ['*']);
        $d = $this->getJson('/api/v1/hr/attendance/today')->assertOk()->json('data');

        $this->assertNull($d['shift_name']);
        $this->assertNull($d['expected_minutes']);
        $this->assertNull($d['overtime_minutes']);
        $this->assertNull($d['late_minutes']);
    }

    public function test_on_time_arrival_inside_the_grace_is_not_late(): void
    {
        $me = $this->staff();
        $this->assignShift($me, '09:00', '18:00');
        Sanctum::actingAs($me, ['*']);

        Carbon::setTestNow(Carbon::parse('2026-09-09 09:07:00'));
        $d = $this->postJson('/api/v1/hr/attendance/check-in')->assertOk()->json('data');

        $this->assertSame(7, $d['late_minutes']);
        $this->assertFalse($d['is_late'], '7 minutes is inside the 10-minute grace');
        $this->assertSame(540, $d['expected_minutes']);
        $this->assertNull($d['overtime_minutes'], 'still in the clinic — not overtime yet');
    }

    public function test_late_arrival_and_overtime_are_measured_against_the_shift(): void
    {
        $me = $this->staff();
        $this->assignShift($me, '09:00', '18:00');
        Sanctum::actingAs($me, ['*']);

        Carbon::setTestNow(Carbon::parse('2026-09-09 09:35:00'));
        $this->postJson('/api/v1/hr/attendance/check-in')->assertOk();

        Carbon::setTestNow(Carbon::parse('2026-09-09 20:10:00'));
        $d = $this->postJson('/api/v1/hr/attendance/check-out')->assertOk()->json('data');

        $this->assertSame(35, $d['late_minutes']);
        $this->assertTrue($d['is_late']);
        $this->assertSame(130, $d['overtime_minutes'], '18:00 → 20:10');
        $this->assertSame(635, $d['worked_minutes'], '09:35 → 20:10');
        $this->assertSame(0, $d['early_leave_minutes']);
    }

    public function test_leaving_early_is_reported_and_is_never_negative_overtime(): void
    {
        $me = $this->staff();
        $this->assignShift($me, '09:00', '18:00');
        Sanctum::actingAs($me, ['*']);

        Carbon::setTestNow(Carbon::parse('2026-09-09 09:00:00'));
        $this->postJson('/api/v1/hr/attendance/check-in')->assertOk();
        Carbon::setTestNow(Carbon::parse('2026-09-09 16:30:00'));
        $d = $this->postJson('/api/v1/hr/attendance/check-out')->assertOk()->json('data');

        $this->assertSame(0, $d['overtime_minutes']);
        $this->assertSame(90, $d['early_leave_minutes']);
    }

    public function test_an_overnight_shift_does_not_produce_a_negative_day(): void
    {
        $me = $this->staff();
        $this->assignShift($me, '21:00', '05:00');   // 8 hours across midnight
        Sanctum::actingAs($me, ['*']);

        Carbon::setTestNow(Carbon::parse('2026-09-09 21:00:00'));
        $d = $this->postJson('/api/v1/hr/attendance/check-in')->assertOk()->json('data');

        $this->assertSame(480, $d['expected_minutes']);
        $this->assertSame(0, $d['late_minutes']);
    }
}
