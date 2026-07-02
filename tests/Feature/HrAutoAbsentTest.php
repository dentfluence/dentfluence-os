<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\HrStaffProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  HR module — Auto-absent automation
 * ─────────────────────────────────────────────────────────────────────────
 *
 *  WHAT THIS CHECKS (plain language):
 *  The nightly `hr:mark-absent` job marks active staff (who have an HR
 *  profile) as "absent" if they have no attendance record for the day.
 */
class HrAutoAbsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto_absent_marks_active_staff_with_no_attendance(): void
    {
        $user = User::factory()->create(['is_active' => true, 'branch_id' => 1]);

        HrStaffProfile::create([
            'user_id'         => $user->id,
            'employment_type' => 'full_time',
            'joining_date'    => today()->toDateString(),
        ]);

        // Run the nightly auto-absent job.
        $this->artisan('hr:mark-absent')->assertExitCode(0);

        $this->assertDatabaseHas('hr_attendance', [
            'user_id' => $user->id,
            'status'  => 'absent',
        ]);
    }
}
