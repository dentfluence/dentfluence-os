<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
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


        return view('communication.huddle.widgets', compact('counts', 'overdue', 'alerts'));
    }

    /**
     * Build the count data used by both widgets and JSON endpoint.
     */
    public function buildCounts(): array
    {
        // TODO (Session 11): replace with real Eloquent queries
        // e.g. FollowUp::whereDate('due_at', today())->whereNull('completed_at')->count()
        return [
            'overdue_callbacks'      => 18,
            'pending_today'          => 34,
            'vip_patients'           => 3,
            'birthdays_today'        => 2,
            'missed_appointments'    => 5,
            'pending_estimates'      => 7,
            'escalations'            => 2,
            'long_term_followups'    => 23,
            'ongoing_treatments'     => 16,
        ];
    }

    /**
     * Build overdue items list for the overdue-summary partial.
     */
    public function buildOverdueItems(): array
    {
        // TODO (Session 11): replace with real Eloquent queries
        return [
            [
                'name'      => 'Mohit Bhatt',
                'phone'     => '98201 23456',
                'type'      => 'Call',
                'icon'      => 'call',
                'overdue'   => '2 days',
                'due_date'  => '17 May, 10:00 AM',
                'initials'  => 'MB',
                'color'     => '#E74C3C',
            ],
            [
                'name'      => 'Sneha Reddy',
                'phone'     => '99456 77889',
                'type'      => 'WhatsApp',
                'icon'      => 'whatsapp',
                'overdue'   => '3 days',
                'due_date'  => '16 May, 03:00 PM',
                'initials'  => 'SR',
                'color'     => '#27AE60',
            ],
            [
                'name'      => 'Amit Kulkarni',
                'phone'     => '98201 23456',
                'type'      => 'Call',
                'icon'      => 'call',
                'overdue'   => '4 days',
                'due_date'  => '15 May, 11:00 AM',
                'initials'  => 'AK',
                'color'     => '#E74C3C',
            ],
            [
                'name'      => 'Vikram Mehta',
                'phone'     => '99876 12345',
                'type'      => 'Call',
                'icon'      => 'call',
                'overdue'   => '5 days',
                'due_date'  => '14 May, 04:00 PM',
                'initials'  => 'VM',
                'color'     => '#E74C3C',
            ],
        ];
    }

    /**
     * Build alert items for the communication-alerts partial.
     */
    public function buildAlerts(): array
    {
        // TODO (Session 11): replace with real Eloquent queries
        return [
            [
                'type'    => 'birthday',
                'icon'    => '🎂',
                'title'   => 'Birthdays Today',
                'count'   => 2,
                'names'   => ['Priya Singh', 'Karan Malhotra'],
                'color'   => '#9B59B6',
                'action'  => 'Send Wishes',
            ],
            [
                'type'    => 'missed_apt',
                'icon'    => '📅',
                'title'   => 'Missed Appointments',
                'count'   => 5,
                'names'   => ['Deepak Nair', 'Anjali Verma', '+3 more'],
                'color'   => '#E74C3C',
                'action'  => 'Follow Up',
            ],
            [
                'type'    => 'vip',
                'icon'    => '⭐',
                'title'   => 'VIP Patients Due',
                'count'   => 3,
                'names'   => ['Arjun Patel', 'Nisha Chauhan', 'Pallavi Joshi'],
                'color'   => '#F39C12',
                'action'  => 'Prioritize',
            ],
            [
                'type'    => 'estimate',
                'icon'    => '📋',
                'title'   => 'Pending Estimates',
                'count'   => 7,
                'names'   => [],
                'color'   => '#2980B9',
                'action'  => 'Review',
            ],
            [
                'type'    => 'escalation',
                'icon'    => '🚨',
                'title'   => 'Escalations',
                'count'   => 2,
                'names'   => ['Rohit Tiwari', 'Megha Iyer'],
                'color'   => '#E74C3C',
                'action'  => 'Resolve Now',
                'urgent'  => true,
            ],
            [
                'type'    => 'overdue',
                'icon'    => '🔴',
                'title'   => 'Overdue Follow-ups',
                'count'   => 18,
                'names'   => ['Mohit Bhatt', 'Sneha Reddy'],
                'color'   => '#E74C3C',
                'action'  => 'View All',
            ],
        ];
    }

    public function overdueSummary(): \Illuminate\View\View
    {
        $overdue = $this->buildOverdueItems();
        return view('communication.huddle.overdue-summary', compact('overdue'));
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
