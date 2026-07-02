<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Models\CommunicationQueue;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * KpiController — Phase 5 Communication OS
 *
 * Admin-only KPI dashboard. Full complexity is intentional (per UI rule).
 * All metrics are scoped to the selected date range (default: last 30 days).
 *
 * Metrics computed:
 *  - Lead → Appointment conversion %
 *  - Lead → Treatment (Won) conversion %
 *  - Avg response time by channel
 *  - SLA breach rate %
 *  - Staff performance (calls logged/day)
 *  - Pipeline ₹ by stage
 *  - Won vs Lost count + reasons
 *  - Daily comm volume trend (chart)
 */
class KpiController extends Controller
{
    public function index(Request $request)
    {
        // ── Date range ──────────────────────────────────────────────────
        $period  = $request->get('period', '30');    // days
        $from    = $request->get('from')
            ? Carbon::parse($request->get('from'))->startOfDay()
            : now()->subDays((int)$period)->startOfDay();
        $to      = $request->get('to')
            ? Carbon::parse($request->get('to'))->endOfDay()
            : now()->endOfDay();

        // ── Lead conversions ─────────────────────────────────────────────
        $totalLeads = Lead::whereBetween('created_at', [$from, $to])->count();

        $apptLeads  = Lead::whereBetween('created_at', [$from, $to])
            ->whereIn('stage', ['appointment', 'consultation', 'plan_given', 'converted'])
            ->count();

        $wonLeads   = Lead::whereBetween('created_at', [$from, $to])
            ->where('stage', 'converted')
            ->count();

        $lostLeads  = Lead::whereBetween('created_at', [$from, $to])
            ->where('stage', 'lost')
            ->count();

        $leadToAppt      = $totalLeads > 0 ? round($apptLeads  / $totalLeads * 100, 1) : 0;
        $leadToTreatment = $totalLeads > 0 ? round($wonLeads    / $totalLeads * 100, 1) : 0;

        // ── Avg response time (minutes) by channel ───────────────────────
        // Proxy: avg minutes between created_at and first last_attempt_at for inbound comms
        $avgResponseByChannel = CommunicationQueue::whereBetween('created_at', [$from, $to])
            ->where('source_engine', 'inbound')
            ->whereNotNull('last_attempt_at')
            ->where('attempt_count', '>', 0)
            ->select(
                'channel',
                DB::raw('ROUND(AVG(TIMESTAMPDIFF(MINUTE, created_at, last_attempt_at)), 0) as avg_minutes'),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('channel')
            ->orderBy('avg_minutes')
            ->get();

        // ── SLA breach rate ───────────────────────────────────────────────
        $totalWithSla   = CommunicationQueue::whereBetween('created_at', [$from, $to])
            ->whereNotNull('sla_deadline')->count();

        $breachedCount  = CommunicationQueue::whereBetween('created_at', [$from, $to])
            ->where('sla_breached', true)->count();

        $slaBreachRate  = $totalWithSla > 0 ? round($breachedCount / $totalWithSla * 100, 1) : 0;

        // ── Staff performance: comms logged per person ────────────────────
        $staffPerformance = CommunicationQueue::whereBetween('created_at', [$from, $to])
            ->whereNotNull('assigned_to')
            ->select(
                'assigned_to',
                DB::raw('COUNT(*) as total_comms'),
                DB::raw('SUM(attempt_count) as total_attempts'),
                DB::raw('SUM(CASE WHEN status = "closed" THEN 1 ELSE 0 END) as closed_count'),
                DB::raw('SUM(CASE WHEN sla_breached = 1 THEN 1 ELSE 0 END) as breached_count')
            )
            ->groupBy('assigned_to')
            ->orderByDesc('total_comms')
            ->get();

        // ── Pipeline ₹ by stage ───────────────────────────────────────────
        $pipelineByStage = Lead::whereNotIn('stage', ['converted', 'lost'])
            ->select('stage', DB::raw('SUM(lead_value) as total_value'), DB::raw('COUNT(*) as count'))
            ->groupBy('stage')
            ->orderByDesc('total_value')
            ->get();

        $totalPipelineValue = Lead::whereNotIn('stage', ['converted', 'lost'])->sum('lead_value');
        $totalWonValue      = Lead::where('stage', 'converted')
            ->whereBetween('updated_at', [$from, $to])->sum('lead_value');

        // ── Won vs Lost reasons ───────────────────────────────────────────
        $wonBySource = Lead::whereBetween('updated_at', [$from, $to])
            ->where('stage', 'converted')
            ->select('lead_source', DB::raw('COUNT(*) as count'), DB::raw('SUM(lead_value) as value'))
            ->groupBy('lead_source')
            ->orderByDesc('count')
            ->get();

        $lostComms = CommunicationQueue::whereBetween('updated_at', [$from, $to])
            ->whereIn('outcome', ['not_interested', 'unreachable', 'lost'])
            // MAX() keeps a representative reason per outcome so the query is
            // valid under MySQL's only_full_group_by mode (we group by outcome).
            ->select('outcome', DB::raw('MAX(outcome_reason) as outcome_reason'), DB::raw('COUNT(*) as count'))
            ->groupBy('outcome')
            ->orderByDesc('count')
            ->get();

        // ── Daily comm volume (chart data — last N days) ──────────────────
        $days = min((int)$period, 30);
        $dailyVolume = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $dailyVolume[] = [
                'date'  => now()->subDays($i)->format('d M'),
                'count' => CommunicationQueue::whereDate('created_at', $date)->count(),
            ];
        }

        // ── Escalations in period ─────────────────────────────────────────
        $escalations = CommunicationQueue::whereBetween('created_at', [$from, $to])
            ->where('outcome', 'escalated')
            ->count();

        // ── High-value leads not yet contacted ────────────────────────────
        $highValueUncontacted = Lead::where('lead_value', '>=', 30000)
            ->whereIn('stage', ['new_lead', 'contacted'])
            ->where(function ($q) {
                // Not contacted in 2 hours
                $q->whereNull('updated_at')
                  ->orWhere('updated_at', '<', now()->subHours(2));
            })
            ->count();

        // ── Inbound SLA violations (not contacted within 30 min) ──────────
        $inboundSlaViolations = CommunicationQueue::where('source_engine', 'inbound')
            ->where('sla_breached', true)
            ->whereBetween('created_at', [$from, $to])
            ->count();

        return view('communication.kpi.index', compact(
            'from', 'to', 'period',
            'totalLeads', 'apptLeads', 'wonLeads', 'lostLeads',
            'leadToAppt', 'leadToTreatment',
            'avgResponseByChannel',
            'slaBreachRate', 'breachedCount', 'totalWithSla',
            'staffPerformance',
            'pipelineByStage', 'totalPipelineValue', 'totalWonValue',
            'wonBySource', 'lostComms',
            'dailyVolume',
            'escalations', 'highValueUncontacted', 'inboundSlaViolations'
        ));
    }
}
