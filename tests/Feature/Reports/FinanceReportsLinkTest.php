<?php

namespace Tests\Feature\Reports;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * V.11 (18 Sep 2026) — /finance/reports, the 12-tab set that carries Provider
 * Earnings, had no link anywhere in the app. It is now reachable from Reports,
 * and only for users who may see finance.
 */
class FinanceReportsLinkTest extends TestCase
{
    use RefreshDatabase, BuildsAccessPersonas;

    public function test_reports_page_links_to_finance_reports_for_a_finance_user(): void
    {
        $user = $this->userWithTwoModulePerms('reports', [true, false, false], 'finance', [true, false, false]);

        $this->actingAs($user)->get(route('reports.index'))
            ->assertOk()
            ->assertSee(route('finance.reports'), false);
    }

    public function test_a_user_without_finance_does_not_get_the_link(): void
    {
        $user = $this->userWithModulePerm('reports', true, false, false);

        $this->actingAs($user)->get(route('reports.index'))
            ->assertOk()
            ->assertDontSee(route('finance.reports'), false);
    }
}
