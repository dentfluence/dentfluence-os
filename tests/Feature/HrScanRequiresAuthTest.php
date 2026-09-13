<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 13 Sep 2026 — GET and POST /hr/scan were registered OUTSIDE the auth group
 * since the feature shipped. The page prints every active staff member's
 * name, role and raw qr_token, and the POST marks attendance for whatever
 * token it is given; qr_token never rotates. Flagged in the D-1 handbook
 * review on 4 Sep, closed the day before go-live.
 *
 * Route gates have essentially no coverage in this suite — W-3 shipped an
 * un-gated /finance/expenses/scan with 967/967 green, and only a route:list
 * diff caught it. This file exists so that specific mistake cannot recur
 * silently on the two routes that leak the staff roster.
 */
class HrScanRequiresAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_load_the_staff_roster_page(): void
    {
        $this->get('/hr/scan')->assertRedirect();
    }

    public function test_guest_cannot_log_a_scan_and_writes_no_row(): void
    {
        $this->post('/hr/scan', [
            'qr_token' => 'any-token-at-all',
            'type'     => 'entry',
        ])->assertRedirect();

        $this->assertSame(0, DB::table('hr_entry_exit_logs')->count(),
            'an unauthenticated scan must never reach the controller, let alone write attendance');
    }

    public function test_a_signed_in_user_can_still_use_the_door_tablet(): void
    {
        // auth only, NO module gate — front desk holds no HR permission and
        // must still be able to say "I am here" (the M-16 ruling).
        $user = User::factory()->create(['role' => 'front_desk', 'branch_id' => 1, 'is_active' => true]);

        $this->actingAs($user)->get('/hr/scan')->assertOk();
    }
}
