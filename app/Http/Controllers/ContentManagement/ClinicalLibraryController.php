<?php

namespace App\Http\Controllers\ContentManagement;

use App\Http\Controllers\Controller;
use App\Models\EducationCategory;
use App\Models\Patient;
use App\Services\ContentManagement\CmsSearchService;
use App\Services\ContentManagement\TimelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClinicalLibraryController extends Controller
{
    public function __construct(
        private CmsSearchService $search,
        private TimelineService  $timeline,
    ) {}

    // ── Patient Clinical Data tab ──────────────────────────────────────────────

    public function index(Request $request)
    {
        $filters = $request->only([
            'q',
            'patient_id',
            'tooth',
            'treatment',
            'doctor_id',
            'date_from',
            'date_to',
            'tag',
            'sort',
            'date_range',
        ]);

        // Map human date_range shortcuts to actual dates
        $filters = $this->resolveDateRange($filters);

        $cases    = $this->search->searchCases($filters, $request->integer('per_page', 10));
        $stats    = $this->search->getStats();
        $patients = Patient::orderBy('name')->get(['id', 'name']);
        $doctors  = \DB::table('users')->orderBy('name')->get(['id', 'name']);

        return view('content-management.index', compact(
            'cases',
            'filters',
            'stats',
            'patients',
            'doctors'
        ) + [
            'toothOptions'     => $this->search->getToothOptions(),
            'treatmentOptions' => $this->search->getTreatmentOptions(),
            'tagOptions'       => $this->search->getTagOptions(),
        ]);
    }

    // ── Generic Education Library tab ─────────────────────────────────────────

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

    // ── AJAX: Case Viewer panel ───────────────────────────────────────────────

    public function caseViewer(Request $request): JsonResponse
    {
        $patientId = $request->integer('patient_id');
        $treatment = $request->get('treatment');
        $tooth     = $request->get('tooth');

        if (!$patientId) {
            return response()->json(['error' => 'patient_id required'], 422);
        }

        $media    = $this->search->getCaseMedia($patientId, $treatment, $tooth);
        $timeline = $this->timeline->build($media);
        $stages   = $this->timeline->groupByStage($media);

        $patient = Patient::select('id', 'name', 'age', 'gender')->find($patientId);

        // Visit history (just dates + media counts per visit)
        $visitHistory = $media
            ->groupBy('visit_id')
            ->filter(fn($g, $k) => $k !== null)
            ->map(fn($g) => [
                'visit_id'   => $g->first()->visit_id,
                'date'       => $g->min('upload_date'),
                'media_count' => $g->count(),
                'stage'      => $g->first()->treatment_stage,
            ])
            ->values();

        return response()->json([
            'patient'      => $patient,
            'treatment'    => $treatment,
            'tooth'        => $tooth,
            'media_count'  => $media->count(),
            'start_date'   => $media->min('upload_date'),
            'completion_date' => $media->where('treatment_stage', 'after')->max('upload_date'),
            'last_followup' => $media->where('treatment_stage', 'followup')->max('upload_date'),
            'timeline'     => $timeline,
            'stages'       => [
                'before'   => $this->serializeMedia($stages['before']),
                'during'   => $this->serializeMedia($stages['during']),
                'after'    => $this->serializeMedia($stages['after']),
                'followup' => $this->serializeMedia($stages['followup']),
            ],
            'counts' => [
                'photos'   => $media->whereIn('media_type', ['photo'])->count(),
                'xrays'    => $media->whereIn('media_type', ['xray', 'opg', 'cbct'])->count(),
                'scans'    => $media->where('media_type', 'scan')->count(),
                'videos'   => $media->where('media_type', 'video')->count(),
            ],
            'tags'         => $media->pluck('searchable_tags')->flatten()->unique()->values(),
            'visit_history' => $visitHistory,
        ]);
    }

    // ── AJAX: Search suggestions ──────────────────────────────────────────────

    public function searchSuggest(Request $request): JsonResponse
    {
        $q = $request->get('q', '');
        if (strlen($q) < 2) return response()->json([]);

        $patients = Patient::where('name', 'like', "%{$q}%")
            ->limit(5)
            ->get(['id', 'name'])
            ->map(fn($p) => ['type' => 'patient', 'label' => $p->name, 'value' => $p->id]);

        $treatments = \DB::table('clinical_media')
            ->where('treatment_name', 'like', "%{$q}%")
            ->distinct()
            ->limit(5)
            ->pluck('treatment_name')
            ->map(fn($t) => ['type' => 'treatment', 'label' => $t, 'value' => $t]);

        return response()->json([...$patients, ...$treatments]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

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

    private function serializeMedia(Collection $media): array
    {
        return $media->map(fn($m) => [
            'id'           => $m->id,
            'media_type'   => $m->media_type,
            'display_url'  => $m->display_url,
            'thumbnail_url' => $m->thumbnail_url,
            'stage'        => $m->treatment_stage,
            'stage_label'  => $m->stage_label,
            'upload_date'  => $m->upload_date?->format('d M Y'),
            'notes'        => $m->notes,
        ])->toArray();
    }

    public function educationManage(Request $request)
    {
        return app(EducationContentController::class)->manage($request);
    }
}
