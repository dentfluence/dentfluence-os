<?php

namespace Tests\Unit;

use App\Services\Analytics\ReportMetricsService;
use Carbon\Carbon;
use Tests\TestCase;

/** Dashboard periods: Today / This Month / This Quarter / This FY, and their like-for-like comparisons. */
class DashboardPeriodRangeTest extends TestCase
{
    private ReportMetricsService $m;

    protected function setUp(): void
    {
        parent::setUp();
        $this->m = app(ReportMetricsService::class);
        Carbon::setTestNow(Carbon::create(2026, 9, 24, 15, 0, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function assertRange(string $from, string $to, array $range): void
    {
        $this->assertSame($from, $range[0]->format('Y-m-d H:i:s'));
        $this->assertSame($to, $range[1]->format('Y-m-d H:i:s'));
    }

    public function test_named_periods_run_from_their_start_to_today(): void
    {
        $this->assertRange('2026-09-24 00:00:00', '2026-09-24 23:59:59', $this->m->resolveRange('today'));
        $this->assertRange('2026-09-01 00:00:00', '2026-09-24 23:59:59', $this->m->resolveRange('month'));
        $this->assertRange('2026-07-01 00:00:00', '2026-09-24 23:59:59', $this->m->resolveRange('quarter'));
        $this->assertRange('2026-04-01 00:00:00', '2026-09-24 23:59:59', $this->m->resolveRange('fy'));
    }

    public function test_fy_before_april_belongs_to_the_previous_year(): void
    {
        Carbon::setTestNow(Carbon::create(2027, 2, 10));
        $this->assertRange('2026-04-01 00:00:00', '2027-02-10 23:59:59', $this->m->resolveRange('fy'));
    }

    public function test_comparisons_are_like_for_like(): void
    {
        foreach ([
            'today'   => ['2026-09-23 00:00:00', '2026-09-23 23:59:59'],
            'month'   => ['2026-08-01 00:00:00', '2026-08-24 23:59:59'],
            'quarter' => ['2026-04-01 00:00:00', '2026-06-24 23:59:59'],
            'fy'      => ['2025-04-01 00:00:00', '2025-09-24 23:59:59'],
        ] as $period => [$f, $t]) {
            [$from, $to] = $this->m->resolveRange($period);
            $this->assertRange($f, $t, $this->m->previousRange($period, $from, $to));
        }
    }

    public function test_month_comparison_does_not_overflow_on_the_31st(): void
    {
        Carbon::setTestNow(Carbon::create(2027, 3, 31));
        [$from, $to] = $this->m->resolveRange('month');
        $this->assertRange('2027-02-01 00:00:00', '2027-02-28 23:59:59', $this->m->previousRange('month', $from, $to));
    }

    public function test_custom_range_is_swapped_when_reversed(): void
    {
        $this->assertRange('2026-09-01 00:00:00', '2026-09-10 23:59:59', $this->m->resolveRange('custom', '2026-09-10', '2026-09-01'));
    }

    public function test_rolling_periods_still_resolve_for_old_links_and_the_api(): void
    {
        $this->assertRange('2026-08-25 00:00:00', '2026-09-24 23:59:59', $this->m->resolveRange(null));
    }
}
