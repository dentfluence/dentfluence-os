<?php

namespace App\Http\Controllers\ContentManagement;

use App\Http\Controllers\Controller;
use App\Models\ClinicalFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CmsSearchController — global Clinical Library search.
 *
 * Phase 9: All queries now target clinical_files (not cms_media / clinical_media).
 * The old CmsSearchService and TimelineService are no longer used here.
 */
class CmsSearchController extends Controller
{
    /**
     * AJAX: full-text search across clinical_files.
     * Used by the global search drawer in the Content Manager.
     *
     * Returns JSON for AJAX calls, or redirects for plain GET.
     */
    public function search(Request $request): mixed
    {
        $q         = $request->get('q', '');
        $perPage   = $request->integer('per_page', 24);
        $activeTab = $request->get('tab', 'marketing');

        // Base eligibility scope per active tab
        $query = match ($activeTab) {
            'education'    => ClinicalFile::educationEligible(),
            'case-library' => ClinicalFile::caseLibraryEligible(),
            'teaching'     => ClinicalFile::teachingEligible(),
            'research'     => ClinicalFile::researchEligible(),
            default        => ClinicalFile::marketingEligible(),
        };

        // Keyword search: procedure name, title, notes, tags
        if (strlen($q) >= 2) {
            $query->where(function ($sub) use ($q) {
                $sub->where('procedure', 'like', "%{$q}%")
                    ->orWhere('title',     'like', "%{$q}%")
                    ->orWhere('notes',     'like', "%{$q}%")
                    ->orWhereJsonContains('tags', $q);
            });
        }

        // Optional filters
        if ($treatment = $request->get('treatment')) {
            $query->where('procedure', $treatment);
        }
        if ($stage = $request->get('stage')) {
            $query->where('stage', $stage);
        }
        if ($fileType = $request->get('file_type')) {
            $query->where('file_type', $fileType);
        }

        $results = $query->latest('captured_at')->paginate($perPage)->withQueryString();

        if ($request->ajax()) {
            return response()->json([
                'total'   => $results->total(),
                'results' => $results->map(fn($f) => [
                    'id'            => $f->id,
                    'title'         => $f->title ?? $f->original_filename,
                    'procedure'     => $f->procedure,
                    'stage'         => $f->stage_label,
                    'file_type'     => $f->file_type_label,
                    'thumbnail_url' => $f->thumbnail_url,
                    'display_url'   => $f->display_url,
                    'captured_at'   => $f->captured_at?->format('d M Y'),
                    'tags'          => $f->tags ?? [],
                ]),
                'next_page_url' => $results->nextPageUrl(),
            ]);
        }

        return redirect()->route('cms.index');
    }

    /**
     * AJAX: case viewer data for a grouped case (case-library tab).
     * Delegates to ClinicalLibraryController::caseViewer() which handles anonymisation.
     */
    public function caseViewer(Request $request): JsonResponse
    {
        return app(ClinicalLibraryController::class)->caseViewer($request);
    }

    /**
     * Mark a clinical file as marketing eligible.
     * Phase 9 replacement for old cms_media tagging.
     */
    public function tagMarketing(Request $request): JsonResponse
    {
        $request->validate(['file_id' => 'required|exists:clinical_files,id']);

        ClinicalFile::findOrFail($request->file_id)->update([
            'is_marketing_eligible' => true,
            'marketing_status'      => 'pending',
        ]);

        return response()->json(['success' => true, 'message' => 'Marked as marketing eligible. Pending approval.']);
    }

    /**
     * Remove marketing eligibility from a clinical file.
     */
    public function removeMarketingTag(int $id): JsonResponse
    {
        ClinicalFile::findOrFail($id)->update([
            'is_marketing_eligible' => false,
            'marketing_status'      => null,
        ]);

        return response()->json(['success' => true]);
    }
}
