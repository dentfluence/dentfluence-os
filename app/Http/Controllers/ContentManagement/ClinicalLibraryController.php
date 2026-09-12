<?php

namespace App\Http\Controllers\ContentManagement;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateWatermark;
use App\Models\ClinicalFile;
use App\Models\EducationCategory;
use App\Models\Patient;
use App\Models\TreatmentVisit;
use App\Services\ClinicalLibrary\ClinicalFileUploadService;
use App\Services\ClinicalLibrary\ClinicalLibrarySearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ClinicalLibraryController extends Controller
{
    // ── Clinical Library Workspace Dashboard (Phase 9 — wired to real data) ──

    public function dashboard()
    {
        // ── Stat chips ─────────────────────────────────────────────────────
        $totalFiles    = ClinicalFile::count();
        $totalPatients = ClinicalFile::distinct('patient_id')->count('patient_id');
        $filesThisMonth= ClinicalFile::whereMonth('created_at', now()->month)
                            ->whereYear('created_at', now()->year)
                            ->count();
        $pendingReview = ClinicalFile::marketingEligible()
                            ->where('marketing_status', 'pending')
                            ->count();

        // ── Resume Work — 6 most-recently-active patients ──────────────────
        // Get the last 6 distinct patient_ids by most recent upload, with counts
        $recentPatientIds = ClinicalFile::select('patient_id')
            ->selectRaw('MAX(created_at) as last_upload')
            ->selectRaw('COUNT(*) as file_count')
            ->groupBy('patient_id')
            ->orderByDesc('last_upload')
            ->limit(6)
            ->get();

        $recentPatients = collect();
        if ($recentPatientIds->isNotEmpty()) {
            $patientMap = Patient::whereIn('id', $recentPatientIds->pluck('patient_id'))
                ->get(['id', 'name'])
                ->keyBy('id');

            $recentPatients = $recentPatientIds->map(function ($row) use ($patientMap) {
                $patient = $patientMap[$row->patient_id] ?? null;
                if (!$patient) return null;

                // Get last uploaded file for thumbnail
                $lastFile = ClinicalFile::where('patient_id', $row->patient_id)
                    ->latest()
                    ->first();

                return [
                    'patient_id'  => $row->patient_id,
                    'name'        => $patient->name,
                    'file_count'  => $row->file_count,
                    'last_upload' => Carbon::parse($row->last_upload)->diffForHumans(),
                    'thumb_url'   => $lastFile?->isImage() ? $lastFile->thumbnail_url : null,
                ];
            })->filter()->values();
        }

        // ── Recent Uploads — last 12 files across all patients ─────────────
        $recentUploads = ClinicalFile::with(['patient:id,name', 'uploadedBy:id,name'])
            ->latest()
            ->limit(12)
            ->get();

        // ── Needs Attention ────────────────────────────────────────────────
        // 1) Pending marketing approval
        $pendingApproval = ClinicalFile::marketingEligible()
            ->with('patient:id,name')
            ->where('marketing_status', 'pending')
            ->latest()
            ->limit(8)
            ->get();

        // 2) Visits from the last 30 days that have 0 clinical files
        $visitsWithNoFiles = TreatmentVisit::whereDoesntHave('clinicalFiles')
            ->where('visit_date', '>=', now()->subDays(30))
            ->with('patient:id,name')
            ->orderByDesc('visit_date')
            ->limit(8)
            ->get();

        // Patients list for upload modal
        $patients = Patient::orderBy('name')->get(['id', 'name']);

        // ── Storage ────────────────────────────────────────────────────────
        // file_size has always been recorded and never shown. Storage stops
        // being free the moment this is multi-tenant, and nobody can manage a
        // number they cannot see. The watermarked display copies are counted
        // separately because they are the half that can be regenerated — the
        // originals are the half that can never be recreated.
        $storage = [
            'files'     => $totalFiles,
            'bytes'     => (int) ClinicalFile::sum('file_size'),
            'by_type'   => ClinicalFile::selectRaw('file_type, COUNT(*) as files, SUM(file_size) as bytes')
                                ->groupBy('file_type')
                                ->orderByDesc('bytes')
                                ->get(),
            'heaviest'  => ClinicalFile::with('patient:id,name')
                                ->selectRaw('patient_id, COUNT(*) as files, SUM(file_size) as bytes')
                                ->groupBy('patient_id')
                                ->orderByDesc('bytes')
                                ->limit(5)
                                ->get(),
            'stamped'   => ClinicalFile::whereNotNull('watermarked_path')->count(),
            'unstamped' => ClinicalFile::whereNull('watermarked_path')->count(),
        ];

        // ── Options for the search drawer's filter strip ───────────────────
        // Doctors are whoever has actually uploaded something, not every user —
        // a dropdown of thirty names where three have files is a worse answer
        // than no dropdown at all.
        $searchOptions = [
            'stages' => [
                'before'   => 'Before',
                'during'   => 'During',
                'after'    => 'After',
                'followup' => 'Follow-up',
            ],
            'file_types' => [
                'photo'          => 'Photo',
                'xray'           => 'X-ray',
                'opg'            => 'OPG',
                'cbct'           => 'CBCT',
                'intraoral_scan' => 'Scan',
                'stl'            => 'STL',
                'pdf'            => 'PDF',
                'consent'        => 'Consent',
                'lab_slip'       => 'Lab slip',
            ],
            'treatments' => ClinicalFile::TREATMENT_CATEGORIES,
            'doctors'    => \App\Models\User::whereIn(
                    'id',
                    ClinicalFile::whereNotNull('uploaded_by')->distinct()->pluck('uploaded_by')
                )->orderBy('name')->get(['id', 'name']),
            'teeth'      => ClinicalLibrarySearchService::toothOptions(),
        ];

        return view('clinical-library.dashboard', compact(
            'totalFiles',
            'totalPatients',
            'filesThisMonth',
            'pendingReview',
            'recentPatients',
            'recentUploads',
            'pendingApproval',
            'visitsWithNoFiles',
            'patients',
            'searchOptions',
            'storage',
        ));
    }

    // ── Upload Clinical Files ─────────────────────────────────────────────────

    /**
     * POST /clinical-library/upload
     *
     * Every upload path in the app goes through ClinicalFileUploadService — the
     * one place that decides where a clinical file is stored, what its
     * treatment_category is, and whether it enters the marketing review queue.
     * This method used to duplicate that logic inline, which cost it three
     * things the shared service does for free: watermark generation,
     * TreatmentCategoryDetector, and MarketingEligibilityDetector.
     *
     * It also hard-set marketing_status = 'pending' on every row. That looked
     * harmless and was not: MarketingEligibilityDetector only ever touches rows
     * where marketing_status IS NULL (so it can never re-open a file a human has
     * already approved or rejected). A file created here could therefore NEVER
     * be auto-flagged when its before/after partner arrived. Do not reintroduce
     * a default marketing_status here — eligibility is decided by the detector
     * or by a human, never by the upload form.
     */
    public function store(Request $request, ClinicalFileUploadService $uploads)
    {
        $validated = $request->validate([
            'patient_id'  => 'required|exists:patients,id',
            'files'       => 'required|array|min:1',
            'files.*'     => ClinicalFileUploadService::validationRule(), // 50 MB, allowlisted formats only
            'procedure'   => 'nullable|string|max:100',
            'stage'       => 'nullable|in:general,before,during,after,followup',
            'file_type'   => 'nullable|in:photo,video,xray,opg,cbct,stl,intraoral_scan,pdf,consent,estimate,invoice,lab_slip,other',
            'tooth_number'=> 'nullable|string|max:10',
            'notes'       => 'nullable|string|max:1000',
        ]);

        $count = 0;

        foreach ($request->file('files') as $file) {
            $uploads->store($file, [
                'patient_id'   => $validated['patient_id'],
                'procedure'    => $validated['procedure']     ?? null,
                'stage'        => $validated['stage']         ?? 'general',
                'file_type'    => $validated['file_type']     ?? null, // null => detected from MIME
                'tooth_number' => $validated['tooth_number']  ?? null,
                'notes'        => $validated['notes']         ?? null,
                'source_type'  => 'manual_upload',
            ]);

            $count++;
        }

        return back()->with('success', "{$count} file(s) uploaded successfully.");
    }

    /**
     * POST /clinical-library/restamp
     *
     * Changing a watermark setting only affects NEW uploads — the stamped copy
     * of an existing file was baked when it was uploaded. This clears those
     * copies and queues them again, so a settings change can actually be seen
     * on the library you already have.
     *
     * The ORIGINAL is never touched. Only the derived wm_*.jpg is regenerated,
     * and the old one is simply overwritten at the same path.
     *
     * ⚠ Needs a queue worker running, and one started BEFORE composer installed
     * intervention/image will keep failing with a stale autoloader — restart it
     * after any install rather than trusting the log line.
     */
    public function restamp(Request $request)
    {
        ClinicalFile::whereNotNull('watermarked_path')->update(['watermarked_path' => null]);

        $queued = 0;

        ClinicalFile::whereNull('watermarked_path')
            ->cursor()
            ->each(function (ClinicalFile $file) use (&$queued) {
                if ($file->isImage()) {
                    GenerateWatermark::dispatch($file);
                    $queued++;
                }
            });

        return back()->with('success', $queued === 0
            ? 'Nothing to re-stamp — there are no viewable images in the library yet.'
            : "{$queued} image(s) queued for re-stamping. They update as the queue worker gets to them.");
    }

    // ── Content Manager index ─────────────────────────────────────────────────

    /** Eligibility lane behind each tab. */
    private const TAB_LANES = [
        'marketing'    => 'marketing',
        'education'    => 'education',
        'case-library' => 'case_library',
        'teaching'     => 'teaching',
        'research'     => 'research',
    ];

    /**
     * GET /content-management
     *
     * Reads through ClinicalLibrarySearchService — the SAME brain behind the
     * dashboard search drawer and GET /clinical-library/search. It used to carry
     * its own filter code, and the two disagreed: the Treatment dropdown matched
     * a hardcoded specialty list against free-text `procedure` while the page
     * beside it filtered on `treatment_category`, so the same question got two
     * answers depending on which screen you asked. One brain now, no drift.
     *
     * Only the ACTIVE tab is loaded. Every tab used to be queried on every page
     * load, two of them with an unbounded ->get() that would fetch the entire
     * library once this clinic has a real one. Tabs are now links, so each has
     * its own URL and the filters survive a reload or a shared link.
     */
    public function index(Request $request, ClinicalLibrarySearchService $search)
    {
        $activeTab = array_key_exists($request->get('tab'), self::TAB_LANES)
            ? $request->get('tab')
            : 'marketing';

        $filters = array_filter($request->only([
            'q', 'tooth', 'treatment_category', 'stage', 'file_type',
            'doctor_id', 'period', 'approval', 'ready',
        ]), fn ($v) => $v !== null && $v !== '');

        // ── Tab badge counts — the whole library, never the filtered slice, so
        // the badges do not move around as someone narrows a search.
        $tabCounts = [
            'marketing'    => ClinicalFile::marketingEligible()->count(),
            'education'    => ClinicalFile::educationEligible()->count(),
            'case-library' => ClinicalFile::caseLibraryEligible()
                                ->distinct('patient_id')
                                ->count('patient_id'),
            'teaching'     => ClinicalFile::teachingEligible()->count(),
            'research'     => ClinicalFile::researchEligible()->count(),
        ];

        // ── Translate the filter bar into the search service's vocabulary ──
        $input = $filters;
        $input['eligible_' . self::TAB_LANES[$activeTab]] = true;
        $input['per_page'] = 48;

        // "Ready to post" is both halves or neither — a photo with consent but
        // no approval is not publishable, and neither is the reverse.
        //
        // Order matters: Ready sets marketing=approved, so an explicit Approval
        // choice is applied AFTER it and wins. Picking Approval=Pending together
        // with Ready used to silently become Approved — a filter that changes
        // another filter's answer is worse than one that does nothing.
        if (! empty($filters['ready'])) {
            $input['consent']   = 'given';
            $input['marketing'] = 'approved';
        }

        if (! empty($filters['approval'])) {
            $input['marketing'] = $filters['approval'];
        }

        if (! empty($filters['period'])) {
            $input['from'] = now()->subDays((int) $filters['period'])->toDateString();
        }

        $files = $search->search($input);

        // ── Shapes the existing tab partials expect ────────────────────────
        $marketingFiles   = $files;
        $marketingByMonth = $files->getCollection()
            ->groupBy(fn ($f) => $f->captured_at ? $f->captured_at->format('F Y') : 'Unknown');

        $educationFiles = $files->getCollection();

        // Case Library is anonymised HERE, in the controller.
        // NEVER expose patient name / patient_id / contact details in $caseFiles.
        $caseFiles = $files->getCollection()
            ->groupBy(fn ($f) => $f->patient_id . '_' . ($f->procedure ?? 'general'))
            ->map(function ($group) {
                $first = $group->first();

                // Anonymous label derived from patient_id — never the real one.
                $letter = chr(65 + ($first->patient_id % 26));
                $number = str_pad(($first->patient_id * 7 + 13) % 1000, 3, '0', STR_PAD_LEFT);

                $beforeFile = $group->firstWhere('stage', 'before') ?? $group->first();
                $afterFile  = $group->firstWhere('stage', 'after')  ?? $group->last();

                $dates    = $group->pluck('captured_at')->filter()->sort();
                $duration = $dates->count() >= 2
                    ? $dates->first()->format('M Y') . '–' . $dates->last()->format('M Y')
                    : ($dates->first()?->format('M Y') ?? '—');

                return [
                    'id'           => 'cl_' . $first->id,
                    'anon_id'      => "Case #{$letter}{$number}",
                    'procedure'    => $first->procedure ?? 'General',
                    'tooth'        => $first->tooth_number ?? '—',
                    'doctor'       => $first->uploadedBy?->name ?? '—',
                    'duration'     => $duration,
                    'before_url'   => ($beforeFile?->isImage()) ? $beforeFile->display_url : null,
                    'after_url'    => ($afterFile?->isImage())  ? $afterFile->display_url  : null,
                    'stage_counts' => $group->groupBy('stage')->map->count()->toArray(),
                    'tags'         => $group->pluck('tags')->flatten()->filter()->unique()->values()->toArray(),
                    'rating'       => $first->content_rating ?? 0,
                    'file_count'   => $group->count(),
                ];
            })
            ->values();

        $casesByProcedure = $caseFiles->groupBy('procedure');

        // Filter-bar options — the same fixed vocabularies the search understands,
        // not a hardcoded list that can drift away from what is stored.
        $filterOptions = [
            'treatments' => ClinicalFile::TREATMENT_CATEGORIES,
            'stages'     => ['before' => 'Before', 'during' => 'During', 'after' => 'After', 'followup' => 'Follow-up'],
            'file_types' => [
                'photo' => 'Photo', 'xray' => 'X-ray', 'opg' => 'OPG', 'cbct' => 'CBCT',
                'intraoral_scan' => 'Scan', 'stl' => 'STL', 'pdf' => 'PDF',
                'consent' => 'Consent', 'lab_slip' => 'Lab slip',
            ],
            'doctors'    => \App\Models\User::whereIn(
                    'id',
                    ClinicalFile::whereNotNull('uploaded_by')->distinct()->pluck('uploaded_by')
                )->orderBy('name')->get(['id', 'name']),
            'teeth'      => ClinicalLibrarySearchService::toothOptions(),
        ];

        return view('content-management.index', compact(
            'activeTab',
            'files',
            'marketingFiles',
            'marketingByMonth',
            'educationFiles',
            'caseFiles',
            'casesByProcedure',
            'tabCounts',
            'filters',
            'filterOptions',
        ));
    }

    // ── Marketing Approval Actions ─────────────────────────────────────────────

    /**
     * PUT /clinical-library/files/{file}/approve
     * Approve a file for marketing use.
     */
    public function approveFile(Request $request, ClinicalFile $file): JsonResponse
    {
        $file->update(['marketing_status' => 'approved']);

        return response()->json([
            'success' => true,
            'status'  => 'approved',
            'message' => 'File approved for marketing use.',
        ]);
    }

    /**
     * PUT /clinical-library/files/{file}/reject
     * Reject a file from marketing use.
     */
    public function rejectFile(Request $request, ClinicalFile $file): JsonResponse
    {
        $file->update(['marketing_status' => 'rejected']);

        return response()->json([
            'success' => true,
            'status'  => 'rejected',
            'message' => 'File rejected from marketing use.',
        ]);
    }

    // ── Generic Education Library tab (separate route from Content Manager) ───

    public function education(Request $request)
    {
        $categorySlug = $request->get('category');

        $categories = EducationCategory::active()
            ->withCount('activeTreatments')
            ->orderBy('sort_order')
            ->get();

        $treatmentsQuery = \App\Models\EducationTreatment::active()
            ->with(['category', 'media']);

        if ($categorySlug && $categorySlug !== 'all') {
            $treatmentsQuery->whereHas('category', fn($q) => $q->where('slug', $categorySlug));
        }

        $treatments = $treatmentsQuery->orderBy('sort_order')->paginate(12)->withQueryString();

        return view('content-management.education', compact('categories', 'treatments', 'categorySlug'));
    }

    // ── AJAX: Case Viewer panel (anonymised) ──────────────────────────────────

    public function caseViewer(Request $request): JsonResponse
    {
        // Accepts a group key: "cl_{first_file_id}" — we look up the file to get
        // patient_id + procedure, then fetch the full group.
        // Patient name / contact details are NEVER returned.
        $fileId = (int) str_replace('cl_', '', $request->get('id', ''));

        if (!$fileId) {
            return response()->json(['error' => 'id required'], 422);
        }

        $anchor = ClinicalFile::caseLibraryEligible()->find($fileId);
        if (!$anchor) {
            return response()->json(['error' => 'Case not found'], 404);
        }

        // Fetch all files in this case (same patient + procedure)
        $files = ClinicalFile::caseLibraryEligible()
            ->where('patient_id', $anchor->patient_id)
            ->where('procedure', $anchor->procedure)
            ->with('uploadedBy:id,name')
            ->orderBy('captured_at')
            ->get();

        // Stage groups
        $stages = [
            'before'   => $files->where('stage', 'before')->values(),
            'during'   => $files->where('stage', 'during')->values(),
            'after'    => $files->where('stage', 'after')->values(),
            'followup' => $files->where('stage', 'followup')->values(),
        ];

        // Anonymous ID — derived from patient_id, never the real ID
        $letter = chr(65 + ($anchor->patient_id % 26));
        $number = str_pad(($anchor->patient_id * 7 + 13) % 1000, 3, '0', STR_PAD_LEFT);
        $anonId = "Case #{$letter}{$number}";

        return response()->json([
            // ── Anonymised identity — no patient name / real ID ──
            'anon_id'   => $anonId,
            'procedure' => $anchor->procedure ?? 'General',
            'tooth'     => $anchor->tooth_number ?? '—',

            // ── Clinical data ──
            'file_count'      => $files->count(),
            'start_date'      => $files->min('captured_at')?->format('d M Y'),
            'completion_date' => optional($files->where('stage', 'after')->sortByDesc('captured_at')->first())?->captured_at?->format('d M Y'),

            // ── Stage groups ──
            'stages' => [
                'before'   => $this->serializeClinicalFiles($stages['before']),
                'during'   => $this->serializeClinicalFiles($stages['during']),
                'after'    => $this->serializeClinicalFiles($stages['after']),
                'followup' => $this->serializeClinicalFiles($stages['followup']),
            ],

            'counts' => [
                'photos' => $files->whereIn('file_type', ['photo'])->count(),
                'xrays'  => $files->whereIn('file_type', ['xray', 'opg', 'cbct'])->count(),
                'scans'  => $files->where('file_type', 'intraoral_scan')->count(),
                'videos' => $files->where('file_type', 'video')->count(),
            ],

            'tags' => $files->pluck('tags')->flatten()->filter()->unique()->values(),
        ]);
    }

    // ── AJAX: Universal search ────────────────────────────────────────────────

    /**
     * GET /clinical-library/search
     *
     * The ONE search endpoint. It drives the Clinical Library dashboard's search
     * drawer and the Content Manager's filter bar, so those two surfaces can
     * never answer the same question differently again — which is exactly what
     * they did before, one matching a hardcoded specialty list against free-text
     * `procedure` while the other filtered on `treatment_category`.
     *
     * Takes one line of plain text plus any explicit chips:
     *   ?q=26                    every file on tooth 26
     *   ?q=sharma implant after  that patient's implant after-photos
     *   ?q=opg 36 pending        OPGs on 36 whose treatment is not yet done
     *
     * `interpreted` says what each word was taken to mean so the UI can show it
     * back as removable chips — a search that silently reinterprets the typing
     * is worse than one that shows its working.
     */
    public function search(Request $request, ClinicalLibrarySearchService $search): JsonResponse
    {
        $results = $search->search($request->all());

        return response()->json([
            'query'       => (string) $request->get('q', ''),
            'interpreted' => $search->interpretation($request->get('q')),
            'total'       => $results->total(),
            'per_page'    => $results->perPage(),
            'current_page'=> $results->currentPage(),
            'last_page'   => $results->lastPage(),
            'next_page_url' => $results->nextPageUrl(),
            'results'     => $results->getCollection()->map(fn (ClinicalFile $f) => [
                'id'                 => $f->id,
                'patient_id'         => $f->patient_id,
                'patient_name'       => $f->patient?->name,
                'title'              => $f->title ?: $f->original_filename,
                'procedure'          => $f->procedure,
                'treatment_category' => $f->treatment_category,
                'treatment_label'    => $f->treatment_category_label,
                'tooth_number'       => $f->tooth_number,
                'stage'              => $f->stage,
                'stage_label'        => $f->stage_label,
                'file_type'          => $f->file_type,
                'file_type_label'    => $f->file_type_label,
                'is_image'           => $f->isImage(),
                'no_preview_reason'  => $f->no_preview_reason,
                'thumbnail_url'      => $f->thumbnail_url,
                'display_url'        => $f->display_url,
                'captured_at'        => $f->captured_at?->format('d M Y'),
                'uploaded_by'        => $f->uploadedBy?->name,
                'file_size'          => $f->file_size_human,
                'consent_status'     => $f->consent_status,
                'marketing_status'   => $f->marketing_status,
                'tags'               => $f->tags ?? [],
            ])->values(),
        ]);
    }

    // ── AJAX: Search suggestions ──────────────────────────────────────────────

    public function searchSuggest(Request $request): JsonResponse
    {
        $q = $request->get('q', '');
        if (strlen($q) < 2) return response()->json([]);

        // Treatment / procedure suggestions from clinical_files
        $treatments = ClinicalFile::where('procedure', 'like', "%{$q}%")
            ->distinct()
            ->limit(5)
            ->pluck('procedure')
            ->filter()
            ->map(fn($t) => ['type' => 'treatment', 'label' => $t, 'value' => $t]);

        // Tag suggestions
        $tags = ClinicalFile::whereJsonContains('tags', $q)
            ->distinct()
            ->limit(3)
            ->pluck('tags')
            ->flatten()
            ->filter(fn($t) => str_contains(strtolower($t), strtolower($q)))
            ->unique()
            ->take(3)
            ->map(fn($t) => ['type' => 'tag', 'label' => $t, 'value' => $t]);

        return response()->json([...$treatments, ...$tags]);
    }

    public function educationManage(Request $request)
    {
        return app(EducationContentController::class)->manage($request);
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    /**
     * Apply shared filters (treatment, stage, date range, tag) to a query builder.
     */
    private function applyCommonFilters($query, array $filters): void
    {
        if (!empty($filters['treatment'])) {
            $query->where('procedure', $filters['treatment']);
        }
        if (!empty($filters['stage'])) {
            $query->where('stage', $filters['stage']);
        }
        if (!empty($filters['date_from'])) {
            $query->where('captured_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('captured_at', '<=', $filters['date_to']);
        }
        if (!empty($filters['tag'])) {
            $query->whereJsonContains('tags', $filters['tag']);
        }
    }

    /**
     * Map human date_range shortcuts to actual date_from / date_to values.
     */
    private function resolveDateRange(array $filters): array
    {
        if (empty($filters['date_range'])) return $filters;

        $now = now();
        $filters['date_from'] = match ($filters['date_range']) {
            '30d'  => $now->copy()->subDays(30)->toDateString(),
            '90d'  => $now->copy()->subDays(90)->toDateString(),
            '6m'   => $now->copy()->subMonths(6)->toDateString(),
            '1y'   => $now->copy()->subYear()->toDateString(),
            '2y'   => $now->copy()->subYears(2)->toDateString(),
            default => null,
        };
        $filters['date_to'] = $now->toDateString();

        return $filters;
    }

    /**
     * Serialise a ClinicalFile collection for JSON responses (case viewer).
     * Returns only display-safe fields — no patient identity.
     */
    private function serializeClinicalFiles($files): array
    {
        return $files->map(fn($f) => [
            'id'            => $f->id,
            'file_type'     => $f->file_type,
            'file_type_label' => $f->file_type_label,
            'display_url'   => $f->display_url,
            'thumbnail_url' => $f->thumbnail_url,
            'stage'         => $f->stage,
            'stage_label'   => $f->stage_label,
            'captured_at'   => $f->captured_at?->format('d M Y'),
            'notes'         => $f->notes,
            'tooth_number'  => $f->tooth_number,
            'is_image'      => $f->isImage(),
        ])->toArray();
    }
}
