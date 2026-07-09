<?php

namespace App\Services\ClinicalLibrary;

use App\Jobs\GenerateWatermark;
use App\Models\ClinicalFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

/**
 * ClinicalFileUploadService
 *
 * The single place that turns an uploaded file into a ClinicalFile record.
 * Used by ClinicalFileController (patient Documents tab upload) and
 * ConsultationController (photos/x-rays/scans captured during a consultation),
 * so every upload path in the app writes to the same table the Clinical Library
 * dashboard actually reads from. Before this existed, consultation uploads wrote
 * to the legacy clinical_media table instead and never appeared in the library.
 *
 * Storage: always the private 'local' disk — clinical photos must never sit on a
 * publicly reachable disk. Access is only through the authenticated
 * SecureMediaController route. The disk name here is a config-level indirection:
 * if storage ever needs to move to cloud object storage, only
 * config/filesystems.php's 'local' entry changes (swap driver to 's3' or similar),
 * this class and every caller stay untouched.
 *
 * Usage:
 *   app(ClinicalFileUploadService::class)->store($uploadedFile, [
 *       'patient_id'   => $patient->id,
 *       'procedure'    => 'Root Canal',
 *       'tooth_number' => '26',
 *       'stage'        => 'before',
 *       'tags'         => ['photo', 'before'],
 *       'source_type'  => \App\Models\Consultation::class,
 *       'source_id'    => $consultation->id,
 *   ]);
 */
class ClinicalFileUploadService
{
    /** Private disk — never 'public'. Patient clinical media must stay behind auth. */
    private const DISK = 'local';

    /**
     * Store an uploaded file and create its ClinicalFile record.
     * Dispatches watermark generation automatically for image types.
     */
    public function store(UploadedFile $file, array $context): ClinicalFile
    {
        if (empty($context['patient_id'])) {
            throw new \InvalidArgumentException('ClinicalFileUploadService::store() requires patient_id in context.');
        }

        $fileType = $context['file_type'] ?? $this->detectFileType($file);

        $relativePath = $file->store(
            "patients/{$context['patient_id']}/clinical-files",
            self::DISK
        );

        $record = ClinicalFile::create([
            'patient_id'               => $context['patient_id'],
            'visit_id'                 => $context['visit_id'] ?? null,
            'treatment_plan_item_id'   => $context['treatment_plan_item_id'] ?? null,
            'procedure'                => $context['procedure'] ?? null,
            'treatment_category'       => $context['treatment_category']
                ?? TreatmentCategoryDetector::detect($context['procedure'] ?? null),
            'tooth_number'             => $context['tooth_number'] ?? null,
            'stage'                    => $context['stage'] ?? 'general',
            'file_type'                => $fileType,
            'title'                    => $context['title'] ?? null,
            'notes'                    => $context['notes'] ?? null,
            'disk'                     => self::DISK,
            'path'                     => $relativePath,
            'original_filename'        => $file->getClientOriginalName(),
            'mime_type'                => $file->getMimeType(),
            'file_size'                => $file->getSize(),
            'captured_at'              => $context['captured_at'] ?? now(),
            'uploaded_by'              => $context['uploaded_by'] ?? Auth::id(),
            'source_type'              => $context['source_type'] ?? null,
            'source_id'                => $context['source_id'] ?? null,
            'tags'                     => $context['tags'] ?? [],
            'is_marketing_eligible'    => $context['is_marketing_eligible'] ?? false,
            'is_education_eligible'    => $context['is_education_eligible'] ?? false,
            'is_teaching_eligible'     => $context['is_teaching_eligible'] ?? false,
            'is_research_eligible'     => $context['is_research_eligible'] ?? false,
            'is_case_library_eligible' => $context['is_case_library_eligible'] ?? false,
            'consent_status'           => $context['consent_status'] ?? 'not_given',
            'protocol_step_id'         => $context['protocol_step_id'] ?? null,
            'sync_status'              => $context['sync_status'] ?? 'local_only',
        ]);

        // Only image types are watermarkable; non-images are silently skipped by the job.
        // Original path is NEVER modified by the job.
        if ($record->isImage()) {
            GenerateWatermark::dispatch($record);
        }

        // If this file completes a before/after pair for the same case, both
        // files enter the marketing review queue automatically — see
        // MarketingEligibilityDetector for exactly what it does and doesn't do.
        MarketingEligibilityDetector::checkAndFlag($record);

        return $record;
    }

    /**
     * Auto-detect file_type from MIME type when the caller doesn't specify one.
     */
    private function detectFileType(UploadedFile $file): string
    {
        $mime = $file->getMimeType();

        if (str_starts_with($mime, 'image/')) return 'photo';
        if (str_starts_with($mime, 'video/')) return 'video';
        if ($mime === 'application/pdf') return 'pdf';
        if ($mime === 'model/stl' || str_ends_with($file->getClientOriginalName(), '.stl')) return 'stl';

        return 'other';
    }
}
