<?php

namespace App\Http\Controllers\ContentManagement;

use App\Http\Controllers\Controller;
use App\Models\ClinicalFile;
use App\Models\EducationCategory;
use App\Models\Patient;
use App\Models\TreatmentVisit;
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
        ));
    }

    // ── Upload Clinical Files ─────────────────────────────────────────────────

    public function store(Request $request)
    {
        $validated = $request->validate([
            'patient_id'  => 'required|exists:patients,id',
            'files'       => 'required|array|min:1',
            'files.*'     => 'required|file|max:51200', // 50 MB per file
            'procedure'   => 'nullable|string|max:100',
            'stage'       => 'nullable|in:general,before,during,after,followup',
            'file_type'   => 'nullable|in:photo,video,xray,opg,cbct,stl,intraoral_scan,pdf,consent,estimate,invoice,lab_slip,other',
            'tooth_number'=> 'nullable|string|max:10',
            'notes'       => 'nullable|string|max:1000',
        ]);

        $patientId = $validated['patient_id'];
        $count     = 0;

        foreach ($request->file('files') as $file) {
            // Detect file type from MIME if not provided
            $fileType = $validated['file_type'] ?? $this->guessFileType($file);

            // Security (Phase A): clinical files go to the PRIVATE disk and are
            // served only via the authenticated SecureMediaController route.
            $path = $file->store("clinical-files/{$patientId}", 'local');

            ClinicalFile::create([
                'patient_id'       => $patientId,
                'procedure'        => $validated['procedure'] ?? null,
                'stage'            => $validated['stage']     ?? 'general',
                'file_type'        => $fileType,
                'tooth_number'     => $validated['tooth_number'] ?? null,
                'notes'            => $validated['notes']     ?? null,
                'disk'             => 'local',
                'path'             => $path,
                'original_filename'=> $file->getClientOriginalName(),
                'mime_type'        => $file->getMimeType(),
                'file_size'        => $file->getSize(),
                'captured_at'      => now(),
                'uploaded_by'      => auth()->id(),
                'source_type'      => 'manual_upload',
                'marketing_status' => 'pending',
            ]);

            $count++;
        }

        return back()->with('success', "{$count} file(s) uploaded successfully.");
    }

    /**
     * Guess a file_type enum value from the uploaded file's MIME type.
     */
    private function guessFileType(\Illuminate\Http\UploadedFile $file): string
    {
        $mime = $file->getMimeType();

        if (str_starts_with($mime, 'image/')) return 'photo';
        if (str_starts_with($mime, 'video/')) return 'video';
        if ($mime === 'application/pdf')       return 'pdf';

        return 'other';
    }

    // ── Content Manager index — all tabs ──────────────────────────────────────

    public function index(Request $request)
    {
        // ── Filters (shared across tabs) ───────────────────────────────────
        $filters = $request->only([
            'treatment', 'stage', 'approval', 'tag',
            'date_from', 'date_to', 'date_range', 'sort',
        ]);
        $filters = $this->resolveDateRange($filters);

        // ── Tab counts for badge display ───────────────────────────────────
        $tabCounts = [
            'marketing'    => ClinicalFile::marketingEligible()->count(),
            'education'    => ClinicalFile::educationEligible()->count(),
            'case-library' => ClinicalFile::caseLibraryEligible()
                                ->distinct('patient_id')
                                ->count('patient_id'),
            'teaching'     => ClinicalFile::teachingEligible()->count(),
            'research'     => ClinicalFile::researchEligible()->count(),
        ];

        // ── Marketing files ────────────────────────────────────────────────
        $marketingQuery = ClinicalFile::marketingEligible()
            ->with('uploadedBy:id,name');

        $this->applyCommonFilters($marketingQuery, $filters);

        if (!empty($filters['approval'])) {
            $marketingQuery->where('marketing_status', $filters['approval']);
        }

        $marketingFiles = $marketingQuery
            ->latest('captured_at')
            ->paginate(48)
            ->withQueryString();

        // Group marketing files by calendar month for the view
        $marketingByMonth = $marketingFiles->getCollection()
            ->groupBy(fn($f) => $f->captured_at
                ? $f->captured_at->format('F Y')
                : 'Unknown');

        // ── Education files ────────────────────────────────────────────────
        $educationQuery = ClinicalFile::educationEligible();
        $this->applyCommonFilters($educationQuery, $filters);
        $educationFiles = $educationQuery->latest('captured_at')->get();

        // ── Case Library files — anonymised in controller ──────────────────
        // NEVER expose patient name / patient_id / contact details in $caseFiles.
        $caseQuery = ClinicalFile::caseLibraryEligible()
            ->with('uploadedBy:id,name');
        $this->applyCommonFilters($caseQuery, $filters);
        $rawCaseFiles = $caseQuery->latest('captured_at')->get();

        // Group by patient+procedure → one "case" per combination
        $caseFiles = $rawCaseFiles
            ->groupBy(fn($f) => $f->patient_id . '_' . ($f->procedure ?? 'general'))
            ->map(function ($group) {
                $first = $group->first();

                // Anonymous ID derived from patient_id — no real patient data exposed
                $letter = chr(65 + ($first->patient_id % 26));
                $number = str_pad(($first->patient_id * 7 + 13) % 1000, 3, '0', STR_PAD_LEFT);
                $anonId = "Case #{$letter}{$number}";

                // Before / after representative files
                $beforeFile = $group->firstWhere('stage', 'before') ?? $group->first();
                $afterFile  = $group->firstWhere('stage', 'after')  ?? $group->last();

                // Date range
                $dates    = $group->pluck('captured_at')->filter()->sort();
                $duration = $dates->count() >= 2
                    ? $dates->first()->format('M Y') . '–' . $dates->last()->format('M Y')
                    : ($dates->first()?->format('M Y') ?? '—');

                return [
                    // ID used only for UI interactions — NOT patient_id
                    'id'           => 'cl_' . $first->id,
                    'anon_id'      => $anonId,
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

        // Group by procedure for the view's grouped display
        $casesByProcedure = $caseFiles->groupBy('procedure');

        // ── Distinct treatment options for filter dropdowns ────────────────
        $treatmentOptions = ClinicalFile::distinct()
            ->orderBy('procedure')
            ->pluck('procedure')
            ->filter()
            ->values();

        return view('content-management.index', compact(
            'marketingFiles',
            'marketingByMonth',
            'educationFiles',
            'caseFiles',
            'casesByProcedure',
            'tabCounts',
            'filters',
            'treatmentOptions',
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
