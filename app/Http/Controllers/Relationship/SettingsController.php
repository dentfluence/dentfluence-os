<?php

namespace App\Http\Controllers\Relationship;

use App\Http\Controllers\Controller;
use App\Support\Features\FeatureFlagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * SettingsController — module-scoped settings for the Relationship Engine (PRE).
 *
 * Moved out of the global Settings module (2026-07-03) because Sumit plans to
 * sell PRE as a standalone module alongside the full Dentfluence bundle. A
 * standalone buyer should be able to configure PRE entirely from within the
 * Relationship module itself, without needing the rest of Settings wired in.
 *
 * Only PRE-relevant flags live here (identity/journey/relationship/today/
 * tasks/automation/insights + PRE navigation). Flags that are genuinely
 * cross-cutting across the whole app (Communication Guard, Integrations,
 * Workflow Engine, Search) stay in the global Settings module, since those
 * apply regardless of which paid modules a clinic has.
 *
 * Route: GET  /relationship/settings          [relationship.settings]
 *        POST /relationship/settings/toggle    [relationship.settings.toggle]
 */
class SettingsController extends Controller
{
    public function index(): View
    {
        $featureFlags = app(FeatureFlagService::class)->all();

        $flagGroups = [
            'Navigation'              => ['nav.pre_primary', 'prm.secondary'],
            'Relationship Foundation' => [
                'identity.link_patient',
                'identity.reads_relationship',
                'activity.single_ledger_reads',
                'journey.authoritative',
                'relationship.pipeline_journey_column',
                'relationship.opportunity_journey_column',
            ],
            'Automation'    => ['automation.engine', 'rules.single_engine'],
            'Work Surfaces' => ['today.projection', 'tasks.human_system_split'],
            'Insights'      => ['insights.signals'],
        ];

        return view('relationship.settings.index', compact('featureFlags', 'flagGroups'));
    }

    // Toggle one PRE flag globally. Whitelist-checked against config/features.php
    // so only declared flags can ever be written — never trusts the key alone.
    public function toggleFlag(Request $request): JsonResponse
    {
        $request->validate([
            'key'     => ['required', 'string'],
            'enabled' => ['required', 'boolean'],
        ]);

        $key = $request->string('key')->toString();

        if (! array_key_exists($key, (array) config('features.flags', []))) {
            return response()->json(['ok' => false, 'message' => 'Unknown flag — refusing to set it.'], 422);
        }

        app(FeatureFlagService::class)->set(
            $key,
            $request->boolean('enabled'),
            null, // global override, not per-clinic
            'Toggled from Relationship > Settings by ' . (auth()->user()->name ?? 'admin')
        );

        return response()->json([
            'ok'      => true,
            'key'     => $key,
            'enabled' => $request->boolean('enabled'),
        ]);
    }
}
