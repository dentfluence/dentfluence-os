<?php

namespace App\Http\Controllers\ContentManagement;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\CmsMedia;
use App\Services\ContentManagement\CmsSearchService;
use Illuminate\Http\Request;
use App\Models\WatermarkSetting;

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

        $treatments = CmsMedia::distinct()
            ->orderBy('treatment_name')
            ->pluck('treatment_name')
            ->filter()
            ->values();

        $patients = Patient::orderBy('name')->get(['id', 'name']);

        $stats = $this->searchService->getStats();

        $treatmentOptions = $treatments;

        return compact('toothOptions', 'treatments', 'treatmentOptions', 'patients', 'stats');
    }

    // Main entry — clinical tab by default
    public function index(Request $request)
    {
        $activeTab = $request->get('tab', 'clinical');

        return view('content-management.index', array_merge(
            $this->sharedViewData(),
            ['activeTab' => $activeTab]
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
