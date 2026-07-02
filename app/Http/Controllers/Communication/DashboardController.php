<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\CommunicationQueue;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $modules = collect(config('communication.navigation'))->map(function ($item) {
            return array_merge($item, [
                'count'       => 0,
                'status'      => 'active',
                'description' => $this->moduleDescription($item['key']),
            ]);
        });

        // Real stats from DB — combining Leads + CommunicationQueue
        $stats = [
            'new_leads'        => Lead::whereDate('created_at', today())->count(),

            // Follow-ups due today: leads + queue items due today OR pending with no date set
            'followups_due'    => Lead::whereDate('followup_date', today())->count()
                                + CommunicationQueue::whereNotIn('status', ['closed', 'won', 'lost'])
                                    ->where(function ($q) {
                                        $q->whereDate('follow_up_date', today())
                                          ->orWhereNull('follow_up_date');
                                    })->count(),

            'converted'        => Lead::where('stage', 'converted')->whereDate('updated_at', today())->count(),

            // Overdue: leads + communication_queue items past due date, not closed
            'overdue'          => Lead::where('followup_date', '<', today())
                                    ->whereNotIn('stage', ['converted', 'lost'])->count()
                                + CommunicationQueue::where('follow_up_date', '<', today())
                                    ->whereNotIn('status', ['closed', 'won', 'lost'])->count(),

            // Today's contact list: queue items scheduled for today
            'today_count'      => CommunicationQueue::whereDate('follow_up_date', today())
                                    ->whereNotIn('status', ['closed', 'won', 'lost'])->count(),

            'active_leads'     => Lead::whereNotIn('stage', ['converted', 'lost'])->count(),

            'whatsapp_pending' => CommunicationQueue::where('channel', 'whatsapp')
                                    ->whereNotIn('status', ['closed', 'won', 'lost'])->count(),
        ];

        $total = Lead::count();
        $converted = Lead::where('stage', 'converted')->count();
        $metrics = [
            'total_leads'         => $total,
            'total_calls'         => CommunicationQueue::where('channel', 'call')->count(),
            'followups_completed' => $converted,
            'conversion_rate'     => $total > 0 ? round(($converted / $total) * 100, 1) . '%' : '0%',
        ];

        // Recent activity — leads + communication queue (recall, follow-ups, etc.)
        $recentLeads = Lead::orderByDesc('created_at')->limit(5)->get()
            ->map(fn($l) => [
                'type'  => 'lead',
                'color' => 'blue',
                'text'  => 'New lead — ' . $l->name,
                'by'    => $l->assigned_to ?? 'Staff',
                'time'  => $l->created_at->diffForHumans(),
                'badge' => 'Lead',
            ]);

        $recentQueue = CommunicationQueue::with('patient')
            ->orderByDesc('created_at')->limit(5)->get()
            ->map(fn($q) => [
                'type'  => 'queue',
                'color' => $q->source_engine === 'recall' ? 'green' : 'purple',
                'text'  => ucfirst($q->source_engine ?? 'Follow-up') . ' — ' . optional($q->patient)->name,
                'by'    => $q->assignedTo->name ?? 'System',
                'time'  => $q->created_at->diffForHumans(),
                'badge' => ucfirst($q->source_engine ?? 'Queue'),
            ]);

        $recentActivity = $recentLeads->concat($recentQueue)
            ->sortByDesc('time')->take(8)->values()->toArray();

        return view('communication.index', [
            'modules'        => $modules,
            'stats'          => $stats,
            'metrics'        => $metrics,
            'recentActivity' => $recentActivity,
            'pageTitle'      => 'Communication OS',
            'activeNav'      => 'dashboard',
        ]);
    }

    private function moduleDescription(string $key): string
    {
        return match ($key) {
            'manager'       => 'Execute callbacks, follow-ups & communication queue',
            'prm'           => 'Lead pipeline from inquiry to treatment acceptance',
            'followup'      => 'Post-op, recalls, and continuity follow-ups',
            'opportunities' => 'Track patient intent and future treatment opportunities',
            'tasks'         => 'Assignments, escalations & accountability queue',
            'timeline'      => 'Unified communication history per patient',
            'templates'     => 'Quick replies, WhatsApp templates & smart defaults',
            default         => '',
        };
    }
}
