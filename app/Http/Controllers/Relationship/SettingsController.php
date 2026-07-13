<?php

namespace App\Http\Controllers\Relationship;

use App\Http\Controllers\Controller;
use App\Models\ActionOptionList;
use App\Models\AppSetting;
use App\Models\MessageTemplate;
use App\Models\TreatmentType;
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
 *
 * Also now hosts Recall/Birthday settings (moved from
 * Communication OS 2026-07-06 — see saveRecallGeneral/saveTreatmentRecall/
 * saveBirthday below) and the Templates module routes
 * (see routes/relationship.php's `templates.*` group + Relationship\
 * TemplateController) — both are PRE concerns now, not Communication OS.
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

        // Recall Automation Go-Live Date — see RecallAutomationRunner::runNoVisit()
        // for what this actually gates. Null/blank = legacy behaviour (unrestricted).
        $recallEffectiveFrom = AppSetting::get('recall.effective_from');

        // ── Recall / Birthday settings (moved from Communication
        // OS 2026-07-06 — was Communication\RecallSettingsController@index,
        // archived at under_review/pre_consolidation_2026_07_06/). Same
        // AppSetting keys, same TreatmentType column — no behaviour change,
        // only relocated into this page's "Recall" section. Message *copy* is
        // still NOT edited here — gear icons deep-link to
        // relationship.templates.forType.
        $recallGeneralDays = (int) AppSetting::get('recall.general_days', 180); // matches legacy 6-month default
        $recallChannels = [
            'whatsapp' => AppSetting::get('recall.channel_whatsapp', '1') === '1',
            'sms'      => AppSetting::get('recall.channel_sms', '0') === '1',
            'email'    => AppSetting::get('recall.channel_email', '0') === '1',
        ];

        $recallTreatmentTypes = TreatmentType::query()->active()->get(['id', 'name', 'recall_after_days']);

        $birthdayEnabled = AppSetting::get('recall.birthday_enabled', '1') === '1';
        $birthdayWindowDays = (int) AppSetting::get(
            'recall.birthday_window_days',
            config('relationship_rules.today_actions.birthday_window_days', 1)
        );

        // Template ids (if any already exist) — used only to show a "configured"
        // hint on the gear icon; the gear link itself uses forType and doesn't
        // need the id up front.
        $recallTemplate      = MessageTemplate::query()->ofType('recall')->active()->first();
        $birthdayTemplate    = MessageTemplate::query()->ofType('birthday')->active()->first();

        $flagHelp = $this->flagHelp();

        // ── Call Outcomes + Dismiss Reasons (2026-07-06) ────────────────────
        // See docs/feature-specs/feature-spec-custom-call-outcomes.md. Shows
        // ALL rows (not just active) so staff can re-activate a disabled one —
        // "delete" isn't offered since old Activity rows may still reference a
        // key by value.
        $callOutcomeCategories = ActionOptionList::query()
            ->where('option_type', 'call_outcome')
            ->orderBy('action_category')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('action_category');

        $dismissReasonOptions = ActionOptionList::query()
            ->where('option_type', 'dismiss_reason')
            ->orderBy('sort_order')
            ->get();

        return view('relationship.settings.index', compact(
            'featureFlags',
            'flagGroups',
            'referralRewardEnabled',
            'referralRewardAmount',
            'recallEffectiveFrom',
            'recallGeneralDays',
            'recallChannels',
            'recallTreatmentTypes',
            'birthdayEnabled',
            'birthdayWindowDays',
            'recallTemplate',
            'birthdayTemplate',
            'flagHelp',
            'callOutcomeCategories',
            'dismissReasonOptions',
        ));
    }

    /** Plain labels for the call-outcome category tables (fewer than the full Action Board categories — only those that get a custom outcome set). */
    public static function callOutcomeCategoryLabels(): array
    {
        return [
            'default'               => 'New Enquiries / Lead Follow-ups (default set)',
            'appointment_reminders' => 'Appointment Reminders',
            'follow_up_calls'       => 'Follow-up Calls',
            'recall_calls'          => 'Recall Calls',
            'opportunities'         => 'Treatment Opportunities',
            'pending_estimates'     => 'Pending Estimates',
            'membership_renewals'   => 'Membership Renewals',
            'lab_ready'             => 'Lab Work Ready',
            'birthday'              => 'Birthday Wishes',
            'payment_reminders'     => 'Payment Reminders',
        ];
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

    /**
     * Save (or clear) the Recall Automation Go-Live Date.
     *
     * Scopes automatic "no visit in 6 months" recall to patients whose last
     * visit is on/after this date — keeps a bulk historical/migrated patient
     * import from being auto-queued as a one-day backlog dump. See
     * RecallAutomationRunner::runNoVisit().
     */
    public function saveRecallEffectiveFrom(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'effective_from' => ['nullable', 'date'],
        ]);

        if (empty($data['effective_from'])) {
            AppSetting::set('recall.effective_from', null, 'automation');
            return back()->with('success', 'Recall automation go-live date cleared — back to unrestricted (legacy) behaviour.');
        }

        AppSetting::set('recall.effective_from', $data['effective_from'], 'automation');

        return back()->with('success', 'Recall automation go-live date saved.');
    }

    /**
     * Save General Recall periodicity + per-channel enable flags.
     *
     * Moved from Communication\RecallSettingsController@saveGeneral
     * (2026-07-06) — same AppSetting keys, same validation, no behaviour
     * change.
     */
    public function saveRecallGeneral(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'general_days' => ['required', 'integer', 'min:1', 'max:3650'],
        ]);

        AppSetting::set('recall.general_days', (string) $data['general_days'], 'recall');
        AppSetting::set('recall.channel_whatsapp', $request->boolean('channel_whatsapp') ? '1' : '0', 'recall');
        AppSetting::set('recall.channel_sms', $request->boolean('channel_sms') ? '1' : '0', 'recall');
        AppSetting::set('recall.channel_email', $request->boolean('channel_email') ? '1' : '0', 'recall');

        return back()->with('success', 'General recall settings saved.');
    }

    /**
     * Save one treatment type's "recall after N days" override.
     *
     * Moved from Communication\RecallSettingsController@saveTreatmentRecall
     * (2026-07-06) — same behaviour, only the route parameter name changed
     * (treatmentType, matching this file's whereNumber binding).
     */
    public function saveTreatmentRecall(Request $request, int $treatmentType): RedirectResponse
    {
        $data = $request->validate([
            'recall_after_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        $type = TreatmentType::findOrFail($treatmentType);
        $type->update(['recall_after_days' => $data['recall_after_days'] ?? null]);

        return back()->with('success', "Recall periodicity saved for {$type->name}.");
    }

    /**
     * Save Birthday reminder enable + window (days before/after).
     *
     * Moved from Communication\RecallSettingsController@saveBirthday
     * (2026-07-06) — same AppSetting keys, no behaviour change.
     */
    public function saveBirthday(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'window_days' => ['required', 'integer', 'min:0', 'max:30'],
        ]);

        AppSetting::set('recall.birthday_enabled', $request->boolean('enabled') ? '1' : '0', 'recall');
        AppSetting::set('recall.birthday_window_days', (string) $data['window_days'], 'recall');

        return back()->with('success', 'Birthday reminder settings saved.');
    }

    /**
     * Update one call-outcome option (label / requires_notes / active / order).
     * See docs/feature-specs/feature-spec-custom-call-outcomes.md.
     */
    public function saveCallOutcome(Request $request, ActionOptionList $option): RedirectResponse
    {
        abort_unless($option->option_type === 'call_outcome', 404);

        $data = $request->validate([
            'label'          => ['required', 'string', 'max:150'],
            'sort_order'     => ['required', 'integer', 'min:0'],
            'requires_notes' => ['nullable', 'boolean'],
            'closes_task'    => ['nullable', 'boolean'],
            'is_active'      => ['nullable', 'boolean'],
        ]);

        $option->update([
            'label'          => $data['label'],
            'sort_order'     => $data['sort_order'],
            'requires_notes' => $request->boolean('requires_notes'),
            'closes_task'    => $request->boolean('closes_task'),
            'is_active'      => $request->boolean('is_active'),
        ]);

        return back()->with('success', "Call outcome \"{$option->label}\" saved.");
    }

    /** Add a new call-outcome option to a category. */
    public function addCallOutcome(Request $request, string $category): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:150'],
        ]);

        $key = \Illuminate\Support\Str::slug($data['label'], '_');

        if (ActionOptionList::query()->where('option_type', 'call_outcome')
            ->where('action_category', $category)->where('key', $key)->exists()) {
            return back()->with('error', 'An outcome with a matching key already exists in this category — use a slightly different label.');
        }

        $nextOrder = (int) ActionOptionList::query()
            ->where('option_type', 'call_outcome')
            ->where('action_category', $category)
            ->max('sort_order') + 1;

        ActionOptionList::create([
            'option_type'     => 'call_outcome',
            'action_category' => $category,
            'key'             => $key,
            'label'           => $data['label'],
            'sort_order'      => $nextOrder,
            // New outcomes default to auto-close (true) — the common case is a
            // resolved outcome; admin can untick "Auto-closes" below for a
            // retry-style outcome like "No answer".
            'closes_task'     => true,
            'is_active'       => true,
        ]);

        return back()->with('success', "Added \"{$data['label']}\".");
    }

    /** Update one dismiss-reason option. */
    public function saveDismissReason(Request $request, ActionOptionList $option): RedirectResponse
    {
        abort_unless($option->option_type === 'dismiss_reason', 404);

        $data = $request->validate([
            'label'          => ['required', 'string', 'max:150'],
            'sort_order'     => ['required', 'integer', 'min:0'],
            'requires_notes' => ['nullable', 'boolean'],
            'is_active'      => ['nullable', 'boolean'],
        ]);

        $option->update([
            'label'          => $data['label'],
            'sort_order'     => $data['sort_order'],
            'requires_notes' => $request->boolean('requires_notes'),
            'is_active'      => $request->boolean('is_active'),
        ]);

        return back()->with('success', "Dismiss reason \"{$option->label}\" saved.");
    }

    /** Add a new dismiss-reason option (shared across all categories). */
    public function addDismissReason(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:150'],
        ]);

        $key = \Illuminate\Support\Str::slug($data['label'], '_');

        if (ActionOptionList::query()->where('option_type', 'dismiss_reason')->where('key', $key)->exists()) {
            return back()->with('error', 'A dismiss reason with a matching key already exists — use a slightly different label.');
        }

        $nextOrder = (int) ActionOptionList::query()
            ->where('option_type', 'dismiss_reason')
            ->max('sort_order') + 1;

        ActionOptionList::create([
            'option_type' => 'dismiss_reason',
            'key'         => $key,
            'label'       => $data['label'],
            'sort_order'  => $nextOrder,
            'is_active'   => true,
        ]);

        return back()->with('success', "Added \"{$data['label']}\".");
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
