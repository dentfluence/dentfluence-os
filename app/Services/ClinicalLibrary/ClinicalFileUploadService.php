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
     * The ONLY formats the Clinical Library accepts — CEO ruling, 12 Sep 2026.
     *
     * Why an allowlist and not a blocklist: a camera RAW (.CR2) uploaded on
     * 12 Sep was accepted, stored, and then could not be shown — its MIME type
     * starts with image/ so the app called it a photo, but no browser can decode
     * a RAW and neither can GD, so the thumbnail was permanently broken and the
     * file could never be watermarked. Anything outside this list has the same
     * problem waiting in it, so nothing gets in unless it is listed here.
     *
     * Before this existed, FOUR upload endpoints carried THREE different rules
     * (web: none at all; mobile documents: jpg,jpeg,png,pdf,dcm,doc,docx;
     * mobile capture: jpg,jpeg,png,heic,heif). This constant is now the one
     * list; every endpoint reads it through validationRule().
     *
     * Deliberately NOT here yet, each needing its own decision:
     *   stl  — lab cases carry them, so P1 (lab → clinical_files) needs it added
     *   heic/heif — iPhone's native camera format; the mobile capture endpoint
     *               used to accept it and no longer does
     *   dcm  — DICOM / CBCT
     * Adding any of them is one line in this array plus a matching accept="".
     */
    /**
     * The ONLY formats the Clinical Library accepts, each with its OWN size cap
     * in KB — CEO rulings 12 Sep 2026.
     *
     * Why per-format and not one blanket cap: a single 50 MB limit let someone
     * upload a 50 MB JPEG, which no clinical photo needs, while still being too
     * small for the CBCT and STL files a lab case genuinely carries. One number
     * cannot be right for both, and storage is a real cost line the moment this
     * is multi-tenant — at ~20 photos a case this clinic alone generates several
     * GB a month before a single scan is counted.
     *
     * Why an allowlist at all: a Canon RAW (.CR2) uploaded cleanly on 12 Sep and
     * was then unviewable forever — RAW's MIME starts with image/ so the app
     * called it a photo, but no browser can decode RAW and neither can GD, so the
     * thumbnail was permanently broken and it could never be watermarked.
     * Anything outside this list has the same problem waiting in it.
     *
     * DICOM is accepted but is a poor use of the vault: the app cannot display it,
     * so it sits there as cost. The JPG/PDF report exported from the CBCT software
     * is what belongs here — the 100 MB cap is set to make that the easy path.
     */
    public const MAX_KB_BY_EXTENSION = [
        // images — nothing clinical needs more than this
        'jpg'  => 15360,  'jpeg' => 15360,  'png'  => 15360,  'avif' => 15360,   // 15 MB
        // documents
        'pdf'  => 25600,  'doc'  => 25600,  'docx' => 25600,
        'xls'  => 25600,  'xlsx' => 25600,                                        // 25 MB
        // dental 3D / imaging — added for P1 (lab cases carry STL)
        'stl'  => 61440,                                                          // 60 MB
        'dcm'  => 102400,                                                         // 100 MB
    ];

    /**
     * Accepted extensions, derived from the cap map so the two can never drift
     * apart. Still excluded, each needing its own decision: heic/heif (iPhone's
     * native camera format — the mobile capture endpoint accepted it before
     * 12 Sep and no longer does).
     */
    public static function allowedExtensions(): array
    {
        return array_keys(self::MAX_KB_BY_EXTENSION);
    }

    /**
     * The cap that applies to one extension. $ceilingKb lets an endpoint be
     * STRICTER than the format allows (the mobile documents endpoint caps
     * everything at 20 MB) but never looser.
     */
    public static function maxKbFor(string $extension, ?int $ceilingKb = null): int
    {
        $max = self::MAX_KB_BY_EXTENSION[strtolower($extension)] ?? 15360;

        return $ceilingKb ? min($max, $ceilingKb) : $max;
    }

    /**
     * The validation rule every upload endpoint must use, so accepted formats and
     * sizes can never drift apart between web, mobile and library.
     */
    public static function validationRule(?int $ceilingKb = null): array
    {
        return [
            'required',
            'file',
            'mimes:' . implode(',', self::allowedExtensions()),
            // Outer guard so an enormous file is rejected before the closure runs.
            'max:' . ($ceilingKb ?? max(self::MAX_KB_BY_EXTENSION)),
            function (string $attribute, mixed $value, \Closure $fail) use ($ceilingKb) {
                if (! $value instanceof UploadedFile) {
                    return;
                }

                $extension = strtolower($value->getClientOriginalExtension());
                $limitKb   = self::maxKbFor($extension, $ceilingKb);

                if (($value->getSize() / 1024) > $limitKb) {
                    $fail(sprintf(
                        'A .%s file may be at most %d MB.',
                        $extension,
                        (int) round($limitKb / 1024)
                    ));
                }
            },
        ];
    }

    /**
     * Store an uploaded file and create its ClinicalFile record.
     * Dispatches watermark generation automatically for image types.
     */
    public function store(UploadedFile $file, array $context): ClinicalFile
    {
        if (empty($context['patient_id'])) {
            throw new \InvalidArgumentException('ClinicalFileUploadService::store() requires patient_id in context.');
        }

        // Defence in depth: the controllers validate too, but this is the one
        // door every upload path walks through, so the format rule is enforced
        // here as well — a new caller cannot forget it.
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, self::allowedExtensions(), true)) {
            throw new \InvalidArgumentException(
                "ClinicalFileUploadService::store() rejected .{$extension} — accepted formats are: "
                . implode(', ', self::allowedExtensions()) . '.'
            );
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

        $extension = strtolower($file->getClientOriginalExtension());

        // Extension wins for the 3D/imaging formats: their MIME types are
        // unreliable (a .stl and a .dcm both commonly arrive as
        // application/octet-stream, and some servers report .dcm as image/*,
        // which is exactly how a file nothing can render ends up labelled a photo).
        if ($extension === 'stl') return 'stl';
        if ($extension === 'dcm') return 'cbct';

        return 'other';
    }
}
