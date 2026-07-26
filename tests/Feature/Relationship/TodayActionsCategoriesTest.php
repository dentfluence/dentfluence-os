<?php

namespace Tests\Feature\Relationship;

use App\Models\Finance\FinanceMembershipPlan;
use App\Models\Finance\FinancePatientMembership;
use App\Models\Lead;
use App\Models\Patient;
use App\Models\TreatmentOpportunity;
use App\Services\Relationship\TodayActionsEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Slice 0.3 — the three previously-dead board categories provably populate,
 * the working-category set is snapshot-locked, and the new category-health
 * record makes silent category death visible.
 *
 * Context: new_enquiries filtered a stage value nothing writes; opportunities
 * and membership_renewals called methods that never existed and died inside
 * the per-category try/catch — invisible for weeks.
 */
class TodayActionsCategoriesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every category key generate() must emit — the current board contract.
     *
     * 'birthdays' was RETIRED 2026-07-26 (CEO): a birthday greeting is not
     * work that needs a human today. 14 categories, not 15 — the reader is
     * preserved for the future WhatsApp automation, but the board no longer
     * carries a card for it.
     */
    private const ALL_CATEGORIES = [
        'new_enquiries', 'lead_followups', 'opportunities', 'recall_calls',
        'follow_up_calls', 'appointment_reminders', 'pending_estimates',
        'membership_renewals', 'lab_ready', 'payment_reminders',
        'wellness_check_yesterday', 'logged_communications',
        'missed_calls_yesterday', 'missed_appointments_yesterday',
    ];

    private function patient(string $name = 'Board Patient'): Patient
    {
        return Patient::create([
            'name'      => $name,
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ]);
    }

    private function generate(): array
    {
        return app(TodayActionsEngine::class)->generate();
    }

    public function test_fresh_lead_appears_under_new_enquiries(): void
    {
        $lead = Lead::create([
            'name'  => 'Fresh Board Lead',
            'phone' => '9' . random_int(100000000, 999999999),
            'stage' => 'new_lead',
        ]);

        $items = $this->generate()['new_enquiries'];

        $this->assertNotEmpty($items, 'new_enquiries is still a dead category');
        $this->assertContains($lead->id, array_column($items, 'lead_id'));
    }

    public function test_overdue_opportunity_appears_with_high_priority(): void
    {
        $patient = $this->patient('Opportunity Patient');

        $opp = TreatmentOpportunity::create([
            'patient_id'      => $patient->id,
            'type'            => 'implant',
            'label'           => 'Implant consult',
            'status'          => 'quoted',
            'follow_up_date'  => now()->subDays(3)->toDateString(),
            'estimated_value' => 45000,
        ]);

        $items = $this->generate()['opportunities'];

        $this->assertNotEmpty($items, 'opportunities is still a dead category');
        $row = collect($items)->firstWhere('meta.id', $opp->id);
        $this->assertNotNull($row, 'seeded opportunity missing from board');
        // is_overdue accessor path (the old isOverdue() call fataled here)
        $this->assertSame('high', $row['priority']);
    }

    public function test_expiring_membership_appears_with_high_priority_inside_week(): void
    {
        $patient = $this->patient('Membership Patient');

        $plan = FinanceMembershipPlan::create([
            'clinic_id' => 1,
            'plan_name' => 'Smile Club',
            'price'     => 2999,
            'duration'  => 'yearly',
        ]);

        $membership = FinancePatientMembership::create([
            'clinic_id'   => 1,
            'patient_id'  => $patient->id,
            'plan_id'     => $plan->id,
            'amount_paid' => 2999,
            'start_date'  => now()->subYear()->toDateString(),
            'end_date'    => now()->addDays(5)->toDateString(),
            'status'      => 'active',
        ]);

        $items = $this->generate()['membership_renewals'];

        $this->assertNotEmpty($items, 'membership_renewals is still a dead category');
        $row = collect($items)->firstWhere('meta.id', $membership->id);
        $this->assertNotNull($row, 'seeded membership missing from board');
        // days_remaining accessor path (the old daysUntilExpiry() call fataled here)
        $this->assertSame('high', $row['priority']);
    }

    public function test_generate_emits_every_category_key(): void
    {
        $groups = $this->generate();

        foreach (self::ALL_CATEGORIES as $category) {
            $this->assertArrayHasKey($category, $groups, "category [{$category}] vanished from generate()");
            $this->assertIsArray($groups[$category]);
        }
    }

    public function test_health_record_reports_all_categories_ok(): void
    {
        Cache::forget(TodayActionsEngine::HEALTH_CACHE_KEY);

        $this->generate();

        $health = Cache::get(TodayActionsEngine::HEALTH_CACHE_KEY);

        $this->assertIsArray($health);
        $this->assertSame(count(self::ALL_CATEGORIES), count($health));
        foreach ($health as $category => $entry) {
            $this->assertTrue($entry['ok'], "category [{$category}] recorded as failing: {$entry['error']}");
            $this->assertNull($entry['error']);
            $this->assertNotNull($entry['last_run']);
        }
    }

    public function test_health_command_exits_zero_when_all_ok_and_nonzero_without_record(): void
    {
        Cache::forget(TodayActionsEngine::HEALTH_CACHE_KEY);
        $this->assertSame(1, Artisan::call('today-actions:health'));

        $this->assertSame(0, Artisan::call('today-actions:health', ['--fresh' => true]));
    }
}
