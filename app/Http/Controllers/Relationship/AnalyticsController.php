<?php

namespace App\Http\Controllers\Relationship;

use App\Http\Controllers\Controller;
use App\Models\Relationship;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * AnalyticsController — Phase 6, Relationship Engine
 *
 * Overview dashboard for relationship health and pipeline metrics.
 * All heavy DB queries are cached for 1 hour (configurable in relationship_score.php).
 *
 * Metrics:
 *   1. Relationship Growth       — new relationships per month, last 6 months
 *   2. Lead Conversion Rate      — leads converted to patients (%)
 *   3. Recall Success Rate       — recalls that resulted in an appointment (%)
 *   4. Average Lifetime Value    — avg revenue per patient relationship
 *   5. Score Distribution        — how many are 80+, 60–79, <60
 *   6. Staff KPIs                — calls logged, tasks completed, leads converted per user
 *
 * Route: GET /relationship/analytics
 */
class AnalyticsController extends Controller
{
    protected int $cacheTtl;

    public function __construct()
    {
        $this->cacheTtl = (int) config('relationship_score.cache_ttl', 3600);
    }

    // ─────────────────────────────────────────────────────────────────────────

    public function index()
    {
        return view('relationship.analytics.index', [
            'growth'          => $this->relationshipGrowth(),
            'conversion'      => $this->leadConversionRate(),
            'recallSuccess'   => $this->recallSuccessRate(),
            'avgLifetimeValue'=> $this->avgLifetimeValue(),
            'scoreDistrib'    => $this->scoreDistribution(),
            'staffKpis'       => $this->staffKpis(),
            'totalRelations'  => $this->totalRelationships(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Metric methods — all cached
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * New relationships per month for the last 6 months.
     * Returns: [ ['month' => 'Jan 26', 'count' => 12], ... ]
     */
    protected function relationshipGrowth(): array
    {
        return Cache::remember('rel_analytics_growth', $this->cacheTtl, function () {
            $months = [];
            for ($i = 5; $i >= 0; $i--) {
                $date  = now()->subMonths($i);
                $start = $date->copy()->startOfMonth();
                $end   = $date->copy()->endOfMonth();

                $months[] = [
                    'month' => $date->format('M y'),
                    'count' => DB::table('relationships')
                        ->whereBetween('created_at', [$start, $end])
                        ->count(),
                ];
            }
            return $months;
        });
    }

    /**
     * Lead → Patient conversion rate (%).
     * Returns: [ 'total' => 240, 'converted' => 86, 'rate' => 35.8 ]
     */
    protected function leadConversionRate(): array
    {
        return Cache::remember('rel_analytics_conversion', $this->cacheTtl, function () {
            $total     = DB::table('leads')->count();
            $converted = DB::table('leads')->where('stage', 'converted')->count();
            $rate      = $total > 0 ? round($converted / $total * 100, 1) : 0;

            return compact('total', 'converted', 'rate');
        });
    }

    /**
     * Recall success rate — recalls that resulted in an appointment booking.
     * Returns: [ 'total' => 150, 'successful' => 72, 'rate' => 48.0 ]
     */
    protected function recallSuccessRate(): array
    {
        return Cache::remember('rel_analytics_recall', $this->cacheTtl, function () {
            $total = DB::table('communication_queue')
                ->where('source_engine', 'recall')
                ->count();

            $successful = DB::table('communication_queue')
                ->where('source_engine', 'recall')
                ->whereIn('outcome', ['appointment_booked', 'completed', 'success'])
                ->count();

            $rate = $total > 0 ? round($successful / $total * 100, 1) : 0;

            return compact('total', 'successful', 'rate');
        });
    }

    /**
     * Average lifetime value per patient relationship (total invoiced / patient count).
     * Returns: [ 'avg' => 18450, 'total_revenue' => 4500000, 'patient_count' => 244 ]
     */
    protected function avgLifetimeValue(): array
    {
        return Cache::remember('rel_analytics_ltv', $this->cacheTtl, function () {
            $totalRevenue = DB::table('invoices')
                ->where('status', 'paid')
                ->sum('total_amount');

            $patientCount = DB::table('patients')->count();

            $avg = $patientCount > 0 ? round($totalRevenue / $patientCount) : 0;

            return [
                'avg'           => $avg,
                'total_revenue' => (int) $totalRevenue,
                'patient_count' => $patientCount,
            ];
        });
    }

    /**
     * Relationship score distribution by band (High/Medium/Low).
     * Returns: [ ['label' => 'High (80–100)', 'count' => 45, 'color' => '#1a7a45'], ... ]
     */
    protected function scoreDistribution(): array
    {
        return Cache::remember('rel_analytics_score_distrib', $this->cacheTtl, function () {
            $bands  = config('relationship_score.bands', []);
            $result = [];

            foreach ($bands as $bandKey => $band) {
                $count = DB::table('relationships')
                    ->whereBetween('score', [$band['min'], $band['max']])
                    ->count();

                $result[] = [
                    'key'   => $bandKey,
                    'label' => $band['label'],
                    'count' => $count,
                    'color' => $band['color'],
                ];
            }

            return $result;
        });
    }

    /**
     * Staff KPIs: calls logged, tasks completed, leads converted — per user.
     * Returns: array of user rows sorted by calls_logged desc.
     */
    protected function staffKpis(): array
    {
        return Cache::remember('rel_analytics_staff_kpis', $this->cacheTtl, function () {
            // Calls/comms logged per user (last 30 days)
            $since = now()->subDays(30);

            $comms = DB::table('communication_queue')
                ->whereBetween('created_at', [$since, now()])
                ->whereNotNull('assigned_to')
                ->select('assigned_to', DB::raw('COUNT(*) as calls_logged'))
                ->groupBy('assigned_to')
                ->pluck('calls_logged', 'assigned_to');

            $tasks = DB::table('tasks')
                ->whereBetween('updated_at', [$since, now()])
                ->where('status', 'completed')
                ->whereNotNull('assigned_to')
                ->select('assigned_to', DB::raw('COUNT(*) as tasks_done'))
                ->groupBy('assigned_to')
                ->pluck('tasks_done', 'assigned_to');

            $conversions = DB::table('leads')
                ->where('stage', 'converted')
                ->whereBetween('updated_at', [$since, now()])
                ->whereNotNull('assigned_to')
                ->select('assigned_to', DB::raw('COUNT(*) as leads_converted'))
                ->groupBy('assigned_to')
                ->pluck('leads_converted', 'assigned_to');

            // Merge into user rows
            $allUserIds = collect($comms->keys())
                ->merge($tasks->keys())
                ->merge($conversions->keys())
                ->unique();

            $users = DB::table('users')
                ->whereIn('id', $allUserIds)
                ->select('id', 'name', 'role')
                ->get()
                ->keyBy('id');

            $rows = [];
            foreach ($allUserIds as $userId) {
                $user   = $users[$userId] ?? null;
                $rows[] = [
                    'user_id'         => $userId,
                    'name'            => $user?->name ?? 'Unknown',
                    'role'            => $user?->role ?? '—',
                    'calls_logged'    => (int) ($comms[$userId]    ?? 0),
                    'tasks_done'      => (int) ($tasks[$userId]    ?? 0),
                    'leads_converted' => (int) ($conversions[$userId] ?? 0),
                ];
            }

            usort($rows, fn($a, $b) => $b['calls_logged'] - $a['calls_logged']);

            return $rows;
        });
    }

    /**
     * Simple total relationship count (not cached — tiny query).
     */
    protected function totalRelationships(): int
    {
        return DB::table('relationships')->count();
    }
}
