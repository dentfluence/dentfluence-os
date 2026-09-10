<?php

namespace Tests\Feature\Relationship;

use App\Models\Patient;
use App\Models\TreatmentOpportunity;
use App\Services\Relationship\TodayActionsEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * W-10 step 3 (2026-09-10) — one TreatmentOpportunity, one card.
 *
 * A quoted opportunity that was overdue used to sit under BOTH Opportunities
 * and Pending Estimates, each with its own Stop chasing. Now: quoted →
 * Pending Estimates only; every other open stage → Opportunities only. And
 * Pending Estimates finally carries a due_date, so it splits across the
 * Today and Pending Calls boards like every other date-driven card.
 */
class OneCardPerOpportunityTest extends TestCase
{
    use RefreshDatabase;

    private function opp(string $status, string $date): TreatmentOpportunity
    {
        $patient = Patient::create(['name' => "Opp {$status}", 'phone' => '9' . random_int(100000000, 999999999), 'branch_id' => 1]);

        return TreatmentOpportunity::create([
            'patient_id'     => $patient->id,
            'type'           => 'other',
            'label'          => 'Crown',
            'status'         => $status,
            'follow_up_date' => $date,
        ]);
    }

    private function ids(string $category, ?string $window = null): array
    {
        $rows = app(TodayActionsEngine::class)->generate(dueWindow: $window)[$category] ?? [];

        return array_map(fn ($r) => $r['meta']['id'] ?? null, $rows);
    }

    public function test_a_quoted_opportunity_has_exactly_one_card(): void
    {
        $quoted = $this->opp('quoted', today()->subDays(3)->toDateString());

        $this->assertContains($quoted->id, $this->ids('pending_estimates'));
        $this->assertNotContains($quoted->id, $this->ids('opportunities'), 'listed twice — the duplicate');
    }

    public function test_an_unquoted_opportunity_has_exactly_one_card(): void
    {
        $prospect = $this->opp('prospect', today()->toDateString());

        $this->assertContains($prospect->id, $this->ids('opportunities'));
        $this->assertNotContains($prospect->id, $this->ids('pending_estimates'));
    }

    public function test_pending_estimates_follows_the_due_window(): void
    {
        $dueToday = $this->opp('quoted', today()->toDateString());
        $overdue  = $this->opp('quoted', today()->subDays(2)->toDateString());

        // Today's Actions board
        $this->assertContains($dueToday->id, $this->ids('pending_estimates', 'today'), 'a quote due today used to show nowhere');
        $this->assertNotContains($overdue->id, $this->ids('pending_estimates', 'today'));

        // Pending Calls board
        $this->assertContains($overdue->id, $this->ids('pending_estimates', 'overdue'));
        $this->assertNotContains($dueToday->id, $this->ids('pending_estimates', 'overdue'));

        $row = collect(app(TodayActionsEngine::class)->generate(dueWindow: 'overdue')['pending_estimates'])
            ->firstWhere('meta.id', $overdue->id);
        $this->assertSame(today()->subDays(2)->toDateString(), $row['due_date'], 'the Pending board filters on this key');
    }
}
