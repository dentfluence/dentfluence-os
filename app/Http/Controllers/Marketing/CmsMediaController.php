<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\CmsMedia;
use App\Services\ContentManagement\CmsMediaUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CmsMediaController
 *
 * Handles:
 *  POST   /marketing/media/upload        — upload file, auto-watermark, return tag-prompt payload
 *  POST   /marketing/media/{id}/tag      — save consent + photo_type + treatment_type
 *  GET    /marketing/media/library       — Marketing Library tab (approved grid + pending section)
 */
class CmsMediaController extends Controller
{
    public function __construct(private CmsMediaUploadService $uploadService) {}

    // ── Upload ─────────────────────────────────────────────────────────────

    /**
     * Accept a file upload, store + watermark it, return the new media record
     * so the front-end can immediately open the tag-prompt modal.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file'           => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic,mp4,pdf', 'max:51200'],
            'patient_id'     => ['nullable', 'integer'],
            'consultation_id'=> ['nullable', 'integer'],
            'visit_id'       => ['nullable', 'integer'],
            'doctor_id'      => ['nullable', 'integer'],
            'treatment_name' => ['nullable', 'string', 'max:100'],
            'tooth_no'       => ['nullable', 'string', 'max:20'],
            'treatment_stage'=> ['nullable', 'string'],
        ]);

        $media = $this->uploadService->store($request->file('file'), [
            'patient_id'      => $request->integer('patient_id')      ?: null,
            'consultation_id' => $request->integer('consultation_id') ?: null,
            'visit_id'        => $request->integer('visit_id')        ?: null,
            'doctor_id'       => $request->integer('doctor_id')       ?: null,
            'doctor_name'     => optional($request->user())->name,
            'treatment_name'  => $request->input('treatment_name'),
            'tooth_no'        => $request->input('tooth_no'),
            'treatment_stage' => $request->input('treatment_stage'),
            'disk'            => 'local',
        ]);

        return response()->json([
            'success'  => true,
            'media_id' => $media->id,
            'preview'  => $media->display_url,
            // Front-end uses these to populate the tag-prompt modal dropdowns
            'options'  => [
                'consent_status'      => CmsMedia::$consentOptions,
                'photo_type'          => CmsMedia::$photoTypeOptions,
                'tag_treatment_type'  => CmsMedia::$treatmentTypeOptions,
            ],
        ], 201);
    }

    // ── Tag ────────────────────────────────────────────────────────────────

    /**
     * Save the three required tags.
     * Automatically resolves marketing_status based on consent + completeness.
     */
    public function tag(Request $request, CmsMedia $cmsMedia): JsonResponse
    {
        $request->validate([
            'consent_status'     => ['required', Rule::in(array_keys(CmsMedia::$consentOptions))],
            'photo_type'         => ['required', Rule::in(array_keys(CmsMedia::$photoTypeOptions))],
            'tag_treatment_type' => ['required', Rule::in(array_keys(CmsMedia::$treatmentTypeOptions))],
        ]);

        $media = $this->uploadService->applyTags($cmsMedia, $request->only([
            'consent_status', 'photo_type', 'tag_treatment_type',
        ]));

        return response()->json([
            'success'          => true,
            'marketing_status' => $media->marketing_status,
            'is_marketing_ready' => $media->isMarketingReady(),
        ]);
    }

    // ── Marketing Library ──────────────────────────────────────────────────

    /**
     * Marketing Library page.
     *
     * approved  = consent=given + fully tagged + marketing_status=approved
     * pending   = missing consent OR missing tags
     * rejected  = consent=not_given (shown separately so staff can follow up)
     */
    public function library(Request $request)
    {
        $filterTreatment = $request->input('treatment_type');
        $filterPhoto     = $request->input('photo_type');

        // Approved grid
        $approvedQuery = CmsMedia::marketingReady()->with('patient')->latest();
        if ($filterTreatment) {
            $approvedQuery->byTreatmentType($filterTreatment);
        }
        if ($filterPhoto) {
            $approvedQuery->byPhotoType($filterPhoto);
        }
        $approved = $approvedQuery->paginate(24, ['*'], 'approved_page');

        // Pending — needs tagging or consent decision
        $pending = CmsMedia::pendingTags()
            ->with('patient')
            ->latest()
            ->paginate(12, ['*'], 'pending_page');

        // Rejected — consent not given (for awareness only)
        $rejected = CmsMedia::where('marketing_status', 'rejected')
            ->with('patient')
            ->latest()
            ->paginate(12, ['*'], 'rejected_page');

        return view('marketing.library', [
            'approved'        => $approved,
            'pending'         => $pending,
            'rejected'        => $rejected,
            'filterTreatment' => $filterTreatment,
            'filterPhoto'     => $filterPhoto,
            'treatmentOptions'=> CmsMedia::$treatmentTypeOptions,
            'photoOptions'    => CmsMedia::$photoTypeOptions,
            'activeTab'       => 'library',
        ]);
    }
}
