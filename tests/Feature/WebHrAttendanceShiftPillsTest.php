<?php

namespace Tests\Feature;

use App\Models\HrAttendance;
use App\Models\HrShift;
use App\Models\HrStaffProfile;
use App\Models\HrStaffShift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M-14 (CEO 9 Sep 2026) — "overtime calculated based on their shift", and the
 * admin/manager must SEE it. The phone got these numbers first; this pins that
 * the web HR > Attendance board shows the same ones, so the two surfaces can
 * never quietly disagree about who was late.
 *
 * The unassigned case is the one that matters most: a staffer with no shift
 * must read as "No shift", never as an on-time day the system invented.
 */
class WebHrAttendanceShiftPillsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-09 21:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function staffWithProfile(string $name): User
    {
        $user = User::factory()->create([
            'name' => $name, 'role' => 'front_desk', 'branch_id' => 1, 'is_active' => true,
        ]);
        HrStaffProfile::create(['user_id' => $user->id]);

        return $user;
    }

    public function test_board_shows_late_and_overtime_against_the_assigned_shift(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'branch_id' => 1, 'is_active' => true]);
        $staff = $this->staffWithProfile('Shift Staffer');

        $shift = HrShift::create([
            'name' => 'Day', 'start_time' => '09:00', 'end_time' => '18:00',
            'branch_id' => 1, 'is_active' => true,
        ]);
        HrStaffShift::create([
            'user_id' => $staff->id, 'shift_id' => $shift->id,
            'effective_from' => '2026-09-01', 'effective_to' => null,
        ]);

        HrAttendance::create([
            'user_id' => $staff->id, 'date' => '2026-09-09',
            'check_in' => '09:35', 'check_out' => '20:10',
            'status' => 'present', 'check_in_method' => 'manual',
        ]);

        $this->actingAs($admin)
            ->get(route('hr.attendance.index'))
            ->assertOk()
            ->assertSee('Late by 35m')
            ->assertSee('OT 2h 10m');
    }

    public function test_staffer_with_no_shift_reads_as_no_shift_not_an_invented_day(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'branch_id' => 1, 'is_active' => true]);
        $staff = $this->staffWithProfile('Unassigned Staffer');

        HrAttendance::create([
            'user_id' => $staff->id, 'date' => '2026-09-09',
            'check_in' => '11:00', 'check_out' => '21:00',
            'status' => 'present', 'check_in_method' => 'manual',
        ]);

        $this->actingAs($admin)
            ->get(route('hr.attendance.index'))
            ->assertOk()
            ->assertSee('No shift')
            ->assertDontSee('Late by')
            ->assertDontSee('OT ');
    }
}
