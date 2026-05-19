<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Communication OS — Module Home
     *
     * Renders the module landing page with navigation overview.
     * Real data wiring happens in Session 11.
     * For now: passes config-driven nav + placeholder stats.
     */
    public function index(): View
    {
        $modules = collect(config('communication.navigation'))->map(function ($item) {
            return array_merge($item, [
                'count'       => 0,   // replaced with real counts in Session 11
                'status'      => 'active',
                'description' => $this->moduleDescription($item['key']),
            ]);
        });

        return view('communication.index', [
            'modules'    => $modules,
            'pageTitle'  => 'Communication OS',
            'activeNav'  => 'dashboard',
        ]);
    }

    private function moduleDescription(string $key): string
    {
        return match ($key) {
            'manager'      => 'Execute callbacks, follow-ups & communication queue',
            'prm'          => 'Lead pipeline from inquiry to treatment acceptance',
            'followup'     => 'Post-op, recalls, and continuity follow-ups',
            'opportunities'=> 'Track patient intent and future treatment opportunities',
            'tasks'        => 'Assignments, escalations & accountability queue',
            'timeline'     => 'Unified communication history per patient',
            'templates'    => 'Quick replies, WhatsApp templates & smart defaults',
            default        => '',
        };
    }
}
