<?php

namespace App\Http\Controllers;

use App\Models\Treatment;
use App\Models\TreatmentCategory;
use App\Models\TreatmentSop;
use App\Models\TreatmentRule;
use App\Models\TreatmentMedia;
use App\Models\TreatmentPlan;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TreatmentController extends Controller
{
    // ══════════════════════════════════════════════════════════════════════════
    // CATALOG — INDEX
    // ══════════════════════════════════════════════════════════════════════════

    public function index(Request $request)
    {
        $search = $request->get('q');

        $categories = TreatmentCategory::with([
            'allTreatments' => function ($q) use ($search) {
                if ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
                }
                $q->orderBy('sort_order')->orderBy('name');
            },
            'allTreatments.activeSop',
            'allTreatments.rules',
        ])
        ->orderBy('name')
        ->get();

        // Filter out empty categories when searching
        if ($search) {
            $categories = $categories->filter(fn($c) => $c->allTreatments->isNotEmpty());
        }

        $totalTreatments = Treatment::count();
        $activeCount     = Treatment::where('is_active', true)->count();
        $sopPending      = Treatment::whereDoesntHave('sops')->where('is_active', true)->count();
        $reviewDue       = TreatmentSop::where('status', 'active')
                            ->where('next_review_at', '<=', now()->addDays(30))
                            ->count();

        return view('treatments.index', compact(
            'categories', 'search',
            'totalTreatments', 'activeCount', 'sopPending', 'reviewDue'
        ));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CREATE / STORE
    // ══════════════════════════════════════════════════════════════════════════

    public function create()
    {
        $categories = TreatmentCategory::active()->orderBy('name')->get();
        return view('treatments.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'treatment_category_id' => 'required|exists:treatment_categories,id',
            'name'                  => 'required|string|max:255',
            'code'                  => 'nullable|string|max:30|unique:treatments,code',
            'description'           => 'nullable|string',
            'color'                 => 'nullable|string|size:7',
            'default_duration_minutes' => 'required|integer|min:5|max:480',
            'default_price'         => 'required|numeric|min:0',
            'min_price'             => 'nullable|numeric|min:0',
            'max_price'             => 'nullable|numeric|min:0',
            'gst_pct'               => 'required|numeric|min:0|max:100',
            'unit_basis'            => 'nullable|in:per_tooth,whole_mouth,per_arch',
            'sort_order'            => 'nullable|integer|min:0',
            'is_active'             => 'boolean',
        ]);

        $treatment = Treatment::create($data);

        // Auto-create a blank draft SOP so the tab is ready
        TreatmentSop::create([
            'treatment_id' => $treatment->id,
            'version'      => 1,
            'status'       => 'draft',
        ]);

        return redirect()->route('treatments.show', $treatment)
            ->with('success', "Treatment \"{$treatment->name}\" created. Fill in the SOP and rules next.");
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SHOW (detail with tabs)
    // ══════════════════════════════════════════════════════════════════════════

    public function show(Treatment $treatment, Request $request)
    {
        $tab = $request->get('tab', 'overview');

        $treatment->load([
            'category',
            'activeSop.reviewer',
            'sops.reviewer',
            'rules',
            'media',
        ]);

        // ── Basic usage count ──────────────────────────────────────────────────
        $usageCount = DB::table('treatment_plan_items')
            ->where('treatment_name', $treatment->name) // legacy name-match
            ->count();

        // ── P2C8: Intelligence tab — performance insights ─────────────────────
        $intelligenceData = [];
        if ($tab === 'intelligence') {
            $tpItems = DB::table('treatment_plan_items')
                ->where('treatment_name', $treatment->name)
                ->select('price', 'created_at', 'treatment_plan_id')
                ->orderByDesc('created_at')
                ->limit(100)
                ->get();

            // Revenue stats
            $prices = $tpItems->pluck('price')->filter()->map(fn($p) => (float) $p);
            $intelligenceData = [
                'total_uses'          => $tpItems->count(),
                'revenue_total'       => $prices->sum(),
                'revenue_avg'         => $prices->count() ? round($prices->avg(), 0) : 0,
                'revenue_min'         => $prices->count() ? $prices->min() : 0,
                'revenue_max'         => $prices->count() ? $prices->max() : 0,
                'last_used_at'        => $tpItems->first()?->created_at,
                'last_30d_uses'       => $tpItems->filter(
                    fn($r) => \Carbon\Carbon::parse($r->created_at)->gt(now()->subDays(30))
                )->count(),
                'last_90d_uses'       => $tpItems->filter(
                    fn($r) => \Carbon\Carbon::parse($r->created_at)->gt(now()->subDays(90))
                )->count(),
                // Co-occurring treatments (treatments that appear in the same plans)
                'co_treatments'       => DB::table('treatment_plan_items as b')
                    ->join('treatment_plan_items as a', 'a.treatment_plan_id', '=', 'b.treatment_plan_id')
                    ->where('a.treatment_name', $treatment->name)
                    ->where('b.treatment_name', '!=', $treatment->name)
                    ->select('b.treatment_name', DB::raw('COUNT(*) as cnt'))
                    ->groupBy('b.treatment_name')
                    ->orderByDesc('cnt')
                    ->limit(5)
                    ->get(),
                // Keyword match rate — how many consultations with a matching complaint had this treatment added
                'keyword_match_count' => $treatment->trigger_keywords
                    ? DB::table('consultations')
                        ->where(function ($q) use ($treatment) {
                            foreach (($treatment->trigger_keywords ?? []) as $kw) {
                                $q->orWhere('chief_complaint', 'like', "%{$kw}%");
                            }
                        })
                        ->count()
                    : 0,
            ];
        }

        $categories = TreatmentCategory::active()->orderBy('name')->get();

        return view('treatments.show', compact('treatment', 'tab', 'usageCount', 'categories', 'intelligenceData'));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // UPDATE — basic info (Overview tab)
    // ══════════════════════════════════════════════════════════════════════════

    public function update(Request $request, Treatment $treatment)
    {
        $data = $request->validate([
            'treatment_category_id' => 'required|exists:treatment_categories,id',
            'name'                  => 'required|string|max:255',
            'code'                  => "nullable|string|max:30|unique:treatments,code,{$treatment->id}",
            'description'           => 'nullable|string',
            'color'                 => 'nullable|string|size:7',
            'default_duration_minutes' => 'required|integer|min:5|max:480',
            'default_price'         => 'required|numeric|min:0',
            'min_price'             => 'nullable|numeric|min:0',
            'max_price'             => 'nullable|numeric|min:0',
            'gst_pct'               => 'required|numeric|min:0|max:100',
            'unit_basis'            => 'nullable|in:per_tooth,whole_mouth,per_arch',
            'sort_order'            => 'nullable|integer|min:0',
            'is_active'             => 'boolean',
            'needs_lab'             => 'boolean',
            'lab_work_category'     => 'nullable|string|max:100',
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['needs_lab'] = $request->boolean('needs_lab');
        $treatment->update($data);

        return back()->with('success', 'Treatment updated.');
    }

    public function destroy(Treatment $treatment)
    {
        $treatment->delete(); // soft delete
        return redirect()->route('treatments.index')->with('success', "Treatment deleted.");
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SOP — save / update structured SOP (SOP tab)
    // ══════════════════════════════════════════════════════════════════════════

    public function saveSop(Request $request, Treatment $treatment)
    {
        $data = $request->validate([
            'doctor_steps'      => 'nullable|array',
            'doctor_steps.*'    => 'string|max:500',
            'assistant_steps'   => 'nullable|array',
            'assistant_steps.*' => 'string|max:500',
            'pre_instructions'  => 'nullable|string',
            'post_instructions' => 'nullable|string',
            'clinical_notes'    => 'nullable|string',
            'consent_notes'     => 'nullable|string',
            'status'            => 'required|in:draft,active,under_review',
        ]);

        // Get current active/draft SOP or create new
        $sop = $treatment->sops()->whereIn('status', ['draft', 'active', 'under_review'])->first();

        if ($sop) {
            $sop->update($data);
        } else {
            $lastVersion = $treatment->sops()->max('version') ?? 0;
            $sop = TreatmentSop::create(array_merge($data, [
                'treatment_id' => $treatment->id,
                'version'      => $lastVersion + 1,
            ]));
        }

        // If activating, archive old ones
        if ($data['status'] === 'active') {
            TreatmentSop::where('treatment_id', $treatment->id)
                ->where('id', '!=', $sop->id)
                ->where('status', 'active')
                ->update(['status' => 'archived']);
        }

        return back()->with('success', 'SOP saved.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CONSENT — dedicated tab (2026-07-13), split out of the SOP tab so this
    // text isn't buried in a two-column grid. Still writes to the same
    // TreatmentSop.consent_notes column the SOP tab's form used to write, so
    // ConsentDocumentService (Treatment Plan → Consent Form) is unaffected.
    // A separate save route — rather than reusing saveSop()/treatments.sop.save
    // — so this tab's form only ever posts consent_notes and can never
    // accidentally blank out doctor_steps/pre_instructions/etc. from the SOP
    // tab, and so it doesn't force an SOP status choice on staff who are only
    // here to fill in a consent explanation.
    // ══════════════════════════════════════════════════════════════════════════

    public function saveConsent(Request $request, Treatment $treatment)
    {
        $data = $request->validate([
            'consent_notes' => 'nullable|string',
        ]);

        $sop = $treatment->sops()->whereIn('status', ['draft', 'active', 'under_review'])->first();

        if ($sop) {
            $sop->update($data);
        } else {
            $lastVersion = $treatment->sops()->max('version') ?? 0;
            $sop = TreatmentSop::create(array_merge($data, [
                'treatment_id' => $treatment->id,
                'version'      => $lastVersion + 1,
                'status'       => 'draft',
            ]));
        }

        return back()->with('success', 'Consent explanation saved.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // STAGES — define ordered visit stages for this treatment (Stages tab)
    // ══════════════════════════════════════════════════════════════════════════

    public function saveStages(Request $request, Treatment $treatment)
    {
        $data = $request->validate([
            'stages'          => 'nullable|array|max:20',
            'stages.*.key'    => 'required|string|max:60|regex:/^[a-z0-9_]+$/',
            'stages.*.label'  => 'required|string|max:100',
        ]);

        // Deduplicate keys — keep last occurrence
        $stages = collect($data['stages'] ?? [])
            ->filter(fn($s) => !empty($s['key']) && !empty($s['label']))
            ->unique('key')
            ->values()
            ->all();

        $treatment->update(['stages' => $stages ?: null]);

        return back()->with('success', 'Stages saved for ' . $treatment->name . '.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // RULES — toggle / upsert rules (Rules tab)
    // ══════════════════════════════════════════════════════════════════════════

    public function saveRules(Request $request, Treatment $treatment)
    {
        $data = $request->validate([
            'rules'              => 'nullable|array',
            'rules.*.rule_type'  => 'required|string',
            'rules.*.is_active'  => 'boolean',
            'rules.*.value'      => 'nullable|array',
            'rules.*.note'       => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($treatment, $data) {
            // Delete all existing rules for fresh save
            $treatment->allRules()->delete();

            foreach ($data['rules'] ?? [] as $row) {
                if (empty($row['is_active'])) continue;
                TreatmentRule::create([
                    'treatment_id' => $treatment->id,
                    'rule_type'    => $row['rule_type'],
                    'value'        => $row['value'] ?? null,
                    'note'         => $row['note'] ?? null,
                    'is_active'    => true,
                ]);
            }
        });

        return back()->with('success', 'Rules saved.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // MEDIA — upload (Media tab)
    // ══════════════════════════════════════════════════════════════════════════

    public function uploadMedia(Request $request, Treatment $treatment)
    {
        $data = $request->validate([
            'media_type'   => 'required|in:image,video,pdf,consent_template,pre_care_sheet,post_care_sheet,protocol_doc',
            'label'        => 'required|string|max:255',
            'file'         => 'nullable|file|max:51200', // 50MB max
            'external_url' => 'nullable|url',
        ]);

        $filePath  = null;
        $mimeType  = null;
        $fileSize  = null;

        if ($request->hasFile('file')) {
            $file      = $request->file('file');
            $filePath  = $file->store("treatments/{$treatment->id}/media", 'public');
            $mimeType  = $file->getMimeType();
            $fileSize  = $file->getSize();
        }

        TreatmentMedia::create([
            'treatment_id' => $treatment->id,
            'media_type'   => $data['media_type'],
            'label'        => $data['label'],
            'file_path'    => $filePath,
            'external_url' => $data['external_url'] ?? null,
            'mime_type'    => $mimeType,
            'file_size'    => $fileSize,
            'uploaded_by'  => Auth::id(),
            'sort_order'   => $treatment->media()->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Media added.');
    }

    public function deleteMedia(TreatmentMedia $media)
    {
        if ($media->file_path) {
            Storage::disk('public')->delete($media->file_path);
        }
        $media->delete();
        return back()->with('success', 'Media removed.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // INTELLIGENCE — save consult-assist knowledge fields (Intelligence tab) P2C8
    // ══════════════════════════════════════════════════════════════════════════

    public function saveIntelligence(Request $request, Treatment $treatment)
    {
        $data = $request->validate([
            'trigger_keywords'         => 'nullable|string|max:1000',
            'patient_concerns'         => 'nullable|array',
            'patient_concerns.*'       => 'string|max:100',
            'suggested_questions'      => 'nullable|array',
            'suggested_questions.*'    => 'nullable|string|max:300',
            'suggested_investigations' => 'nullable|array',
            'suggested_investigations.*'=> 'string|max:100',
            'possible_diagnoses'       => 'nullable|array',
            'possible_diagnoses.*'     => 'nullable|string|max:200',
            'specialty_tag'            => 'nullable|string|max:50',
        ]);

        // Convert comma-separated keywords string → trimmed array
        $keywords = collect(explode(',', $data['trigger_keywords'] ?? ''))
            ->map(fn($k) => trim(strtolower($k)))
            ->filter()
            ->values()
            ->all();

        // Filter out blank entries from dynamic lists
        $questions = collect($data['suggested_questions'] ?? [])
            ->map(fn($q) => trim($q))
            ->filter()
            ->values()
            ->all();

        $diagnoses = collect($data['possible_diagnoses'] ?? [])
            ->map(fn($d) => trim($d))
            ->filter()
            ->values()
            ->all();

        $treatment->update([
            'trigger_keywords'         => $keywords ?: null,
            'patient_concerns'         => $data['patient_concerns'] ?? null,
            'suggested_questions'      => $questions ?: null,
            'suggested_investigations' => $data['suggested_investigations'] ?? null,
            'possible_diagnoses'       => $diagnoses ?: null,
            'specialty_tag'            => $data['specialty_tag'] ?: null,
        ]);

        return back()->with('success', 'Intelligence data saved.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // REVIEW — mark SOP as reviewed (Review tab)
    // ══════════════════════════════════════════════════════════════════════════

    public function markReviewed(Request $request, Treatment $treatment)
    {
        $data = $request->validate([
            'review_notes'  => 'nullable|string',
            'next_review_at'=> 'nullable|date|after:today',
        ]);

        $sop = $treatment->sops()->whereIn('status', ['active', 'under_review'])->first();

        if (!$sop) {
            return back()->with('error', 'No active SOP to review.');
        }

        $sop->update([
            'status'          => 'active',
            'last_reviewed_at'=> now()->toDateString(),
            'next_review_at'  => $data['next_review_at'] ?? null,
            'reviewed_by'     => Auth::id(),
            'review_notes'    => $data['review_notes'] ?? null,
        ]);

        // Archive older active SOPs
        TreatmentSop::where('treatment_id', $treatment->id)
            ->where('id', '!=', $sop->id)
            ->where('status', 'active')
            ->update(['status' => 'archived']);

        return back()->with('success', 'SOP marked as reviewed.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // API — for treatment plan / billing dropdowns
    // ══════════════════════════════════════════════════════════════════════════

    // ══════════════════════════════════════════════════════════════════════════
    // PRINT — patient-facing instruction sheet
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Printable / shareable instruction sheet.
     * type: pre_op | post_op | consent
     */
    public function printView(Treatment $treatment, string $type)
    {
        $treatment->load(['category', 'activeSop', 'media']);

        $allowed = ['pre_op', 'post_op', 'consent'];
        abort_unless(in_array($type, $allowed), 404);

        // Map type → SOP field + uploaded PDF media type
        $map = [
            'pre_op'  => ['field' => 'pre_instructions',  'media_type' => 'pre_care_sheet',   'title' => 'Pre-Treatment Instructions'],
            'post_op' => ['field' => 'post_instructions', 'media_type' => 'post_care_sheet',  'title' => 'Post-Treatment Instructions'],
            'consent' => ['field' => 'consent_notes',     'media_type' => 'consent_template', 'title' => 'Consent Information'],
        ];

        $config      = $map[$type];
        $textContent = $treatment->activeSop?->{$config['field']};
        $pdfMedia    = $treatment->media
                        ->where('media_type', $config['media_type'])
                        ->first();

        return view('treatments.print', compact(
            'treatment', 'type', 'config', 'textContent', 'pdfMedia'
        ));
    }

    // ── Patient search for Share modal ────────────────────────────────────────

    public function searchPatients(Request $request)
    {
        $q = $request->get('q', '');

        $patients = Patient::when($q, fn($query) =>
            $query->where('name', 'like', "%{$q}%")
                  ->orWhere('phone', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%")
        )
        ->orderBy('name')
        ->limit(20)
        ->get(['id', 'name', 'phone', 'email']);

        return response()->json($patients);
    }

    /**
     * Returns treatment data for a given ID.
     * Used by treatment plan builder and billing to auto-fill price/GST/duration.
     */
    public function apiDetail(Treatment $treatment)
    {
        return response()->json([
            'id'                       => $treatment->id,
            'name'                     => $treatment->name,
            'code'                     => $treatment->code,
            'default_price'            => $treatment->default_price,
            'min_price'                => $treatment->min_price,
            'max_price'                => $treatment->max_price,
            'gst_pct'                  => $treatment->gst_pct,
            'default_duration_minutes' => $treatment->default_duration_minutes,
            'color'                    => $treatment->color,
            'lab_required'             => $treatment->hasRule('lab_required'),
            'consent_required'         => $treatment->hasRule('consent_required'),
            'xray_required'            => $treatment->hasRule('xray_required'),
            'min_visits'               => $treatment->ruleValue('min_visits'),
            'max_discount_pct'         => $treatment->ruleValue('max_discount_pct'),
            'pre_instructions'         => $treatment->activeSop?->pre_instructions,
            'post_instructions'        => $treatment->activeSop?->post_instructions,
        ]);
    }
}
