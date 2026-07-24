<?php

namespace Tests\Feature\Appointments;

use App\Modules\Huddle\Services\HuddleAggregationService;
use App\Services\AppointmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Appointments\Concerns\InteractsWithAppointments;
use Tests\TestCase;

/**
 * Slice 2 — Characterization: READ CONTRACTS.
 *
 * Locks the shapes and values of the read models the dashboards, calendar and
 * mobile app depend on: AppointmentService::todayCounts() / filteredQuery(),
 * the API today + index envelopes, and the Huddle appointment stat block
 * (whose no_show counter was fixed in Slice 1 / M4).
 */
class AppointmentReadContractCharacterizationTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithAppointments;

    public function test_today_counts_keys_and_values(): void
    {
        $this->makeAppointment(['status' => 'scheduled']);
        $this->makeAppointment(['status' => 'no_show']);
        $this->makeAppointment(['status' => 'checkin', 'is_walkin' => true]);

        $counts = app(AppointmentService::class)->todayCounts($this->branchId);

        $expected = ['total', 'scheduled', 'checkin', 'in_chair', 'done', 'cancelled', 'no_show', 'walkin'];
        sort($expected);
        $actual = array_keys($counts);
        sort($actual);
        $this->assertSame($expected, $actual, 'todayCounts() key set changed');

        $this->assertSame(3, $counts['total']);
        $this->assertSame(1, $counts['scheduled']);
        $this->assertSame(1, $counts['no_show']);
        $this->assertSame(1, $counts['walkin']);
    }

    public function test_filtered_query_today_scope_only_returns_today(): void
    {
        $this->makeAppointment(['appointment_date' => today()->toDateString()]);
        $this->makeAppointment(['appointment_date' => today()->addDay()->toDateString()]);

        $count = app(AppointmentService::class)
            ->filteredQuery($this->branchId, ['scope' => 'today'])
            ->count();

        $this->assertSame(1, $count);
    }

    public function test_api_today_returns_envelope_with_counts(): void
    {
        $this->makeAppointment(['status' => 'scheduled']);
        Sanctum::actingAs($this->adminUser(), ['*']);

        $this->getJson('/api/v1/appointments/today')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success', 'message', 'data',
                'meta' => ['counts' => ['total', 'scheduled', 'checkin', 'in_chair', 'done', 'cancelled', 'no_show', 'walkin']],
            ]);
    }

    public function test_api_index_returns_pagination_envelope(): void
    {
        $this->makeAppointment();
        Sanctum::actingAs($this->adminUser(), ['*']);

        $this->getJson('/api/v1/appointments')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success', 'message', 'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);
    }

    public function test_huddle_appointment_stats_expose_no_show_count(): void
    {
        // M4 lock: the Huddle board no-show stat reflects real data (was hard 0).
        $this->makeAppointment(['status' => 'no_show']);
        $this->makeAppointment(['status' => 'scheduled']);

        $stats = app(HuddleAggregationService::class)->computeStats(0, $this->branchId);

        $this->assertArrayHasKey('no_show', $stats['appointments']);
        $this->assertSame(1, $stats['appointments']['no_show']);
        $this->assertSame(1, $stats['appointments']['confirmed']); // 'scheduled'
    }
}
