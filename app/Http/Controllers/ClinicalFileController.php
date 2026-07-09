<?php

namespace App\Http\Controllers;

use App\Models\ClinicalFile;
use App\Models\Patient;
use App\Services\ClinicalLibrary\ClinicalFileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Phase 7C — ClinicalFileController
 *
 * All routes are patient-scoped: /patients/{patient}/clinical-files
 * All responses are JSON — the UI is driven by Alpine.js + Blade (no full-page reload per action).
 *
 * The patient's Documents tab view is loaded by PatientController via PatientProfileService;
 * this controller handles the AJAX actions (upload, update, delete, fetch).
 */
class ClinicalFileController extends Controller
{
    // ── index ──────────────────────────────────────────────────────────────────

    /**
     * GET /patients/{patient}/clinical-files
     *
     * Returns all clinical files for a patient as JSON.
     * Supports optional filters via query params:
     *   ?file_type=photo&stage=before&visit_id=5&from=2025-01-01&to=2025-12-31
     *
     * Used for AJAX filter refreshes (Phase 9+).
     */
    public function index(Request $request, Patient $patient): JsonResponse
    {
        $query = ClinicalFile::with(['visit.doctor', 'uploadedBy'])
            ->forPatient($patient->id)
            ->latest('captured_at');

        // Optional filters
        if ($request->filled('file_type')) {
            $query->where('file_type', $request->file_type);
        }
        if ($request->filled('stage')) {
            $query->where('stage', $request->stage);
        }
        if ($request->filled('visit_id')) {
            $query->where('visit_id', $request->visit_id);
        }
        if ($request->filled('from')) {
            $query->where('captured_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('captured_at', '<=', $request->to . ' 23:59:59');
        }

        $files = $query->get()->map(function (ClinicalFile $file) {
            return $this->formatFile($file);
        });

        return response()->json([
            'success' => true,
            'total'   => $files->count(),
            'files'   => $files,
        ]);
    }

    // ── store ──────────────────────────────────────────────────────────────────

    /**
     * POST /patients/{patient}/clinical-files
     *
     * Upload a new clinical file. Accepts multipart/form-data.
     * Returns the created record as JSON.
     */
    public function store(Request $request, Patient $patient): JsonResponse
    {
        $request->validate([
            'file'                    => 'required|file|max:51200', // 50 MB max
            'visit_id'                => ['nullable', 'integer', 'exists:treatment_visits,id'],
            'treatment_plan_item_id'  => ['nullable', 'integer', 'exists:treatment_plan_items,id'],
            'procedure'               => ['nullable', 'string', 'max:255'],
            'treatment_category'      => ['nullable', Rule::in(array_keys(ClinicalFile::TREATMENT_CATEGORIES))],
            'tooth_number'            => ['nullable', 'string', 'max:50'],
            'stage'                   => ['nullable', Rule::in(ClinicalFile::STAGES)],
            'file_type'               => ['nullable', Rule::in(ClinicalFile::FILE_TYPES)],
            'title'                   => ['nullable', 'string', 'max:255'],
            'notes'                   => ['nullable', 'string', 'max:5000'],
            'captured_at'             => ['nullable', 'date'],
            'tags'                    => ['nullable', 'array'],
            'tags.*'                  => ['string', 'max:50'],
            'is_marketing_eligible'   => ['nullable', 'boolean'],
            'is_education_eligible'   => ['nullable', 'boolean'],
            'is_teaching_eligible'    => ['nullable', 'boolean'],
            'is_research_eligible'    => ['nullable', 'boolean'],
            'is_case_library_eligible'=> ['nullable', 'boolean'],
            'consent_status'          => ['nullable', Rule::in(['not_given', 'pending', 'given'])],
            'protocol_step_id'        => ['nullable', 'integer', 'exists:documentation_protocol_steps,id'],
        ]);

        $uploadedFile = $request->file('file');

        // Storage, record creation, and watermark dispatch all live in
        // ClinicalFileUploadService — shared with ConsultationController and any
        // future upload path, so there is exactly one place that decides how a
        // clinical file gets saved.
        $record = app(ClinicalFileUploadService::class)->store($uploadedFile, [
            'patient_id'               => $patient->id,
            'visit_id'                 => $request->visit_id,
            'treatment_plan_item_id'   => $request->treatment_plan_item_id,
            'procedure'                => $request->procedure,
            'treatment_category'       => $request->treatment_category, // null => auto-detected from procedure
            'tooth_number'             => $request->tooth_number,
            'stage'                    => $request->input('stage', 'general'),
            'file_type'                => $request->input('file_type'), // null => auto-detect from MIME
            'title'                    => $request->title,
            'notes'                    => $request->notes,
            'captured_at'              => $request->captured_at ?? now(),
            'uploaded_by'              => Auth::id(),
            'tags'                     => $request->tags ?? [],
            'is_marketing_eligible'    => $request->boolean('is_marketing_eligible'),
            'is_education_eligible'    => $request->boolean('is_education_eligible'),
            'is_teaching_eligible'     => $request->boolean('is_teaching_eligible'),
            'is_research_eligible'     => $request->boolean('is_research_eligible'),
            'is_case_library_eligible' => $request->boolean('is_case_library_eligible'),
            'consent_status'           => $request->input('consent_status', 'not_given'),
            'protocol_step_id'         => $request->protocol_step_id,
        ]);

        $record->load(['visit.doctor', 'uploadedBy']);

        return response()->json([
            'success' => true,
            'file'    => $this->formatFile($record),
        ], 201);
    }

    // ── show ───────────────────────────────────────────────────────────────────

    /**
     * GET /patients/{patient}/clinical-files/{file}
     *
     * Returns full metadata for a single file.
     * Used by the File Viewer panel (Phase 5) to populate the right pane.
     */
    public function show(Patient $patient, ClinicalFile $file): JsonResponse
    {
        abort_unless($file->patient_id === $patient->id, 403);

        $file->load(['visit.doctor', 'uploadedBy', 'protocolStep.protocol']);

        return response()->json([
            'success' => true,
            'file'    => $this->formatFile($file, detailed: true),
        ]);
    }

    // ── update ─────────────────────────────────────────────────────────────────

    /**
     * PUT /patients/{patient}/clinical-files/{file}
     *
     * Update file metadata (not the file itself — originals are never replaced).
     * Used by the File Viewer's inline edit fields.
     */
    public function update(Request $request, Patient $patient, ClinicalFile $file): JsonResponse
    {
        abort_unless($file->patient_id === $patient->id, 403);

        $request->validate([
            'visit_id'                 => ['nullable', 'integer', 'exists:treatment_visits,id'],
            'procedure'                => ['nullable', 'string', 'max:255'],
            'treatment_category'       => ['nullable', Rule::in(array_keys(ClinicalFile::TREATMENT_CATEGORIES))],
            'tooth_number'             => ['nullable', 'string', 'max:50'],
            'stage'                    => ['nullable', Rule::in(ClinicalFile::STAGES)],
            'file_type'                => ['nullable', Rule::in(ClinicalFile::FILE_TYPES)],
            'title'                    => ['nullable', 'string', 'max:255'],
            'notes'                    => ['nullable', 'string', 'max:5000'],
            'captured_at'              => ['nullable', 'date'],
            'tags'                     => ['nullable', 'array'],
            'tags.*'                   => ['string', 'max:50'],
            'is_marketing_eligible'    => ['nullable', 'boolean'],
            'is_education_eligible'    => ['nullable', 'boolean'],
            'is_teaching_eligible'     => ['nullable', 'boolean'],
            'is_research_eligible'     => ['nullable', 'boolean'],
            'is_case_library_eligible' => ['nullable', 'boolean'],
            'consent_status'           => ['nullable', Rule::in(['not_given', 'pending', 'given'])],
            'marketing_status'         => ['nullable', Rule::in(['pending', 'approved', 'rejected'])],
            'content_rating'           => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        $file->update($request->only([
            'visit_id', 'procedure', 'treatment_category', 'tooth_number', 'stage', 'file_type',
            'title', 'notes', 'captured_at', 'tags',
            'is_marketing_eligible', 'is_education_eligible',
            'is_teaching_eligible', 'is_research_eligible', 'is_case_library_eligible',
            'consent_status', 'marketing_status', 'content_rating',
        ]));

        $file->load(['visit.doctor', 'uploadedBy']);

        return response()->json([
            'success' => true,
            'file'    => $this->formatFile($file),
        ]);
    }

    // ── destroy ────────────────────────────────────────────────────────────────

    /**
     * DELETE /patients/{patient}/clinical-files/{file}
     *
     * Soft-deletes the record. Does NOT delete the physical file from storage
     * (that requires a separate purge step — originals are precious).
     */
    public function destroy(Patient $patient, ClinicalFile $file): JsonResponse
    {
        abort_unless($file->patient_id === $patient->id, 403);

        $file->delete(); // soft delete only

        return response()->json(['success' => true]);
    }

    // ── Private helpers ────────────────────────────────────────────────────────
    // (file-type auto-detection now lives in ClinicalFileUploadService, shared
    // with every other upload path)

    /**
     * Serialize a ClinicalFile to array for JSON responses.
     * $detailed = true includes full visit + uploader + protocol step info.
     */
    private function formatFile(ClinicalFile $file, bool $detailed = false): array
    {
        $data = [
            'id'               => $file->id,
            'patient_id'       => $file->patient_id,
            'visit_id'         => $file->visit_id,
            'procedure'                => $file->procedure,
            'treatment_category'       => $file->treatment_category,
            'treatment_category_label' => $file->treatment_category_label,
            'tooth_number'     => $file->tooth_number,
            'stage'            => $file->stage,
            'stage_label'      => $file->stage_label,
            'file_type'        => $file->file_type,
            'file_type_label'  => $file->file_type_label,
            'title'            => $file->title ?? $file->original_filename,
            'notes'            => $file->notes,
            'tags'             => $file->tags ?? [],
            'original_url'     => $file->original_url,
            'display_url'      => $file->display_url,
            'thumbnail_url'    => $file->thumbnail_url,
            'is_image'         => $file->isImage(),
            'original_filename'=> $file->original_filename,
            'mime_type'        => $file->mime_type,
            'file_size_human'  => $file->file_size_human,
            'captured_at'      => $file->captured_at?->format('d M Y'),
            'uploaded_at'      => $file->created_at->format('d M Y H:i'),
            // Eligibility
            'is_marketing_eligible'    => $file->is_marketing_eligible,
            'is_education_eligible'    => $file->is_education_eligible,
            'is_teaching_eligible'     => $file->is_teaching_eligible,
            'is_research_eligible'     => $file->is_research_eligible,
            'is_case_library_eligible' => $file->is_case_library_eligible,
            // Consent
            'consent_status'    => $file->consent_status,
            'marketing_status'  => $file->marketing_status,
            'content_rating'    => $file->content_rating,
        ];

        if ($detailed) {
            $data['visit']         = $file->visit ? [
                'id'           => $file->visit->id,
                'visit_date'   => $file->visit->visit_date?->format('d M Y'),
                'treatment'    => $file->visit->treatment_name,
                'doctor_name'  => $file->visit->doctor?->name,
            ] : null;
            $data['uploaded_by']   = $file->uploadedBy ? [
                'id'   => $file->uploadedBy->id,
                'name' => $file->uploadedBy->name,
            ] : null;
        }

        return $data;
    }

    // ── protocolSteps (Phase 11) ─────────────────────────────────────────────

    /**
     * GET /clinical-library/protocol-steps?procedure=Root+Canal
     *
     * Returns steps for a procedure as JSON.
     * Called by the upload modal AJAX on procedure select change.
     */
    public function protocolSteps(Request $request): JsonResponse
    {
        $procedure = trim((string) $request->query('procedure', ''));

        if (blank($procedure)) {
            return response()->json(['steps' => [], 'protocol' => null]);
        }

        $service  = app(\App\Services\ClinicalLibrary\ProtocolService::class);
        $protocol = $service->getProtocolForProcedure($procedure);

        if (!$protocol) {
            return response()->json(['steps' => [], 'protocol' => null]);
        }

        return response()->json([
            'protocol' => [
                'id'             => $protocol->id,
                'name'           => $protocol->name,
                'procedure_type' => $protocol->procedure_type,
            ],
            'steps' => $service->getStepsForProcedure($procedure),
        ]);
    }
}
