<?php

namespace App\Http\Controllers\ContentManagement;

use App\Http\Controllers\Controller;
use App\Models\CmsMedia;
use App\Services\ContentManagement\CmsSearchService;
use App\Services\ContentManagement\TimelineService;
use Illuminate\Http\Request;

class CmsSearchController extends Controller
{
    public function __construct(
        private CmsSearchService $searchService,
        private TimelineService  $timelineService,
    ) {}

    // AJAX: search + filter results
    public function search(Request $request)
    {
        $results = $this->searchService->search($request->all());

        if ($request->ajax()) {
            return response()->json([
                'html'  => view('content-management.partials.clinical.results-table', compact('results'))->render(),
                'total' => $results->total(),
            ]);
        }

        return redirect()->route('cms.index');
    }

    // AJAX: open case viewer for a specific cms_media group
    public function caseViewer(int $id)
    {
        $media    = CmsMedia::with('patient')->findOrFail($id);
        $timeline = $this->timelineService->buildForCase(
            $media->patient_id,
            $media->treatment_name,
            $media->tooth_no
        );

        return response()->json([
            'html' => view('content-management.partials.clinical.case-viewer', compact('media', 'timeline'))->render(),
        ]);
    }

    // Tag media as marketing content
    public function tagMarketing(Request $request)
    {
        $request->validate(['media_id' => 'required|exists:cms_media,id']);

        CmsMedia::findOrFail($request->media_id)->update([
            'is_marketing' => true,
        ]);

        return response()->json(['success' => true, 'message' => 'Tagged as marketing content.']);
    }

    // Remove marketing tag
    public function removeMarketingTag(int $id)
    {
        CmsMedia::findOrFail($id)->update(['is_marketing' => false]);

        return response()->json(['success' => true]);
    }
}
