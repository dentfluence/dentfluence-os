<?php

namespace Tests\Feature\Patients;

use App\Models\Consultation;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Services\Patient\PatientJourneyService;
use App\Services\Relationship\UnifiedTimelineService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Patients Phase 4 · Slice 2 — Journey Timeline.
 *
 * Feature layer: the timeline endpoint (JSON html + cursor), event content,
 * ordering, and the Amendment-1 treatment decision events.
 * Unit layer: PatientJourneyService group filtering, permission filtering,
 * and cursor pagination against a stubbed aggregator (deterministic).
 */
class JourneyTimelineTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['branch_id' => 1, 'role' => 'admin', 'role_id' => null]);
    }

    private function patient(string $name = 'Journey Patient'): Patient
    {
        return Patient::create([
            'name'      => $name,
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ]);
    }

    private function consultation(Patient $patient, Carbon $date): Consultation
    {
        return Consultation::create([
            'patient_id'        => $patient->id,
            'doctor_id'         => $this->admin->id,
            'branch_id'         => 1,
            'status'            => 'completed',
            'consultation_date' => $date,
        ]);
    }

    // ── Endpoint ──────────────────────────────────────────────────────────────

    public function test_timeline_endpoint_returns_registration_event(): void
    {
        $patient = $this->patient();

        $resp = $this->actingAs($this->admin)
            ->getJson(route('patients.timeline', $patient));

        $resp->assertOk()
            ->assertJsonStructure(['html', 'next_cursor', 'count']);

        $this->assertStringContainsString('Patient registered', $resp->json('html'));
        $this->assertNull($resp->json('next_cursor')); // one page only
    }

    public function test_timeline_orders_newest_first(): void
    {
        $patient = $this->patient();
        $this->consultation($patient, now()->addDay()); // newer than registration

        $html = $this->actingAs($this->admin)
            ->getJson(route('patients.timeline', $patient))
            ->json('html');

        $consultPos  = strpos($html, 'Consultation');
        $registerPos = strpos($html, 'Patient registered');

        $this->assertNotFalse($consultPos);
        $this->assertNotFalse($registerPos);
        $this->assertLessThan($registerPos, $consultPos, 'Newer consultation should render before registration');
    }

    public function test_group_filter_limits_events(): void
    {
        $patient = $this->patient();
        $this->consultation($patient, now()->subDay());

        // Clinical filter: consultation yes, registration (milestone) no.
        $html = $this->actingAs($this->admin)
            ->getJson(route('patients.timeline', $patient) . '?group=clinical')
            ->json('html');

        $this->assertStringContainsString('Consultation', $html);
        $this->assertStringNotContainsString('Patient registered', $html);
    }

    public function test_timeline_for_merged_patient_is_404(): void
    {
        $master = $this->patient('Master');
        $merged = $this->patient('Merged Away');
        $merged->forceFill(['merged_into_id' => $master->id])->save();

        $this->actingAs($this->admin)
            ->getJson(route('patients.timeline', $merged))
            ->assertNotFound();
    }

    // ── Amendment 1 — treatment decision events ───────────────────────────────

    public function test_treatment_accepted_rejected_and_deferred_events(): void
    {
        $patient = $this->patient();
        $consult = $this->consultation($patient, now()->subDays(40));

        $base = [
            'consultation_id' => $consult->id,
            'patient_id'      => $patient->id,
            'plan_type'       => 'best',
            'rows'            => [],
        ];

        TreatmentPlan::create($base + [
            'plan_name'   => 'Accepted Plan',
            'plan_date'   => now()->subDays(30),
            'status'      => 'ongoing',
            'accepted_at' => now()->subDays(25),
        ]);
        TreatmentPlan::create($base + [
            'plan_name' => 'Rejected Plan',
            'plan_date' => now()->subDays(30),
            'status'    => 'cancelled',
        ]);
        TreatmentPlan::create($base + [
            'plan_name' => 'Stale Plan',
            'plan_date' => now()->subDays(30),
            'status'    => 'pending',
        ]);

        $events = app(PatientJourneyService::class)->for($patient)['events'];
        $titles = $events->pluck('title')->implode(' | ');

        $this->assertStringContainsString('Treatment plan accepted — Accepted Plan', $titles);
        $this->assertStringContainsString('Treatment plan rejected — Rejected Plan', $titles);
        $this->assertStringContainsString('Treatment plan pending decision — Stale Plan', $titles);
        $this->assertStringContainsString('Treatment plan created — Accepted Plan', $titles);
    }

    // ── Unit: facade behaviour against a stubbed aggregator ──────────────────

    private function stubbedJourney(Collection $fixed): PatientJourneyService
    {
        $stub = new class($fixed) extends UnifiedTimelineService {
            public function __construct(private Collection $fixed)
            {
            }

            public function forPatient(Patient $patient, ?Carbon $before = null): Collection
            {
                return $this->fixed
                    ->when($before, fn ($c) => $c->filter(fn ($e) => $e['date']->lt($before)))
                    ->sortByDesc('date')
                    ->values();
            }
        };

        return new PatientJourneyService($stub);
    }

    private function fakeEvent(int $daysAgo, string $type, string $group, string $permission): array
    {
        return [
            'date'        => now()->subDays($daysAgo),
            'type'        => $type,
            'icon_type'   => $type,
            'title'       => ucfirst($type) . ' ' . $daysAgo,
            'description' => null,
            'actor'       => null,
            'meta'        => null,
            'group'       => $group,
            'permission'  => $permission,
            'link'        => null,
            'color'       => 'slate',
        ];
    }

    public function test_permission_filtering_drops_events_viewer_cannot_see(): void
    {
        $patient = $this->patient();
        $journey = $this->stubbedJourney(collect([
            $this->fakeEvent(1, 'payment',      'financial', 'billing.view'),
            $this->fakeEvent(2, 'consultation', 'clinical',  'patients.view'),
            $this->fakeEvent(3, 'lab',          'clinical',  'lab.view'),
        ]));

        // Viewer with patients access only — no billing, no lab.
        $viewer = new class extends User {
            public function canAccess(string $module, string $action = 'view'): bool
            {
                return $module === 'patients';
            }
        };

        $events = $journey->for($patient, $viewer)['events'];

        $this->assertCount(1, $events);
        $this->assertSame('consultation', $events->first()['type']);
    }

    public function test_group_filter_and_cursor_pagination(): void
    {
        $patient = $this->patient();
        $fixed   = collect(range(1, 30))
            ->map(fn ($i) => $this->fakeEvent($i, 'consultation', 'clinical', 'patients.view'));
        $journey = $this->stubbedJourney($fixed);

        // Page 1: default page size, cursor present.
        $page1 = $journey->for($patient, null, 'clinical');
        $this->assertCount(PatientJourneyService::PAGE_SIZE, $page1['events']);
        $this->assertNotNull($page1['next_cursor']);

        // Page 2: strictly older, no overlap, cursor exhausted.
        $page2 = $journey->for($patient, null, 'clinical', Carbon::parse($page1['next_cursor']));
        $this->assertCount(30 - PatientJourneyService::PAGE_SIZE, $page2['events']);
        $this->assertNull($page2['next_cursor']);

        $newestOfPage2 = $page2['events']->first()['date'];
        $oldestOfPage1 = $page1['events']->last()['date'];
        $this->assertTrue($newestOfPage2->lt($oldestOfPage1));

        // Non-matching group returns nothing.
        $this->assertCount(0, $journey->for($patient, null, 'financial')['events']);
    }
}
