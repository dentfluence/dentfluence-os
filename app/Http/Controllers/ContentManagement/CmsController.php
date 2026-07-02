<?php

namespace App\Http\Controllers\ContentManagement;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\CmsMedia;
use App\Services\ContentManagement\CmsSearchService;
use Illuminate\Http\Request;
use App\Models\WatermarkSetting;
use App\Models\User;
use App\Models\ClinicalFile;

class CmsController extends Controller
{
    public function __construct(private CmsSearchService $searchService) {}

    // ── Shared data needed by every index() render ────────────────
    private function sharedViewData(): array
    {
        $toothOptions = collect([
            18,
            17,
            16,
            15,
            14,
            13,
            12,
            11,
            21,
            22,
            23,
            24,
            25,
            26,
            27,
            28,
            31,
            32,
            33,
            34,
            35,
            36,
            37,
            38,
            41,
            42,
            43,
            44,
            45,
            46,
            47,
            48,
        ]);

        // Phase 8E — cms_media dropped; source is now clinical_files.procedure
        $treatments = \App\Models\ClinicalFile::distinct()
            ->orderBy('procedure')
            ->pluck('procedure')
            ->filter()
            ->values();

        $patients = Patient::orderBy('name')->get(['id', 'name']);

        // Doctors = all active users (filter by role if available)
        $doctors = User::orderBy('name')->get(['id', 'name']);

        // Tag options from clinical_media tags column
        $tagOptions = $this->searchService->getTagOptions();

        $stats = $this->searchService->getStats();

        $treatmentOptions = $treatments;

        // Tab badge counts — mirrors ClinicalLibraryController logic
        $tabCounts = [
            'marketing'    => ClinicalFile::marketingEligible()->count(),
            'education'    => ClinicalFile::educationEligible()->count(),
            'case-library' => ClinicalFile::caseLibraryEligible()->distinct('patient_id')->count('patient_id'),
            'teaching'     => ClinicalFile::teachingEligible()->count(),
            'research'     => ClinicalFile::researchEligible()->count(),
        ];

        // ── Marketing tab data ─────────────────────────────────────────────
        $marketingFiles = ClinicalFile::marketingEligible()
            ->with('uploadedBy:id,name')
            ->latest('captured_at')
            ->paginate(48)
            ->withQueryString();

        $marketingByMonth = $marketingFiles->getCollection()
            ->groupBy(fn($f) => $f->captured_at
                ? $f->captured_at->format('F Y')
                : 'Unknown');

        // ── Education tab data ─────────────────────────────────────────────
        $educationFiles = ClinicalFile::educationEligible()
            ->latest('captured_at')
            ->get();

        // ── Case Library tab data (anonymised) ────────────────────────────
        $rawCaseFiles = ClinicalFile::caseLibraryEligible()
            ->with('uploadedBy:id,name')
            ->latest('captured_at')
            ->get();

        $caseFiles = $rawCaseFiles
            ->groupBy(fn($f) => $f->patient_id . '_' . ($f->procedure ?? 'general'))
            ->map(function ($group) {
                $first      = $group->first();
                $letter     = chr(65 + ($first->patient_id % 26));
                $number     = str_pad(($first->patient_id * 7 + 13) % 1000, 3, '0', STR_PAD_LEFT);
                $anonId     = "Case #{$letter}{$number}";
                $beforeFile = $group->firstWhere('stage', 'before') ?? $group->first();
                $afterFile  = $group->firstWhere('stage', 'after')  ?? $group->last();
                $dates      = $group->pluck('captured_at')->filter()->sort();
                $duration   = $dates->count() >= 2
                    ? $dates->first()->format('M Y') . '–' . $dates->last()->format('M Y')
                    : ($dates->first()?->format('M Y') ?? '—');

                return [
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

        $casesByProcedure = $caseFiles->groupBy('procedure');

        return compact(
            'toothOptions', 'treatments', 'treatmentOptions', 'patients', 'doctors',
            'tagOptions', 'stats', 'tabCounts',
            'marketingFiles', 'marketingByMonth',
            'educationFiles',
            'caseFiles', 'casesByProcedure'
        );
    }

    // Main entry — clinical tab by default
    public function index(Request $request)
    {
        $activeTab = $request->get('tab', 'clinical');
        $filters   = $request->only(['q', 'patient_id', 'tooth', 'treatment', 'doctor_id', 'date_range', 'tag', 'sort']);
        $cases     = $this->searchService->searchCases($filters);

        return view('content-management.index', array_merge(
            $this->sharedViewData(),
            ['activeTab' => $activeTab, 'filters' => $filters, 'cases' => $cases]
        ));
    }

    // Clinical tab — full page or AJAX
    public function clinical(Request $request)
    {
        $results = $this->searchService->search($request->all());

        if ($request->ajax()) {
            return view('content-management.partials.clinical.results-table', compact('results'));
        }

        return view('content-management.index', array_merge(
            $this->sharedViewData(),
            [
                'activeTab' => 'clinical',
                'results'   => $results,
            ]
        ));
    }

    // Education tab
    public function education(Request $request)
    {
        if ($request->ajax() || $request->wantsJson()) {
            $categories = \App\Models\CmsEduCategory::withCount('items')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();

            $items = \App\Models\CmsEduItem::with('category')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->map(fn($i) => [
                    'id'          => $i->id,
                    'title'       => $i->title,
                    'description' => $i->description,
                    'media_type'  => $i->media_type,
                    'thumbnail'   => $i->thumbnail_path ? \Storage::url($i->thumbnail_path) : null,
                    'duration'    => $i->duration_seconds,
                    'photo_count' => $i->photo_count,
                    'xray_count'  => $i->xray_count,
                    'video_count' => $i->video_count,
                    'category_id' => $i->category_id,
                    'category'    => $i->category?->name,
                ]);

            return response()->json([
                'categories' => $categories,
                'items'      => $items,
            ]);
        }

        return view('content-management.index', array_merge(
            $this->sharedViewData(),
            ['activeTab' => 'education']
        ));
    }

    // Marketing tab
    public function marketing()
    {
        return view('content-management.index', array_merge(
            $this->sharedViewData(),
            ['activeTab' => 'marketing']
        ));
    }

    // Patient profile shortcut — opens clinical tab pre-filtered
    public function patientView(int $patientId)
    {
        $patient = Patient::findOrFail($patientId);
        $results = $this->searchService->search(['patient_id' => $patientId]);

        return view('content-management.index', array_merge(
            $this->sharedViewData(),
            [
                'activeTab'        => 'clinical',
                'prefilterPatient' => $patient,
                'results'          => $results,
            ]
        ));
    }

    public function saveWatermarkSettings(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->only([
            'wm_clinic_name',
            'wm_doctor_name',
            'wm_patient_name',
            'wm_position',
            'wm_opacity',
        ]);

        if ($request->hasFile('watermark_logo')) {
            $file = $request->file('watermark_logo');
            $dir  = storage_path('app/public/settings');
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $file->move($dir, 'watermark_logo.png');
            $data['has_logo'] = true;
        }

        WatermarkSetting::save($data);

        return response()->json(['success' => true]);
    }
}
