<?php

namespace Tests\Feature\Api;

use App\Models\HrAttendance;
use App\Models\HrEntryExitLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * M-16 (Android V1.1) — a staffer marks their own attendance from the phone.
 *
 * Pinned rules: self only (no user_id is read from the body), one check-in
 * and one check-out per day, check-out needs a check-in, the same two tables
 * the QR scan writes so web reports keep reading one source, and no HR
 * permission is needed to mark yourself.
 */
class HrAttendanceSelfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-08 09:32:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function receptionist(): User
    {
        // front_desk holds no HR module permission by default — and must not need one here.
        return User::factory()->create(['role' => 'front_desk', 'branch_id' => 1, 'is_active' => true]);
    }

    public function test_check_in_then_check_out_writes_the_same_rows_the_qr_scan_writes(): void
    {
        $me = $this->receptionist();
        Sanctum::actingAs($me, ['*']);

        $this->getJson('/api/v1/hr/attendance/today')->assertOk()
            ->assertJsonPath('data.can_check_in', true)
            ->assertJsonPath('data.can_check_out', false)
            ->assertJsonPath('data.check_in', null);

        $this->postJson('/api/v1/hr/attendance/check-in')->assertOk()
            ->assertJsonPath('data.check_in', '09:32')
            ->assertJsonPath('data.status', 'present')
            ->assertJsonPath('data.can_check_in', false)
            ->assertJsonPath('data.can_check_out', true);

        $att = HrAttendance::where('user_id', $me->id)->whereDate('date', '2026-09-08')->firstOrFail();
        $this->assertSame('present', $att->status);
        $this->assertSame('manual', $att->check_in_method);
        $this->assertSame(1, HrEntryExitLog::where('user_id', $me->id)->where('type', 'entry')->count());

        Carbon::setTestNow(Carbon::parse('2026-09-08 21:10:00'));
        $this->postJson('/api/v1/hr/attendance/check-out')->assertOk()
            ->assertJsonPath('data.check_out', '21:10')
            ->assertJsonPath('data.can_check_out', false);

        $this->assertSame(1, HrEntryExitLog::where('user_id', $me->id)->where('type', 'exit')->count());
        $this->assertSame(1, HrAttendance::where('user_id', $me->id)->count(), 'one row per staff per day');
    }

    public function test_a_second_check_in_and_a_check_out_without_check_in_are_refused(): void
    {
        $me = $this->receptionist();
        Sanctum::actingAs($me, ['*']);

        $this->postJson('/api/v1/hr/attendance/check-out')->assertStatus(422);

        $this->postJson('/api/v1/hr/attendance/check-in')->assertOk();
        $this->postJson('/api/v1/hr/attendance/check-in')->assertStatus(422);

        $this->assertSame(1, HrEntryExitLog::where('user_id', $me->id)->count());
    }

    public function test_the_body_cannot_mark_somebody_else(): void
    {
        $me    = $this->receptionist();
        $other = User::factory()->create(['role' => 'assistant', 'branch_id' => 1, 'is_active' => true]);
        Sanctum::actingAs($me, ['*']);

        $this->postJson('/api/v1/hr/attendance/check-in', ['user_id' => $other->id])->assertOk();

        $this->assertSame(0, HrAttendance::where('user_id', $other->id)->count());
        $this->assertSame(1, HrAttendance::where('user_id', $me->id)->count());
    }
}
