<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\ClinicalMedia;
use App\Models\EducationCategory;
use App\Models\Patient;
use App\Models\User;
use App\Services\Cms\CmsSearchService;
use App\Services\Cms\TimelineService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ClinicalLibraryController extends Controller
{
    public function __construct(
        private CmsSearchService $searchService,
        private TimelineService  $timelineService,
    ) {}

    /**
     * Main CMS page — Patient Clinical Data tab.
     */
    public function index(Request $request)
    {
        $filters  = $request->only([
            'search', 'patient_id', 'tooth_no', 'treatment',
            'doctor_id', 'date_from', 'date_to', 'tags', 'sort',
        ]);

        $results = $this->searchService->searchPatientClinical($filters);

        return view('cms.index', [
            'results'       => $results,
            'filters'       => $filters,
            'patients'      => Patient::orderBy('name')->get(['id', 'name']),
            'doctors'       => User::orderBy('name')->get(['id', 'name']),
            'treatments'    => $this->searchService->getTreatmentOptions(),
            'toothOptions'  => $this->searchService->getToothOptions(),
            'activeTab'     => 'clinical',
        ]);
    }

    /**
     * Generic Educational Library tab.
     */
    public function education(Request $request)
    {
        $categorySlug = $request->get('category', 'all');

        $categories = EducationCategory::where('is_active', true)
            ->withCount(['treatments'])
            ->orderBy('sort_order')
            ->get();

        $mediaQuery = ClinicalMedia::with('doctor')
            ->genericEducational()
            ->where('is_active', true);

        if ($categorySlug !== 'all') {
            $mediaQuery->where('category', $categorySlug);
        }

        // Group generic media by treatment_name for card display
        $educationCards = $mediaQuery
            ->select('treatment_name', 'category')
            ->selectRaw('COUNT(*) as media_count')
            ->selectRaw('SUM(media_type = "photo") as photo_count')
            ->selectRaw('SUM(media_type IN ("xray","opg","cbct")) as xray_count')
            ->selectRaw('SUM(media_type = "video") as video_count')
            ->selectRaw('MAX(id) as latest_id')
            ->groupBy('treatment_name', 'category')
            ->orderBy('treatment_name')
            ->paginate(12);

        return view('cms.education', [
            'categories'     => $categories,
            'educationCards' => $educationCards,
            'activeCategory' => $categorySlug,
            'activeTab'      => 'education',
        ]);
    }

    /**
     * AJAX: Load case viewer data (sidebar panel).
     */
    public function caseViewer(Request $request): JsonResponse
    {
        $patientId    = $request->integer('patient_id');
        $treatment    = $request->string('treatment')->toString();
        $toothNo      = $request->string('tooth_no')->toString() ?: null;

        $patient = Patient::findOrFail($patientId);

        // Media grouped by stage
        $caseMedia = $this->searchService->getCaseMedia($patientId, $treatment, $toothNo);
        $stats     = $this->searchService->getCaseStats($patientId, $treatment, $toothNo);
        $timeline  = $this->timelineService->buildCaseTimeline($patientId, $treatment, $toothNo);
        $visits    = $this->timelineService->buildVisitHistory($patientId, $treatment);

        // Dates for case details panel
        $allMedia  = ClinicalMedia::patientClinical()
            ->where('patient_id', $patientId)
            ->where('treatment_name', $treatment)
            ->when($toothNo, fn($q) => $q->where('tooth_no', $toothNo))
            ->orderBy('media_date')
            ->get(['media_date', 'doctor_id', 'tags']);

        $startDate   = optional($allMedia->first())?->media_date?->format('d M Y');
        $lastDate    = optional($allMedia->last())?->media_date?->format('d M Y');
        $tags        = $allMedia->pluck('tags')->flatten()->unique()->values();
        $doctorId    = $allMedia->first()?->doctor_id;
        $doctor      = $doctorId ? User::find($doctorId)?->name : null;

        return response()->json([
            'patient'    => [
                'id'     => $patient->id,
                'name'   => $patient->name,
                'age'    => $patient->age,
                'gender' => ucfirst($patient->gender ?? ''),
                'url'    => route('patients.show', $patient),
            ],
            'treatment'  => $treatment,
            'tooth_no'   => $toothNo,
            'start_date' => $startDate,
            'last_date'  => $lastDate,
            'doctor'     => $doctor,
            'tags'       => $tags,
            'stats'      => $stats,
            'media'      => $caseMedia,
            'timeline'   => $timeline,
            'visits'     => $visits,
        ]);
    }

    /**
     * AJAX: Search suggestions for global search bar.
     */
    public function searchSuggest(Request $request): JsonResponse
    {
        $q = $request->string('q')->toString();
        if (strlen($q) < 2) return response()->json([]);

        $patients = Patient::where('name', 'like', "%{$q}%")->limit(4)->get(['id', 'name']);
        $treatments = $this->searchService->getTreatmentOptions()
            ->filter(fn($t) => str_contains(strtolower($t), strtolower($q)))
            ->take(4)->values();

        return response()->json([
            'patients'   => $patients,
            'treatments' => $treatments,
        ]);
    }
}
