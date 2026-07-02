<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use App\Services\Prm\LeadEnrichmentService;
use App\Services\Prm\LeadFollowUpService;
use App\Services\Prm\LeadReplyService;
use App\Services\Prm\PrmRelationshipAdapter;
use App\Support\Features\Feature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrmController extends Controller
{
    // ── Views ──────────────────────────────────────────────────────────────────

    /**
     * Pipeline board — main kanban view.
     */
    public function index()
    {
        // Workstream F (slice F1) — when PRM is secondary, send people to the
        // PRE lead pipeline. Still reachable here with ?legacy=1.
        if ($redirect = $this->secondaryRedirect()) {
            return $redirect;
        }

        $stages    = $this->getStages();                              // keyed by stage ID
        $allLeads  = Lead::orderBy('followup_date')->get();
        $grouped   = $allLeads->groupBy('stage');                     // for kanban & chart
        $leads     = $allLeads;                                       // flat, for list view
        $stats     = $this->getPipelineStats($grouped);
        $navCounts = [];                                              // filled when comm. module is complete

        return view('communication.prm.index', compact('stages', 'grouped', 'leads', 'stats', 'navCounts'));
    }

    /**
     * Kanban board partial (AJAX view switch).
     */
    public function board()
    {
        if ($redirect = $this->secondaryRedirect()) {
            return $redirect;
        }

        $stages   = $this->getStages();
        $allLeads = Lead::orderBy('followup_date')->get();
        $grouped  = $allLeads->groupBy('stage');
        $leads    = $allLeads;                                        // flat, for list table

        return view('communication.prm.board', compact('stages', 'grouped', 'leads'));
    }

    /**
     * Workstream F (slice F3) — PRM → secondary.
     *
     * When the `prm.secondary` flag is on, the legacy PRM entry points redirect
     * to the PRE lead pipeline so reception defaults to PRE. PRM stays fully
     * reachable: append ?legacy=1 to bypass the redirect. Default off = no
     * change, PRM primary.
     */
    private function secondaryRedirect(): ?\Illuminate\Http\RedirectResponse
    {
        if (Feature::enabled('prm.secondary') && ! request()->boolean('legacy')) {
            return redirect()->route('relationship.pipeline');
        }

        return null;
    }

    /**
     * Lead detail drawer — loaded via AJAX.
     */
    public function leadDetail($id)
    {
        $lead       = Lead::findOrFail($id);

        // Phase 3: if this lead has a Relationship record, show the unified profile instead.
        // Keep the old lead-detail view as fallback for leads without relationship_id.
        if ($lead->relationship_id) {
            return redirect()->route('relationship.profile', $lead->relationship_id);
        }

        $activities = $lead->activities;

        // The detail view reads $lead['activity'] as a pre-shaped array — map the
        // LeadActivity records into the keys it expects (date/time/by/…).
        $lead->setAttribute('activity', $activities->map(fn ($a) => [
            'type'    => $a->type,
            'label'   => $a->label,
            'outcome' => $a->outcome,
            'note'    => $a->note,
            'date'    => $a->activity_date,
            'time'    => $a->activity_time,
            'by'      => $a->by,
        ])->all());

        // Top-nav tab counts (the shared tabs component expects this).
        $navCounts = [];

        return view('communication.prm.lead-detail', compact('lead', 'activities', 'navCounts'));
    }

    /**
     * Source analytics — conversion % and ₹ pipeline value per lead source.
     * Admin/manager view — can be data-dense.
     */
    public function sourceAnalytics()
    {
        $sources    = Lead::LEAD_SOURCES;
        $allLeads   = Lead::select('lead_source', 'stage', 'lead_value')->get();

        $rows = [];
        foreach ($sources as $key => $label) {
            $bucket    = $allLeads->where('lead_source', $key);
            $total     = $bucket->count();
            $converted = $bucket->whereIn('stage', ['converted'])->count();
            $apptSet   = $bucket->whereIn('stage', ['appointment', 'consultation', 'plan_given', 'converted'])->count();
            $lost      = $bucket->where('stage', 'lost')->count();
            $pipeline  = $bucket->whereNotIn('stage', ['converted', 'lost'])->sum('lead_value');
            $won       = $bucket->where('stage', 'converted')->sum('lead_value');

            $rows[$key] = [
                'label'          => $label,
                'total'          => $total,
                'converted'      => $converted,
                'appt_set'       => $apptSet,
                'lost'           => $lost,
                'conversion_pct' => $total > 0 ? round(($converted / $total) * 100, 1) : 0,
                'pipeline_value' => $pipeline,
                'won_value'      => $won,
            ];
        }

        // Totals row
        $totals = [
            'total'          => $allLeads->count(),
            'converted'      => $allLeads->whereIn('stage', ['converted'])->count(),
            'appt_set'       => $allLeads->whereIn('stage', ['appointment','consultation','plan_given','converted'])->count(),
            'lost'           => $allLeads->where('stage', 'lost')->count(),
            'pipeline_value' => $allLeads->whereNotIn('stage', ['converted','lost'])->sum('lead_value'),
            'won_value'      => $allLeads->where('stage','converted')->sum('lead_value'),
        ];
        $totals['conversion_pct'] = $totals['total'] > 0
            ? round(($totals['converted'] / $totals['total']) * 100, 1)
            : 0;

        return view('communication.prm.source-analytics', compact('rows', 'totals', 'sources'));
    }

    /**
     * Quick add lead form — dead simple (staff-facing, 4 fields).
     */
    public function quickAdd()
    {
        return view('communication.prm.quick-add', $this->formData());
    }

    /**
     * Store a quick-add lead.
     */
    public function storeQuickLead(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:120',
            'phone'       => 'required|string|max:20',
            'lead_source' => 'required|string|max:50',
            'treatment'   => 'nullable|string',
        ]);

        // Default stage + source label
        $data['stage']  = 'new_lead';
        $data['source'] = Lead::LEAD_SOURCES[$data['lead_source']] ?? $data['lead_source'];

        $lead = Lead::create($data);

        $lead->activities()->create([
            'type'          => 'note',
            'label'         => 'Lead Created (Quick Add)',
            'note'          => "Added via Quick Add. Source: {$data['source']}.",
            'activity_date' => today(),
            'activity_time' => now()->format('h:i A'),
            'by'            => auth()->user()->name ?? 'Staff',
        ]);

        return redirect()->route('prm.index')
            ->with('success', '✅ Lead added! ' . $lead->name . ' is now in your pipeline.');
    }

    /**
     * Add lead form.
     */
    public function addLead()
    {
        return view('communication.prm.add-lead', $this->formData());
    }

    /**
     * Edit lead form.
     */
    public function editLead($id)
    {
        $lead = Lead::findOrFail($id);

        return view('communication.prm.add-lead', array_merge($this->formData(), compact('lead')));
    }

    /**
     * PRM settings page.
     */
    public function settings()
    {
        return view('communication.prm.settings');
    }

    // ── Store / Update ─────────────────────────────────────────────────────────

    /**
     * Store a new lead.
     */
    public function storeLead(Request $request)
    {
        $data = $request->validate([
            'name'                 => 'required|string|max:120',
            'phone'                => 'required|string|max:20',
            'alt_phone'            => 'nullable|string|max:20',
            'email'                => 'nullable|email|max:120',
            'stage'                => 'nullable|string',
            'source'               => 'nullable|string',
            'lead_source'          => 'nullable|string|max:50',
            'lead_value'           => 'nullable|numeric|min:0',
            'urgency'              => 'nullable|in:low,medium,high',
            'treatment'            => 'nullable|string',
            'secondary_treatment'  => 'nullable|string',
            'assigned_to'          => 'nullable|string',
            'followup_date'        => 'nullable|date',
            'followup_time'        => 'nullable|string|max:20',
            'preferred_contact'    => 'nullable|in:call,whatsapp,email',
            'notes'                => 'nullable|string',
            'tags'                 => 'nullable|array',
            'dob'                  => 'nullable|date',
            'gender'               => 'nullable|string|max:20',
            'occupation'           => 'nullable|string',
            'location'             => 'nullable|string',
            'language'             => 'nullable|string|max:50',
            'referred_by'          => 'nullable|string',
        ]);

        // Sync human-readable source label if lead_source enum was selected
        if (!empty($data['lead_source']) && empty($data['source'])) {
            $data['source'] = Lead::LEAD_SOURCES[$data['lead_source']] ?? $data['lead_source'];
        }

        $lead = Lead::create($data);

        // Log creation activity
        $lead->activities()->create([
            'type'          => 'note',
            'label'         => 'Lead Created',
            'note'          => 'Lead added via form.',
            'activity_date' => today(),
            'activity_time' => now()->format('h:i A'),
            'by'            => auth()->user()->name ?? 'Staff',
        ]);

        return redirect()->route('prm.index')
            ->with('success', 'Lead created successfully.');
    }

    /**
     * Update an existing lead.
     */
    public function updateLead(Request $request, $id)
    {
        $lead = Lead::findOrFail($id);

        $data = $request->validate([
            'name'                => 'required|string|max:120',
            'phone'               => 'required|string|max:20',
            'alt_phone'           => 'nullable|string|max:20',
            'email'               => 'nullable|email|max:120',
            'stage'               => 'nullable|string',
            'source'              => 'nullable|string',
            'lead_source'         => 'nullable|string|max:50',
            'lead_value'          => 'nullable|numeric|min:0',
            'urgency'             => 'nullable|in:low,medium,high',
            'treatment'           => 'nullable|string',
            'secondary_treatment' => 'nullable|string',
            'assigned_to'         => 'nullable|string',
            'followup_date'       => 'nullable|date',
            'followup_time'       => 'nullable|string|max:20',
            'preferred_contact'   => 'nullable|in:call,whatsapp,email',
            'notes'               => 'nullable|string',
            'tags'                => 'nullable|array',
            'dob'                 => 'nullable|date',
            'gender'              => 'nullable|string|max:20',
            'occupation'          => 'nullable|string',
            'location'            => 'nullable|string',
            'language'            => 'nullable|string|max:50',
            'referred_by'         => 'nullable|string',
        ]);

        if (!empty($data['lead_source']) && empty($data['source'])) {
            $data['source'] = Lead::LEAD_SOURCES[$data['lead_source']] ?? $data['lead_source'];
        }

        $lead->update($data);

        return redirect()->route('prm.index')
            ->with('success', 'Lead updated.');
    }

    // ── AJAX Actions ───────────────────────────────────────────────────────────

    /**
     * Move lead to a different pipeline stage (drag-drop / modal).
     */
    public function moveStage(Request $request, $id)
    {
        $request->validate(['stage' => 'required|string']);

        $lead      = Lead::findOrFail($id);
        $oldStage  = $lead->stage;
        $newStage  = $request->stage;

        $lead->update(['stage' => $newStage]);

        // Log the stage change
        $lead->activities()->create([
            'type'          => 'stage_change',
            'label'         => 'Stage Changed',
            'note'          => "Moved from {$oldStage} → {$newStage}",
            'activity_date' => today(),
            'activity_time' => now()->format('h:i A'),
            'by'            => auth()->user()->name ?? 'Staff',
        ]);

        // Phase 2b — auto-create follow-up reminder(s) for the new stage.
        $created = app(LeadFollowUpService::class)->createForStage($lead, $newStage);

        // Workstream F (slice F1) — reflect this PRM write into the relationship
        // spine: shadow-sync the lead journey (correct stage→state mapping) and
        // record a unified-timeline Activity. Additive and fault-tolerant.
        app(PrmRelationshipAdapter::class)->onStageChanged($lead, $oldStage, $newStage, auth()->user());

        return response()->json([
            'success'           => true,
            'message'           => 'Lead moved to ' . $newStage,
            'followups_created' => $created,
        ]);
    }

    /**
     * Log a new activity on a lead (call, note, WhatsApp, etc.).
     */
    public function logActivity(Request $request, $id)
    {
        $request->validate([
            'type'  => 'required|string',
            'label' => 'required|string',
            'note'  => 'nullable|string',
            'outcome' => 'nullable|string',
        ]);

        $lead = Lead::findOrFail($id);

        $activity = $lead->activities()->create([
            'type'          => $request->type,
            'label'         => $request->label,
            'outcome'       => $request->outcome,
            'note'          => $request->note,
            'activity_date' => today(),
            'activity_time' => now()->format('h:i A'),
            'by'            => auth()->user()->name ?? 'Staff',
        ]);

        // Workstream F (slice F1) — mirror the activity onto the relationship timeline.
        app(PrmRelationshipAdapter::class)->onActivityLogged(
            $lead, $request->type, $request->label, $request->note, $request->outcome, auth()->user(),
        );

        return response()->json(['success' => true, 'activity' => $activity]);
    }

    /**
     * Convert lead to patient (stub — full logic when Patient module is ready).
     */
    public function convertToPatient(Request $request, $id)
    {
        $lead = Lead::findOrFail($id);
        $lead->update(['stage' => 'converted']);

        $lead->activities()->create([
            'type'          => 'stage_change',
            'label'         => 'Converted to Patient',
            'note'          => 'Lead marked as converted.',
            'activity_date' => today(),
            'activity_time' => now()->format('h:i A'),
            'by'            => auth()->user()->name ?? 'Staff',
        ]);

        // Workstream F (slice F1) — reflect the conversion into the relationship spine.
        app(PrmRelationshipAdapter::class)->onConverted($lead, auth()->user());

        return response()->json([
            'success'    => true,
            'message'    => 'Lead converted to patient.',
            'patient_id' => null, // wired when Patient module exists
        ]);
    }

    /**
     * Re-run AI enrichment on a single lead (the "re-run AI" button).
     * Runs synchronously here so the drawer can show the fresh result at once.
     */
    public function reEnrich($id, LeadEnrichmentService $enrichment)
    {
        $lead = Lead::findOrFail($id);

        if (! config('prm.ai.enabled')) {
            return response()->json([
                'success' => false,
                'message' => 'AI enrichment is turned off in PRM settings.',
            ], 422);
        }

        $data = $enrichment->enrich($lead);

        return response()->json([
            'success'    => true,
            'message'    => 'Lead re-analysed by AI.',
            'enrichment' => $data,
        ]);
    }

    /**
     * Generate an AI draft reply for a lead (Phase 3). Never sends — returns
     * editable text for the front desk to review.
     */
    public function draftReply(Request $request, $id, LeadReplyService $replies)
    {
        if (! config('prm.replies.enabled')) {
            return response()->json([
                'success' => false,
                'message' => 'AI replies are turned off in PRM settings.',
            ], 422);
        }

        $request->validate([
            'channel' => 'nullable|string|in:whatsapp,sms,email',
        ]);

        $lead  = Lead::findOrFail($id);
        $draft = $replies->draft($lead, $request->input('channel', 'whatsapp'));

        return response()->json([
            'success' => true,
            'draft'   => $draft,
            'phone'   => preg_replace('/\s+/', '', $lead->phone),
            'email'   => $lead->email,
        ]);
    }

    /**
     * Record that a reply was sent (Phase 3) — logs to the activity timeline.
     * Sending itself happens in WhatsApp/email; this just captures the outcome.
     */
    public function logReply(Request $request, $id)
    {
        $request->validate([
            'channel' => 'required|string|in:whatsapp,sms,email',
            'message' => 'nullable|string',
        ]);

        $lead = Lead::findOrFail($id);

        $lead->activities()->create([
            'type'          => $request->channel === 'email' ? 'note' : 'whatsapp',
            'label'         => 'Reply sent (' . ucfirst($request->channel) . ')',
            'note'          => $request->input('message') ?: 'AI-drafted reply sent.',
            'activity_date' => today(),
            'activity_time' => now()->format('h:i A'),
            'by'            => auth()->user()->name ?? 'Staff',
        ]);

        return response()->json(['success' => true, 'message' => 'Reply logged.']);
    }

    /**
     * Website chatbot preview + install snippet (Phase 6). In-app page so staff
     * can see the widget and copy the embed code for their site.
     */
    public function chatbotPreview()
    {
        return view('communication.prm.chatbot-preview');
    }

    /**
     * "Things to do" inbox (Phase 4d) — one action list: brand-new leads that
     * need first contact + lead follow-ups that are due or overdue.
     */
    public function inbox()
    {
        $newLeads = Lead::where('stage', 'new_lead')
            ->latest()
            ->limit(50)
            ->get();

        $dueFollowups = FollowUp::with('lead')
            ->whereNotNull('lead_id')
            ->where('status', 'pending')
            ->whereDate('due_date', '<=', today())
            ->orderBy('due_date')
            ->get();

        return view('communication.prm.inbox', compact('newLeads', 'dueFollowups'));
    }

    // ── Reports (Phase 5) ───────────────────────────────────────────────────────

    /**
     * Team Performance report — per assigned staff member: load, conversions,
     * won ₹, replies sent, and average first-response time.
     */
    public function teamPerformance()
    {
        $leads = Lead::whereNotNull('assigned_to_id')->get();

        $users = User::whereIn('id', $leads->pluck('assigned_to_id')->unique()->filter())
            ->get()->keyBy('id');

        // Replies sent per lead (from the activity timeline — Phase 3 logs these).
        $replyCounts = LeadActivity::where('label', 'like', 'Reply sent%')
            ->selectRaw('lead_id, COUNT(*) as c')
            ->groupBy('lead_id')
            ->pluck('c', 'lead_id');

        // First outbound contact per lead → used for response-time average.
        $firstContact = LeadActivity::where(function ($q) {
                $q->whereIn('type', ['call', 'whatsapp', 'email'])
                  ->orWhere('label', 'like', 'Reply sent%');
            })
            ->orderBy('created_at')
            ->get(['lead_id', 'created_at'])
            ->groupBy('lead_id')
            ->map(fn ($g) => $g->first()->created_at);

        $rows = [];
        foreach ($users as $uid => $user) {
            $mine      = $leads->where('assigned_to_id', $uid);
            $assigned  = $mine->count();
            $converted = $mine->where('stage', 'converted')->count();
            $lost      = $mine->where('stage', 'lost')->count();
            $won       = $mine->where('stage', 'converted')->sum('lead_value');
            $replies   = $mine->sum(fn ($l) => $replyCounts[$l->id] ?? 0);

            // Average first-response time (hours).
            $diffs = [];
            foreach ($mine as $l) {
                $fc = $firstContact[$l->id] ?? null;
                if ($fc && $l->created_at) {
                    $diffs[] = $l->created_at->diffInMinutes($fc);
                }
            }
            $avgRespHrs = count($diffs) ? round((array_sum($diffs) / count($diffs)) / 60, 1) : null;

            $rows[] = [
                'user'           => $user,
                'assigned'       => $assigned,
                'in_pipeline'    => $assigned - $converted - $lost,
                'converted'      => $converted,
                'conversion_pct' => $assigned ? round($converted / $assigned * 100, 1) : 0,
                'won'            => $won,
                'replies'        => $replies,
                'avg_resp_hrs'   => $avgRespHrs,
            ];
        }

        // Best earners first.
        usort($rows, fn ($a, $b) => $b['won'] <=> $a['won']);

        return view('communication.prm.team-performance', compact('rows'));
    }

    /**
     * Channel ROI report — won ₹ vs ad spend per source (spend from config).
     */
    public function channelRoi()
    {
        $sources = Lead::LEAD_SOURCES;
        $leads   = Lead::select('lead_source', 'stage', 'lead_value')->get();
        $spend   = config('prm.ad_spend', []);

        $rows = [];
        foreach ($sources as $key => $label) {
            $bucket    = $leads->where('lead_source', $key);
            $total     = $bucket->count();
            $converted = $bucket->where('stage', 'converted')->count();
            $won       = $bucket->where('stage', 'converted')->sum('lead_value');
            $adSpend   = (float) ($spend[$key] ?? 0);

            $rows[$key] = [
                'label'     => $label,
                'total'     => $total,
                'converted' => $converted,
                'won'       => $won,
                'spend'     => $adSpend,
                'cpl'       => ($total && $adSpend)     ? round($adSpend / $total)     : null,
                'cpa'       => ($converted && $adSpend) ? round($adSpend / $converted) : null,
                'roi'       => $adSpend > 0 ? round((($won - $adSpend) / $adSpend) * 100) : null,
            ];
        }

        return view('communication.prm.channel-roi', compact('rows'));
    }

    // ── Stats ──────────────────────────────────────────────────────────────────

    private function getPipelineStats($grouped): array
    {
        $total     = Lead::count();
        $converted = Lead::byStage('converted')->count();
        $lost      = Lead::byStage('lost')->count();

        return [
            'total'       => $total,
            'converted'   => $converted,
            'in_pipeline' => $total - $converted - $lost,
            'lost'        => $lost,
            'grouped'     => $grouped->map(fn($leads) => $leads->count())->toArray(),
        ];
    }

    // ── Form data helpers ──────────────────────────────────────────────────────

    private function formData(): array
    {
        return [
            // Phase 3 canonical lead sources (keyed enum → display label)
            'leadSources' => Lead::LEAD_SOURCES,

            'treatments' => [
                'Dental Implant','Teeth Whitening','Braces / Orthodontics','Root Canal Treatment',
                'Crown & Bridge','Scaling & Polishing','Aligners','Veneers','Dentures',
                'Smile Makeover','Pediatric Dentistry','Gum Treatment','Other',
            ],
            'staff' => [
                'Neha (Front Desk)','Anjali Kapoor','Priya Singh','Siddharth Rao','Dr. Mehta',
            ],
            'stages'    => $this->getStages(),
            'languages' => ['English','Hindi','Marathi','Gujarati','Tamil','Telugu','Kannada','Bengali'],
            'timeSlots' => ['Morning (9 AM – 1 PM)','Afternoon (1 PM – 5 PM)','Evening (5 PM – 8 PM)','Anytime'],
        ];
    }

    private function getStages(): array
    {
        // Keyed by stage ID so views can do `$stages as $stageKey => $stageInfo`
        return [
            'new_lead'     => ['label' => 'New Lead',     'color' => '#534AB7', 'bg' => '#EEEDFE'],
            'contacted'    => ['label' => 'Contacted',    'color' => '#0F6E56', 'bg' => '#E1F5EE'],
            'appointment'  => ['label' => 'Appointment',  'color' => '#854F0B', 'bg' => '#FAEEDA'],
            'consultation' => ['label' => 'Consultation', 'color' => '#185FA5', 'bg' => '#E6F1FB'],
            'plan_given'   => ['label' => 'Plan Given',   'color' => '#993556', 'bg' => '#FBEAF0'],
            'converted'    => ['label' => 'Converted',    'color' => '#3B6D11', 'bg' => '#EAF3DE'],
        ];
    }
}
