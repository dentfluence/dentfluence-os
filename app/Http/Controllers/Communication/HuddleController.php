<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\FollowUp;
use App\Models\Patient;
use App\Models\Task;
use App\Models\TreatmentPlan;
use App\Models\TreatmentVisit;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class HuddleController extends Controller
{
    /**
     * Return communication stats as JSON for API or partial use.
     * Called by the existing Huddle controller or directly via AJAX.
     */
    public function countsJson(): \Illuminate\Http\JsonResponse
    {
        $counts = $this->buildCounts();

        return response()->json($counts);
    }

    /**
     * Standalone communication huddle widgets page (fallback).
     * Main entry is /communication/huddle — but the primary use is
     * embedding partials into the existing Daily Huddle view.
     */
    public function widgets(): \Illuminate\View\View
    {
        $counts = $this->buildCounts();
        $overdue = $this->buildOverdueItems();
        $alerts  = $this->buildAlerts();

        // Slice E4 — shared read: the Huddle consumes the Today's Actions
        // projection summary instead of running its own domain queries.
        $todaySnapshot = app(\App\Services\Relationship\TodayActionsProjector::class)->summary();

        return view('communication.huddle.widgets', compact('counts', 'overdue', 'alerts', 'todaySnapshot'));
    }

    /**
     * Build the count data used by both widgets and JSON endpoint.
     * All counts are scoped to real DB data — no hardcoded values.
     */
    public function buildCounts(): array
    {
        return [
            // Follow-ups past their due_date and still pending
            'overdue_callbacks'   => FollowUp::overdue()->count(),

            // Follow-ups due exactly today and still pending
            'pending_today'       => FollowUp::dueToday()->count(),

            // Patients tagged "vip"
            'vip_patients'        => Patient::whereHas('tags', fn ($q) => $q->where('slug', 'vip'))->count(),

            // Patients with birthday today (ignores unknown DOBs)
            'birthdays_today'     => Patient::whereNotNull('date_of_birth')
                                        ->whereMonth('date_of_birth', today()->month)
                                        ->whereDay('date_of_birth', today()->day)
                                        ->count(),

            // Appointments marked no_show in the last 7 days
            'missed_appointments' => Appointment::where('status', 'no_show')
                                        ->whereDate('appointment_date', '>=', today()->subDays(7))
                                        ->count(),

            // Treatment plans not yet accepted by the patient
            'pending_estimates'   => TreatmentPlan::where('status', 'pending')->count(),

            // Tasks escalated and unresolved
            'escalations'         => Task::where('status', 'escalated')->count(),

            // Follow-ups due more than 7 days from now (long-range pipeline)
            'long_term_followups' => FollowUp::where('status', 'pending')
                                        ->where('due_date', '>', today()->addDays(7))
                                        ->count(),

            // Treatment visits currently in progress
            'ongoing_treatments'  => TreatmentVisit::where('status', 'ongoing')->count(),
        ];
    }

    /**
     * Build overdue items list for the overdue-summary partial.
     * Returns real overdue follow-ups, oldest first, max 10.
     */
    public function buildOverdueItems(): array
    {
        return FollowUp::overdue()
            ->with('patient:id,name,phone')
            ->orderBy('due_date')           // oldest first = most urgent
            ->limit(10)
            ->get()
            ->map(function (FollowUp $fu) {
                $patient   = $fu->patient;
                $days      = (int) Carbon::today()->diffInDays($fu->due_date);
                $dueStr    = $fu->due_date->format('d M')
                    . ($fu->due_time ? ', ' . Carbon::createFromFormat('H:i', $fu->due_time)->format('h:i A') : '');

                return [
                    'name'     => $patient?->name  ?? 'Unknown Patient',
                    'phone'    => $patient?->phone ?? '—',
                    'type'     => $fu->channel === 'whatsapp' ? 'WhatsApp' : ucfirst($fu->channel),
                    'icon'     => $fu->channel === 'whatsapp' ? 'whatsapp' : 'call',
                    'overdue'  => $days . ' ' . Str::plural('day', $days),
                    'due_date' => $dueStr,
                    'initials' => $fu->avatarInitials(),
                    'color'    => $fu->channelColor(),
                ];
            })
            ->toArray();
    }

    /**
     * Build alert items for the communication-alerts partial.
     * All data comes from real DB queries — no hardcoded values.
     */
    public function buildAlerts(): array
    {
        // ── Birthdays today ──────────────────────────────────────────────────
        $birthdayPatients = Patient::whereNotNull('date_of_birth')
            ->whereMonth('date_of_birth', today()->month)
            ->whereDay('date_of_birth', today()->day)
            ->pluck('name')
            ->toArray();

        // ── Missed appointments (last 7 days) ────────────────────────────────
        $missedAll   = Appointment::where('status', 'no_show')
            ->whereDate('appointment_date', '>=', today()->subDays(7))
            ->with('patient:id,name')
            ->get();
        $missedCount = $missedAll->count();
        $missedNames = $missedAll->take(2)->map(fn ($a) => $a->patient?->name ?? 'Unknown')->toArray();
        if ($missedCount > 2) {
            $missedNames[] = '+' . ($missedCount - 2) . ' more';
        }

        // ── VIP patients (tagged "vip") ──────────────────────────────────────
        $vipAll   = Patient::whereHas('tags', fn ($q) => $q->where('slug', 'vip'))
            ->pluck('name');
        $vipCount = $vipAll->count();
        $vipNames = $vipAll->take(3)->toArray();

        // ── Pending treatment plan estimates ────────────────────────────────
        $pendingEstimates = TreatmentPlan::where('status', 'pending')->count();

        // ── Escalated tasks ──────────────────────────────────────────────────
        $escalatedAll   = Task::where('status', 'escalated')
            ->with('patient:id,name')
            ->get();
        $escalatedCount = $escalatedAll->count();
        $escalatedNames = $escalatedAll->take(2)->map(fn ($t) => $t->patient?->name ?? 'No patient')->toArray();

        // ── Overdue follow-ups ───────────────────────────────────────────────
        $overdueAll   = FollowUp::overdue()->with('patient:id,name')->get();
        $overdueCount = $overdueAll->count();
        $overdueNames = $overdueAll->take(2)->map(fn ($f) => $f->patient?->name ?? 'Unknown')->toArray();

        // Only return alert types that have something to show
        $alerts = [];

        if (count($birthdayPatients) > 0) {
            $alerts[] = [
                'type'   => 'birthday',
                'icon'   => '🎂',
                'title'  => 'Birthdays Today',
                'count'  => count($birthdayPatients),
                'names'  => array_slice($birthdayPatients, 0, 3),
                'color'  => '#9B59B6',
                'action' => 'Send Wishes',
                'link'   => route('patients.index', ['birthday' => today()->format('m-d')]),
            ];
        }

        if ($missedCount > 0) {
            $alerts[] = [
                'type'   => 'missed_apt',
                'icon'   => '📅',
                'title'  => 'Missed Appointments',
                'count'  => $missedCount,
                'names'  => $missedNames,
                'color'  => '#E74C3C',
                'action' => 'Follow Up',
                'link'   => route('appointments.index', ['status' => 'no_show']),
            ];
        }

        if ($vipCount > 0) {
            $alerts[] = [
                'type'   => 'vip',
                'icon'   => '⭐',
                'title'  => 'VIP Patients',
                'count'  => $vipCount,
                'names'  => $vipNames,
                'color'  => '#F39C12',
                'action' => 'Prioritize',
                'link'   => route('patients.index', ['tag' => 'vip']),
            ];
        }

        if ($pendingEstimates > 0) {
            $alerts[] = [
                'type'   => 'estimate',
                'icon'   => '📋',
                'title'  => 'Pending Estimates',
                'count'  => $pendingEstimates,
                'names'  => [],
                'color'  => '#2980B9',
                'action' => 'Review',
                'link'   => route('patients.index', ['tp_status' => 'pending']),
            ];
        }

        if ($escalatedCount > 0) {
            $alerts[] = [
                'type'   => 'escalation',
                'icon'   => '🚨',
                'title'  => 'Escalations',
                'count'  => $escalatedCount,
                'names'  => $escalatedNames,
                'color'  => '#E74C3C',
                'action' => 'Resolve Now',
                'urgent' => true,
                'link'   => route('tasks.index', ['status' => 'escalated']),
            ];
        }

        if ($overdueCount > 0) {
            $alerts[] = [
                'type'   => 'overdue',
                'icon'   => '🔴',
                'title'  => 'Overdue Follow-ups',
                'count'  => $overdueCount,
                'names'  => $overdueNames,
                'color'  => '#E74C3C',
                'action' => 'View All',
                'link'   => route('communication.manager.index'),
            ];
        }

        return $alerts;
    }

    public function overdueSummary(): \Illuminate\View\View
    {
        $overdue = $this->buildOverdueItems();
        // The overdue-summary view also reads $counts (overdue_callbacks,
        // escalations, etc.), so we must supply it here too.
        $counts  = $this->buildCounts();
        return view('communication.huddle.overdue-summary', compact('overdue', 'counts'));
    }

    public function communicationAlerts(): \Illuminate\View\View
    {
        $alerts = $this->buildAlerts();
        return view('communication.huddle.alerts', compact('alerts'));
    }

    public function alerts(): \Illuminate\View\View
    {
        return $this->communicationAlerts();
    }
}
