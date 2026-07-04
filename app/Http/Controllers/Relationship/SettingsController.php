<?php

namespace App\Http\Controllers\Relationship;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Support\Features\FeatureFlagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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

        // Business config (not an architecture flag) — whether the Referral
        // panel on a profile can credit a wallet reward, and how much.
        $referralRewardEnabled = AppSetting::get('referral.reward_enabled', '0') === '1';
        $referralRewardAmount  = (float) AppSetting::get('referral.reward_amount', '500');

        $flagHelp = $this->flagHelp();

        return view('relationship.settings.index', compact(
            'featureFlags',
            'flagGroups',
            'referralRewardEnabled',
            'referralRewardAmount',
            'flagHelp'
        ));
    }

    /**
     * Plain-language "what does this actually do" copy for the hover help card
     * next to each flag. Keyed by flag key; a flag with no entry here just
     * doesn't get a "?" icon. Kept as static content here (not a migration)
     * since it's reference copy, not user data.
     */
    private function flagHelp(): array
    {
        return [
            'identity.link_patient' => [
                'title'   => 'Auto-link new patients to a Master Relationship',
                'explain' => "Every new patient quietly gets one shared \"Relationship\" record behind the scenes. That record is what powers the unified profile page — referrals, family, timeline, all in one place.",
                'example' => "Aarav walks in as a new patient — a Relationship record is created for him automatically. If his son joins later, that record is what lets us link them as a household.",
            ],
            'identity.reads_relationship' => [
                'title'   => 'Reads resolve through the relationship spine',
                'explain' => "Right now, most pages fetch patient info straight from the old Patients table. When this is on, pages read the same info through the newer Relationship record instead — same result on screen, sturdier plumbing underneath.",
                'example' => "Opening a patient's profile today pulls their name and phone directly from Patients. With this on, it pulls the exact same info via the Relationship record — nothing looks different, but it's the foundation the rest of PRE is built on.",
            ],
            'activity.single_ledger_reads' => [
                'title'   => 'Timeline reads from the single Activity ledger',
                'explain' => "The Timeline tab currently stitches together appointments, calls, notes and tasks from several old tables every time you open it. When on, it reads one pre-merged log instead — faster, and everything stays in one place.",
                'example' => "Instead of checking 4 separate tables every time you open a timeline, it's like reading one combined diary instead of four separate notebooks.",
            ],
            'journey.authoritative' => [
                'title'   => 'Journeys become the authoritative pipeline state',
                'explain' => "A \"journey\" tracks where someone is in a process — a lead becoming a patient, a recall being due. Right now, older stage/status fields still drive what you see. This flag would make the newer Journey record the real source of truth instead.",
                'example' => "A lead currently shows \"stage: contacted\" from an old field. This flag would make the system trust the newer Journey record for that same status instead — this is the one switch we're deliberately leaving off until it's been proven on more real data.",
            ],
            'relationship.pipeline_journey_column' => [
                'title'   => 'Show the shadow journey stage on Lead Pipeline cards',
                'explain' => "Adds an extra, read-only label on each lead card showing what the newer Journey system thinks the stage is — just for comparison, doesn't change anything else.",
                'example' => "A lead card shows its normal stage \"Contacted\", plus a small side note \"(Journey: Contacted)\" so you can visually check the two systems agree before we ever rely on the new one.",
            ],
            'relationship.opportunity_journey_column' => [
                'title'   => 'Show the shadow journey stage on Opportunity cards',
                'explain' => "Same idea as the Lead Pipeline version, but for the Treatment Opportunity board — an extra read-only label for comparison only.",
                'example' => "An implant-opportunity card shows its normal status plus \"(Journey: Follow-up due)\" alongside it, just so you can sanity-check the two agree.",
            ],
            'automation.engine' => [
                'title'   => 'Automation Engine owns recall/reminders/retries/cooldowns',
                'explain' => "Decides who gets an automatic recall or appointment reminder message, and when. Turning this on hands that decision to the newer, tested Automation Engine instead of the older code.",
                'example' => "A patient who hasn't visited in 6 months is currently picked up by the old recall logic. With this on, the new engine makes that same call — already checked against 3,831 real patients with zero difference in outcome.",
            ],
            'rules.single_engine' => [
                'title'   => 'Retire the legacy follow-up rules service',
                'explain' => "The \"if this happens, do that\" rules (like follow-up triggers) move from an older service to the newer Rules Engine.",
                'example' => "If a treatment plan sits unaccepted for 3 days, a follow-up task should get created. That rule used to live in old code; this flag moves it to the new engine — checked against 37 different trigger scenarios with zero mismatches.",
            ],
            'today.projection' => [
                'title' => "Serve Today's Actions from a pre-built snapshot",
                'explain' => "Today's Actions currently builds its list live by checking about a dozen data sources every time you open it, which can be slow. When on, it reads a snapshot that's rebuilt automatically every 15 minutes instead — same list, faster to load.",
                'example' => "Instead of re-checking appointments, recalls, tasks and 9 other sources every single time you open the page, it reads a snapshot that refreshes itself every 15 minutes in the background.",
            ],
            'tasks.human_system_split' => [
                'title'   => 'Separate Human and System tasks',
                'explain' => "Splits the task list into things a person needs to actually do, versus things the system just logs automatically in the background — so staff only see what needs their attention.",
                'example' => "A system log entry like \"reminder sent\" won't clutter the task list anymore — only real to-dos like \"Follow up with Mrs. Sharma\" show up.",
            ],
            'insights.signals' => [
                'title'   => 'Replace the single health score with separate signals',
                'explain' => "Every relationship currently gets one blended score. This introduces separate, more specific numbers instead — a Health score, a Lifetime Value estimate, a Risk-of-leaving score. Note: nothing in the app displays these yet, so turning this on has no visible effect right now.",
                'example' => "Instead of one number like \"72/100\", you'd eventually see three: Health 80, Lifetime Value ₹45,000, Risk: Low — a clearer picture than one blended score. (Not wired into any screen yet.)",
            ],
        ];
    }

    /** Save the Referral Rewards business config (on/off + amount). */
    public function saveReferralConfig(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        AppSetting::set('referral.reward_enabled', $request->boolean('enabled') ? '1' : '0', 'referral');
        AppSetting::set('referral.reward_amount', (string) $data['amount'], 'referral');

        return back()->with('success', 'Referral reward settings saved.');
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
