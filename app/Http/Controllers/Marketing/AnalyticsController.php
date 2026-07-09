<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\Campaign;
use App\Models\Marketing\CampaignGoal;
use App\Models\Marketing\MarketingActivityLog;
use App\Models\Marketing\MarketingPost;
use App\Models\Marketing\PlatformConnection;
use App\Models\Marketing\PostVariant;
use App\Models\Marketing\FestivalDate;
use App\Http\Controllers\Marketing\Concerns\ResolvesClinicId;
use App\Services\Marketing\MarketingScoreService;
use Carbon\Carbon;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

/**
 * Marketing Analytics & ROI Dashboard — Phase 6 Intelligence Layer
 *
 * Provides real data for:
 *   - KPI summary cards (posts, campaigns, leads, budget)
 *   - Monthly posts trend (last 6 months bar chart)
 *   - Platform breakdown (posts per platform, donut chart)
 *   - Campaign ROI table (budget vs spend vs goal achievement)
 *   - Intelligence insights (cadence, festival alerts, top campaign)
 *   - Recent activity feed
 */
class AnalyticsController extends Controller
{
    use ResolvesClinicId;

    public function index(): View
    {
        $clinicId = $this->currentClinicId();

        // ── KPI Summary ───────────────────────────────────────────────────
        $kpi = $this->kpiSummary($clinicId);

        // ── Monthly Posts Trend (last 6 months) ───────────────────────────
        $monthlyTrend = $this->monthlyPostsTrend($clinicId);

        // ── Platform Breakdown ────────────────────────────────────────────
        $platformBreakdown = $this->platformBreakdown($clinicId);

        // ── Campaign ROI Table ────────────────────────────────────────────
        $campaignRoi = $this->campaignRoiTable($clinicId);

        // ── ROI Summary (totals across all campaigns) ─────────────────────
        $roiTotals = $this->roiTotals($campaignRoi);

        // ── Intelligence Insights ─────────────────────────────────────────
        $insights = $this->intelligenceInsights($clinicId);

        // ── Recent Activity Feed (last 12 events) ─────────────────────────
        $recentActivity = MarketingActivityLog::where('clinic_id', $clinicId)
            ->with('user')
            ->orderByDesc('occurred_at')
            ->limit(12)
            ->get()
            ->map(fn($log) => [
                'event'       => $log->event,
                'description' => $log->description,
                'user'        => $log->user?->name ?? 'System',
                'time'        => $log->occurred_at?->diffForHumans() ?? '—',
            ])
            ->toArray();

        // ── Marketing Score ───────────────────────────────────────────────
        $scorer    = new MarketingScoreService($clinicId);
        $scoreData = $scorer->breakdown();

        return view('marketing.analytics.index', compact(
            'kpi',
            'monthlyTrend',
            'platformBreakdown',
            'campaignRoi',
            'roiTotals',
            'insights',
            'recentActivity',
            'scoreData'
        ));
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * KPI summary cards:
     *   published_this_month, scheduled, active_campaigns,
     *   total_leads, total_budget_spent, completion_rate
     */
    private function kpiSummary(int $clinicId): array
    {
        $published = MarketingPost::where('clinic_id', $clinicId)
            ->where('status', 'published')
            ->whereMonth('updated_at', now()->month)
            ->whereYear('updated_at', now()->year)
            ->count();

        $publishedLastMonth = MarketingPost::where('clinic_id', $clinicId)
            ->where('status', 'published')
            ->whereMonth('updated_at', now()->subMonth()->month)
            ->whereYear('updated_at', now()->subMonth()->year)
            ->count();

        $publishedTrend = $publishedLastMonth > 0
            ? round(($published - $publishedLastMonth) / $publishedLastMonth * 100)
            : 0;

        $scheduled = MarketingPost::where('clinic_id', $clinicId)
            ->where('status', 'scheduled')
            ->count();

        $activeCampaigns = Campaign::where('clinic_id', $clinicId)
            ->where('status', 'active')
            ->count();

        // Total leads from campaign goals
        $totalLeads = CampaignGoal::whereHas('campaign', fn($q) => $q->where('clinic_id', $clinicId))
            ->where('goal_type', 'leads')
            ->sum('actual_value');

        // Total budget utilised across all campaigns
        $totalBudgetSpent = Campaign::where('clinic_id', $clinicId)
            ->sum('budget_utilized');

        // Completion rate this month
        $failed = MarketingPost::where('clinic_id', $clinicId)
            ->where('status', 'failed')
            ->whereMonth('updated_at', now()->month)
            ->count();

        $completionRate = ($published + $failed) > 0
            ? round($published / ($published + $failed) * 100)
            : 100;

        return [
            'published'          => $published,
            'published_trend'    => ($publishedTrend >= 0 ? '+' : '') . $publishedTrend . '%',
            'trend_positive'     => $publishedTrend >= 0,
            'scheduled'          => $scheduled,
            'active_campaigns'   => $activeCampaigns,
            'total_leads'        => (int) $totalLeads,
            'total_budget_spent' => (float) $totalBudgetSpent,
            'completion_rate'    => $completionRate,
        ];
    }

    /**
     * Posts published per month for the last 6 months.
     * Returns array of ['month' => 'Jun', 'count' => 14, 'pct' => 70].
     */
    private function monthlyPostsTrend(int $clinicId): array
    {
        $months   = [];
        $maxCount = 1;

        for ($i = 5; $i >= 0; $i--) {
            $date  = now()->subMonths($i);
            $count = MarketingPost::where('clinic_id', $clinicId)
                ->where('status', 'published')
                ->whereYear('updated_at', $date->year)
                ->whereMonth('updated_at', $date->month)
                ->count();

            $months[] = [
                'month' => $date->format('M'),
                'year'  => $date->format('Y'),
                'count' => $count,
            ];

            $maxCount = max($maxCount, $count);
        }

        foreach ($months as &$m) {
            $m['pct'] = max(4, (int) round(($m['count'] / $maxCount) * 100));
        }

        return $months;
    }

    /**
     * Posts per platform (from mkt_post_variants where status = 'published').
     */
    private function platformBreakdown(int $clinicId): array
    {
        $platformColors = [
            'instagram'       => '#e1306c',
            'facebook'        => '#1877f2',
            'google_business' => '#4285f4',
            'wordpress'       => '#21759b',
            'whatsapp'        => '#25d366',
        ];

        $platformLabels = [
            'instagram'       => 'Instagram',
            'facebook'        => 'Facebook',
            'google_business' => 'Google Business',
            'wordpress'       => 'WordPress',
            'whatsapp'        => 'WhatsApp',
        ];

        $rows = PostVariant::whereHas('post', fn($q) => $q->where('clinic_id', $clinicId))
            ->where('status', 'published')
            ->select('platform', DB::raw('count(*) as total'))
            ->groupBy('platform')
            ->get();

        $grand = $rows->sum('total') ?: 1;

        return $rows->map(fn($r) => [
            'platform' => $r->platform,
            'label'    => $platformLabels[$r->platform] ?? ucfirst($r->platform),
            'count'    => $r->total,
            'pct'      => round($r->total / $grand * 100),
            'color'    => $platformColors[$r->platform] ?? '#7a1fa2',
        ])->sortByDesc('count')->values()->toArray();
    }

    /**
     * Campaign ROI table — all non-deleted active/paused/completed campaigns.
     */
    private function campaignRoiTable(int $clinicId): array
    {
        return Campaign::where('clinic_id', $clinicId)
            ->with('goals')
            ->whereIn('status', ['active', 'paused', 'completed'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(function ($c) {
                $leads   = $c->goals->firstWhere('goal_type', 'leads');
                $appts   = $c->goals->firstWhere('goal_type', 'appointments');
                $revenue = $c->goals->firstWhere('goal_type', 'revenue');

                $budgetTotal  = (float) ($c->budget_total   ?? 0);
                $budgetSpent  = (float) ($c->budget_utilized ?? 0);
                $budgetPct    = $budgetTotal > 0 ? round($budgetSpent / $budgetTotal * 100) : 0;

                $revenueActual = $revenue ? (float) $revenue->actual_value : 0;
                $roi           = $budgetSpent > 0
                    ? round(($revenueActual - $budgetSpent) / $budgetSpent * 100, 1)
                    : null;

                $costPerLead = ($leads && $leads->actual_value > 0 && $budgetSpent > 0)
                    ? round($budgetSpent / $leads->actual_value, 0)
                    : null;

                return [
                    'id'             => $c->id,
                    'name'           => $c->name,
                    'status'         => $c->status,
                    'channels'       => $c->channels ?? [],
                    'budget_total'   => $budgetTotal,
                    'budget_spent'   => $budgetSpent,
                    'budget_pct'     => $budgetPct,
                    'leads_target'   => $leads  ? (int) $leads->target_value  : 0,
                    'leads_actual'   => $leads  ? (int) $leads->actual_value  : 0,
                    'leads_pct'      => $leads  ? $leads->progressPct()       : 0,
                    'appts_actual'   => $appts  ? (int) $appts->actual_value  : 0,
                    'revenue_actual' => $revenueActual,
                    'roi_pct'        => $roi,
                    'cost_per_lead'  => $costPerLead,
                    'start_date'     => $c->start_date?->format('d M Y'),
                    'end_date'       => $c->end_date?->format('d M Y'),
                ];
            })
            ->toArray();
    }

    /**
     * Aggregate ROI totals across all campaigns.
     */
    private function roiTotals(array $campaigns): array
    {
        $totalBudget       = array_sum(array_column($campaigns, 'budget_total'));
        $totalSpent        = array_sum(array_column($campaigns, 'budget_spent'));
        $totalLeads        = array_sum(array_column($campaigns, 'leads_actual'));
        $totalAppointments = array_sum(array_column($campaigns, 'appts_actual'));
        $totalRevenue      = array_sum(array_column($campaigns, 'revenue_actual'));

        $overallRoi = $totalSpent > 0
            ? round(($totalRevenue - $totalSpent) / $totalSpent * 100, 1)
            : null;

        $avgCostPerLead = $totalLeads > 0 && $totalSpent > 0
            ? round($totalSpent / $totalLeads, 0)
            : null;

        return [
            'total_budget'       => $totalBudget,
            'total_spent'        => $totalSpent,
            'total_leads'        => $totalLeads,
            'total_appointments' => $totalAppointments,
            'total_revenue'      => $totalRevenue,
            'overall_roi'        => $overallRoi,
            'cost_per_lead'      => $avgCostPerLead,
        ];
    }

    /**
     * Intelligence insights — actionable observations based on live data.
     */
    private function intelligenceInsights(int $clinicId): array
    {
        $insights = [];

        // 1. Posting cadence check (target: 20 posts/month)
        $publishedThisMonth = MarketingPost::where('clinic_id', $clinicId)
            ->where('status', 'published')
            ->whereMonth('updated_at', now()->month)
            ->count();

        $dayOfMonth = now()->day;
        $daysInMonth = now()->daysInMonth;
        $paceTarget  = (int) round(20 * $dayOfMonth / $daysInMonth);
        $paceDiff    = $publishedThisMonth - $paceTarget;

        if ($paceDiff < -3) {
            $insights[] = [
                'type'  => 'warning',
                'title' => 'Posting cadence behind',
                'body'  => "You've published {$publishedThisMonth} posts this month. At this pace you'll miss the 20-post target. Schedule " . abs($paceDiff) . " more posts to catch up.",
                'icon'  => 'M12 9v4M12 17h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z',
            ];
        } elseif ($publishedThisMonth >= 20) {
            $insights[] = [
                'type'  => 'success',
                'title' => '20-post target hit!',
                'body'  => "You've hit {$publishedThisMonth} published posts this month — full cadence score.",
                'icon'  => 'M22 11.08V12a10 10 0 11-5.93-9.14M22 4L12 14.01l-3-3',
            ];
        } else {
            $remaining = 20 - $publishedThisMonth;
            $insights[] = [
                'type'  => 'info',
                'title' => 'On track this month',
                'body'  => "{$publishedThisMonth} posts published. {$remaining} more needed to hit the 20-post target.",
                'icon'  => 'M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9z',
            ];
        }

        // 2. Upcoming festival opportunity (next 14 days)
        $upcomingFestival = FestivalDate::where('month', now()->month)
            ->where('day', '>=', now()->day)
            ->where('day', '<=', now()->addDays(14)->day)
            ->where('is_active', true)
            ->orderBy('day')
            ->first();

        if ($upcomingFestival) {
            $festDate  = Carbon::createFromDate(now()->year, $upcomingFestival->month, $upcomingFestival->day);
            $daysUntil = (int) now()->diffInDays($festDate, false);
            if ($daysUntil >= 0) {
                $insights[] = [
                    'type'  => 'opportunity',
                    'title' => "Festival opportunity: {$upcomingFestival->name}",
                    'body'  => "In {$daysUntil} day(s). Plan a themed post in the Brainstorm hub to capitalise on this.",
                    'icon'  => 'M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z',
                ];
            }
        }

        // 3. Best-performing campaign (highest leads)
        $topCampaign = Campaign::where('clinic_id', $clinicId)
            ->where('status', 'active')
            ->with(['goals' => fn($q) => $q->where('goal_type', 'leads')])
            ->get()
            ->sortByDesc(fn($c) => $c->goals->first()?->actual_value ?? 0)
            ->first();

        if ($topCampaign) {
            $topLeads = (int) ($topCampaign->goals->first()?->actual_value ?? 0);
            if ($topLeads > 0) {
                $insights[] = [
                    'type'  => 'success',
                    'title' => "Top campaign: {$topCampaign->name}",
                    'body'  => "Generating {$topLeads} leads. Consider increasing budget allocation to this campaign.",
                    'icon'  => 'M18 20V10M12 20V4M6 20v-6',
                ];
            }
        }

        // 4. Platform connection health
        $connectedCount = PlatformConnection::where('clinic_id', $clinicId)
            ->where('status', 'connected')
            ->count();

        if ($connectedCount === 0) {
            $insights[] = [
                'type'  => 'warning',
                'title' => 'No platforms connected',
                'body'  => 'Connect Instagram, Facebook, or Google Business in Integrations to enable live publishing.',
                'icon'  => 'M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71',
            ];
        }

        return $insights;
    }
}
