<?php

namespace App\Http\Controllers;

use App\Models\Treatment;
use App\Models\TreatmentCategory;
use App\Models\TreatmentSop;
use App\Models\TreatmentRule;
use App\Models\TreatmentMedia;
use App\Models\TreatmentPlan;
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

        // Usage stats — where this treatment appears
        $usageCount = DB::table('treatment_plan_items')
            ->where('treatment_name', $treatment->name) // legacy name-match
            ->count();

        $categories = TreatmentCategory::active()->orderBy('name')->get();

        return view('treatments.show', compact('treatment', 'tab', 'usageCount', 'categories'));
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
            'sort_order'            => 'nullable|integer|min:0',
            'is_active'             => 'boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active');
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
