<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Support\Features\Feature;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /**
     * Marketing module settings.
     *
     * Note: this controller lives under App\Http\Controllers\Marketing —
     * it is distinct from App\Http\Controllers\Settings\SettingsController.
     */
    public function index(): View
    {
        return view('marketing.settings.index', [
            'integratedMode' => Feature::enabled('marketing.integrated_providers'),
        ]);
    }

    /**
     * Flip the marketing.integrated_providers flag (global override).
     *
     * Dev/QA convenience added per docs/marketing-module-reengineering-plan.md
     * V3 — lets the module be tested in both Standalone and Integrated mode
     * without needing DB/tinker access. Sets a *global* override, matching
     * how OverviewController/AnalyticsController/provider bindings read the
     * flag today (they call Feature::enabled() with no clinic scope).
     */
    public function toggleIntegratedMode(): RedirectResponse
    {
        Feature::set('marketing.integrated_providers', !Feature::enabled('marketing.integrated_providers'));

        return back()->with('success', 'Integrated Mode is now ' . (Feature::enabled('marketing.integrated_providers') ? 'ON' : 'OFF') . '.');
    }
}
