<?php

namespace App\Http\Controllers\Relationship;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Patient;
use App\Models\RelationshipJourney;
use App\Services\Prm\LeadEnrichmentService;
use App\Services\Prm\LeadFollowUpService;
use App\Services\Prm\LeadReplyService;
use App\Services\Prm\PrmRelationshipAdapter;
use App\Support\Features\Feature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * LeadPipelineController — PRE (Phase 1 · Workstream D, slice 2; writes added
 * Phase 8 · Slices 1–5 — PRM Retirement, now COMPLETE).
 *
 * The relationship-centric lead board for the receptionist — the ONLY lead-
 * pipeline surface in the app since Slice 5: PrmController, routes/prm.php,
 * and the communication/prm/* + components/prm/* views were retired (moved to
 * under_review/phase8_prm_retirement/, not deleted — see that folder for the
 * originals). Leads are grouped into columns by the RELIABLE legacy
 * `leads.stage` column, so the grouping is authoritative today.
 * The shadow relationship-journey state is shown alongside each lead for
 * context ONLY, and only when the feature flag is on (journeys are not
 * authoritative until `journey.authoritative` flips — Phase 8, later slice).
 *
 * index() is a pure read view — unchanged since Phase 1.
 *
 * moveStage() / logActivity() / convertToPatient() are Phase 8 · Slice 1: the
 * core lead-lifecycle writes ported off the retired `PrmController` (still
 * updates `leads.stage` + legacy `LeadActivity` — kept as the historical
 * ledger, PrmRelationshipAdapter reads/writes it regardless of which
 * controller called it) and mirrored onto the relationship spine via
 * `PrmRelationshipAdapter` (name kept post-retirement: it's the shared
 * spine-writing primitive, not a PRM-specific class).
 *
 * quickAdd()/storeQuickLead(), addLead()/editLead()/storeLead()/updateLead()
 * are Phase 8 · Slice 2: lead creation + editing ported the same way. Lead
 * creation doesn't need an explicit spine-mirror call here — LeadObserver
 * already links every new Lead to a Relationship and logs 'lead.created'
 * unconditionally, regardless of which controller called Lead::create().
 * "Assign" (the blueprint's Slice 3 item) has no dedicated endpoint anywhere
 * in this app — assignment is either automatic (LeadRoutingService, on
 * create) or a field on this same edit form — so there is nothing separate
 * to port for it.
 *
 * reEnrich()/draftReply()/logReply() are Phase 8 · Slice 3: the AI helpers,
 * ported the same way. logReply() additionally mirrors onto the relationship
 * spine here — the legacy logReply() never did, a pre-existing gap this fixes.
 *
 * Slice 4 shipped a write-parity harness (retired with PRM in Slice 5, since
 * there is no longer a second engine to compare against — the harness lived
 * at app/Console/Commands/PrmWriteParity.php).
 * Slice 5 retired PrmController/routes/views and repointed every live link
 * that used to point at them (sidebar tabs, Today's Actions, huddle overdue
 * widget, opportunities board, communication move-to-pipeline flows) at this
 * controller instead. The PRM board was hard-deleted 2026-07-04 (it had been
 * sitting in under_review/ since Slice 5) along with the now-pointless
 * `nav.pre_primary`/`prm.secondary` flags — Relationships (PRE) is the only
 * nav entry now, no toggle needed. `journey.authoritative` still defaults
 * off — flipping it is a separate, explicit decision (it changes what
 * drives the pipeline state machine, not just navigation).
 *
 * Routes: GET  /relationship/pipeline                     [relationship.pipeline]
 *         POST /relationship/pipeline/{id}/move           [relationship.pipeline.move]
 *         POST /relationship/pipeline/{id}/activity       [relationship.pipeline.activity]
 *         POST /relationship/pipeline/{id}/convert        [relationship.pipeline.convert]
 *         GET  /relationship/pipeline/quick-add            [relationship.pipeline.quick-add]
 *         POST /relationship/pipeline/quick-add            [relationship.pipeline.store-quick-lead]
 *         GET  /relationship/pipeline/add                  [relationship.pipeline.add-lead]
 *         GET  /relationship/pipeline/{id}/edit             [relationship.pipeline.edit-lead]
 *         POST /relationship/pipeline/add                  [relationship.pipeline.store-lead]
 *         POST /relationship/pipeline/{id}/edit             [relationship.pipeline.update-lead]
 *         POST /relationship/pipeline/{id}/enrich           [relationship.pipeline.enrich]
 *         POST /relationship/pipeline/{id}/draft-reply      [relationship.pipeline.draft-reply]
 *         POST /relationship/pipeline/{id}/log-reply        [relationship.pipeline.log-reply]
 */
class LeadPipelineController extends Controller
{
    /**
     * Board columns, keyed by the legacy `leads.stage` value.
     * Mirrors PrmController::getStages() plus a terminal "Lost" column so the
     * PRE board shows the full picture. Kept local (self-contained) so this
     * slice never reaches into the PRM controller.
     */
    private const STAGES = [
        'new_lead'     => ['label' => 'New Lead',     'color' => '#534AB7', 'bg' => '#EEEDFE'],
        'contacted'    => ['label' => 'Contacted',    'color' => '#0F6E56', 'bg' => '#E1F5EE'],
        'appointment'  => ['label' => 'Appointment',  'color' => '#854F0B', 'bg' => '#FAEEDA'],
        'consultation' => ['label' => 'Consultation', 'color' => '#185FA5', 'bg' => '#E6F1FB'],
        'plan_given'   => ['label' => 'Plan Given',   'color' => '#993556', 'bg' => '#FBEAF0'],
        'converted'    => ['label' => 'Converted',    'color' => '#3B6D11', 'bg' => '#EAF3DE'],
        'lost'         => ['label' => 'Lost',         'color' => '#8A1F1F', 'bg' => '#FDECEC'],
    ];

    /** Max cards rendered per column (rest summarised as "+N more"). */
    private const CARDS_PER_COLUMN = 40;

    /**
     * Shared lookup data for the add/edit-lead forms. Same lists
     * PrmController::formData() uses — kept local per this controller's
     * "self-contained, never reach into PrmController" convention.
     */
    private function formData(): array
    {
        return [
            'leadSources' => Lead::LEAD_SOURCES,
            'treatments' => [
                'Dental Implant', 'Teeth Whitening', 'Braces / Orthodontics', 'Root Canal Treatment',
                'Crown & Bridge', 'Scaling & Polishing', 'Aligners', 'Veneers', 'Dentures',
                'Smile Makeover', 'Pediatric Dentistry', 'Gum Treatment', 'Other',
            ],
            'staff' => [
                'Neha (Front Desk)', 'Anjali Kapoor', 'Priya Singh', 'Siddharth Rao', 'Dr. Mehta',
            ],
            'stages'    => $this->formStages(),
            'languages' => ['English', 'Hindi', 'Marathi', 'Gujarati', 'Tamil', 'Telugu', 'Kannada', 'Bengali'],
            'timeSlots' => ['Morning (9 AM – 1 PM)', 'Afternoon (1 PM – 5 PM)', 'Evening (5 PM – 8 PM)', 'Anytime'],
        ];
    }

    /** self::STAGES minus the terminal "Lost" column — matches the form's stage dropdown in PRM. */
    private function formStages(): array
    {
        $stages = self::STAGES;
        unset($stages['lost']);

        return $stages;
    }

    public function index(): View
    {
        // Optional context surface — OFF by default (journeys are shadow).
        $showJourney = Feature::enabled('relationship.pipeline_journey_column');

        // Read side: pull leads with just the columns the board needs.
        // Grouping uses the reliable legacy `stage` column.
        $leads = Lead::query()
            ->select([
                'id', 'name', 'phone', 'stage', 'treatment', 'lead_value',
                'followup_date', 'assigned_to', 'urgency', 'relationship_id',
            ])
            ->orderByRaw('followup_date IS NULL, followup_date ASC')
            ->orderByDesc('id')
            ->get();

        $grouped = $leads->groupBy('stage');

        // Shadow journey state, keyed by relationship_id — fetched in ONE query
        // and only when the flag is on. Context only; never used for grouping.
        $journeyByRelationship = [];
        if ($showJourney) {
            $relationshipIds = $leads->pluck('relationship_id')->filter()->unique()->values();
            if ($relationshipIds->isNotEmpty()) {
                $journeyByRelationship = RelationshipJourney::query()
                    ->whereIn('relationship_id', $relationshipIds)
                    ->where('type', RelationshipJourney::TYPE_LEAD)
                    ->pluck('state', 'relationship_id')
                    ->toArray();
            }
        }

        // Build ordered columns with per-stage count + pipeline value.
        $columns = [];
        foreach (self::STAGES as $key => $meta) {
            $bucket = $grouped->get($key, collect());
            $columns[] = [
                'key'    => $key,
                'label'  => $meta['label'],
                'color'  => $meta['color'],
                'bg'     => $meta['bg'],
                'count'  => $bucket->count(),
                'value'  => (float) $bucket->sum('lead_value'),
                'leads'  => $bucket->take(self::CARDS_PER_COLUMN),
                'hidden' => max(0, $bucket->count() - self::CARDS_PER_COLUMN),
            ];
        }

        // Headline numbers (active = everything except the two terminal stages).
        $activeCount   = $leads->whereNotIn('stage', ['converted', 'lost'])->count();
        $pipelineValue = (float) $leads->whereNotIn('stage', ['converted', 'lost'])->sum('lead_value');

        return view('relationship.pipeline.index', array_merge($this->formData(), [
            'columns'               => $columns,
            'totalLeads'            => $leads->count(),
            'activeCount'           => $activeCount,
            'pipelineValue'         => $pipelineValue,
            'showJourney'           => $showJourney,
            'journeyByRelationship' => $journeyByRelationship,
        ]));
    }

    // ── Phase 8 · Slice 1 — core lifecycle writes ─────────────────────────────

    /**
     * Move a lead to a different pipeline stage. Ported from
     * PrmController::moveStage() — same effect, PRE entry point.
     */
    public function moveStage(Request $request, $id): JsonResponse
    {
        $request->validate(['stage' => 'required|string']);

        $lead     = Lead::findOrFail($id);
        $oldStage = $lead->stage;
        $newStage = $request->stage;

        $lead->update(['stage' => $newStage]);

        // Legacy activity log too — keeps the PRM board's lead-detail drawer
        // (still reachable during the soak) showing accurate history.
        $lead->activities()->create([
            'type'          => 'stage_change',
            'label'         => 'Stage Changed',
            'note'          => "Moved from {$oldStage} → {$newStage}",
            'activity_date' => today(),
            'activity_time' => now()->format('h:i A'),
            'by'            => auth()->user()->name ?? 'Staff',
        ]);

        // Auto follow-up reminder for the new stage — same rule engine PRM uses.
        $created = app(LeadFollowUpService::class)->createForStage($lead, $newStage);

        // Mirror onto the relationship spine (journey sync + unified Activity).
        app(PrmRelationshipAdapter::class)->onStageChanged($lead, $oldStage, $newStage, auth()->user());

        return response()->json([
            'success'           => true,
            'message'           => 'Lead moved to ' . $newStage,
            'followups_created' => $created,
        ]);
    }

    /**
     * Log an activity (call, note, WhatsApp, …) on a lead. Ported from
     * PrmController::logActivity() — same effect, PRE entry point.
     */
    public function logActivity(Request $request, $id): JsonResponse
    {
        $request->validate([
            'type'    => 'required|string',
            'label'   => 'required|string',
            'note'    => 'nullable|string',
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

        // Mirror onto the relationship spine's unified Activity ledger.
        app(PrmRelationshipAdapter::class)->onActivityLogged(
            $lead, $request->type, $request->label, $request->note, $request->outcome, auth()->user(),
        );

        return response()->json(['success' => true, 'activity' => $activity]);
    }

    /**
     * Convert a lead to a patient. Ported from PrmController::convertToPatient()
     * — same effect (idempotent: reuses an already-linked Patient if one
     * exists), PRE entry point.
     */
    public function convertToPatient(Request $request, $id): JsonResponse
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

        // Reuse the Patient already linked to this lead's Relationship, if any —
        // avoids creating a duplicate when a lead is converted more than once.
        $patient = $lead->relationship_id
            ? Patient::where('relationship_id', $lead->relationship_id)->first()
            : null;

        if (! $patient) {
            $patient = Patient::create([
                'name'            => $lead->name,
                'phone'           => $lead->phone,
                'alternate_phone' => $lead->alt_phone,
                'email'           => $lead->email,
                'date_of_birth'   => $lead->dob,
                'gender'          => $lead->gender,
                'occupation'      => $lead->occupation,
                'area'            => $lead->location,
                'referred_by'     => $lead->referred_by,
                'source'          => $lead->source ?: $lead->lead_source,
                'chief_complaint' => $lead->treatment,
                'branch_id'       => auth()->user()->branch_id ?? 1,
                'created_by'      => auth()->id(),
            ]);

            // Explicitly reuse the lead's known relationship_id (same reasoning
            // as PrmController::convertToPatient — we already have the
            // authoritative link, no need for fuzzy PatientRelationshipLinker).
            if ($lead->relationship_id) {
                $patient->relationship_id = $lead->relationship_id;
                $patient->save();
            }
        }

        // Mirror onto the relationship spine.
        app(PrmRelationshipAdapter::class)->onConverted($lead, auth()->user());

        return response()->json([
            'success'    => true,
            'message'    => 'Lead converted to patient.',
            'patient_id' => $patient->id,
        ]);
    }

    // ── Phase 8 · Slice 2 — lead create + edit ────────────────────────────────

    /** Quick-add form (4 fields). Ported from PrmController::quickAdd(). */
    public function quickAdd(): View
    {
        return view('relationship.pipeline.quick-add', $this->formData());
    }

    /** Store a quick-add lead. Ported from PrmController::storeQuickLead(). */
    public function storeQuickLead(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:120',
            'phone'       => 'required|string|max:20',
            'lead_source' => 'required|string|max:50',
            'treatment'   => 'nullable|string',
        ]);

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

        return redirect()->route('relationship.pipeline')
            ->with('success', '✅ Lead added! ' . $lead->name . ' is now in your pipeline.');
    }

    /** Full add-lead form. Ported from PrmController::addLead(). */
    public function addLead(): View
    {
        return view('relationship.pipeline.add-lead', $this->formData());
    }

    /** Same form, pre-filled. Ported from PrmController::editLead(). */
    public function editLead($id): View
    {
        $lead = Lead::findOrFail($id);

        return view('relationship.pipeline.add-lead', array_merge($this->formData(), compact('lead')));
    }

    /** Store a new lead from the full form. Ported from PrmController::storeLead(). */
    public function storeLead(Request $request): RedirectResponse
    {
        $data = $this->validateLeadForm($request);

        if (! empty($data['lead_source']) && empty($data['source'])) {
            $data['source'] = Lead::LEAD_SOURCES[$data['lead_source']] ?? $data['lead_source'];
        }

        $lead = Lead::create($data);

        $lead->activities()->create([
            'type'          => 'note',
            'label'         => 'Lead Created',
            'note'          => 'Lead added via form.',
            'activity_date' => today(),
            'activity_time' => now()->format('h:i A'),
            'by'            => auth()->user()->name ?? 'Staff',
        ]);

        return redirect()->route('relationship.pipeline')
            ->with('success', 'Lead created successfully.');
    }

    /** Update an existing lead. Ported from PrmController::updateLead(). */
    public function updateLead(Request $request, $id): RedirectResponse
    {
        $lead = Lead::findOrFail($id);
        $data = $this->validateLeadForm($request);

        if (! empty($data['lead_source']) && empty($data['source'])) {
            $data['source'] = Lead::LEAD_SOURCES[$data['lead_source']] ?? $data['lead_source'];
        }

        $lead->update($data);

        return redirect()->route('relationship.pipeline')
            ->with('success', 'Lead updated.');
    }

    /** Shared validation rules for storeLead()/updateLead() — identical to PrmController's. */
    private function validateLeadForm(Request $request): array
    {
        return $request->validate([
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
    }

    // ── Phase 8 · Slice 3 — AI helpers ────────────────────────────────────────

    /** Re-run AI enrichment on a lead. Ported from PrmController::reEnrich(). */
    public function reEnrich($id, LeadEnrichmentService $enrichment): JsonResponse
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

    /** Draft an AI reply (never sends). Ported from PrmController::draftReply(). */
    public function draftReply(Request $request, $id, LeadReplyService $replies): JsonResponse
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
     * Record that a reply was sent. Ported from PrmController::logReply() — with
     * one addition: this also mirrors onto the relationship spine, closing a
     * pre-existing gap where the legacy action never did (see class docblock).
     */
    public function logReply(Request $request, $id): JsonResponse
    {
        $request->validate([
            'channel' => 'required|string|in:whatsapp,sms,email',
            'message' => 'nullable|string',
        ]);

        $lead = Lead::findOrFail($id);
        $type = $request->channel === 'email' ? 'note' : 'whatsapp';
        $label = 'Reply sent (' . ucfirst($request->channel) . ')';
        $note = $request->input('message') ?: 'AI-drafted reply sent.';

        $lead->activities()->create([
            'type'          => $type,
            'label'         => $label,
            'note'          => $note,
            'activity_date' => today(),
            'activity_time' => now()->format('h:i A'),
            'by'            => auth()->user()->name ?? 'Staff',
        ]);

        app(PrmRelationshipAdapter::class)->onActivityLogged($lead, $type, $label, $note, null, auth()->user());

        return response()->json(['success' => true, 'message' => 'Reply logged.']);
    }
}
