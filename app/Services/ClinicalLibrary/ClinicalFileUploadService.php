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
            // `extensions:`, NOT `mimes:`.
            //
            // `mimes:` does not read the filename at all — it sniffs the file's
            // content, guesses an extension from the detected MIME type, and
            // matches THAT against the list. For a JPEG or a PDF the two agree.
            // For the two formats P1 deliberately added they never can: a real
            // .stl and a real .dcm are sniffed as application/octet-stream,
            // which guesses back as `.bin`, which is not in any allowlist and
            // never will be. So `mimes:` silently rejected every STL and every
            // DICOM — the exact files a lab case exists to carry — while the
            // allowlist above said they were accepted. Two rules, one of them
            // decorative; this module has shipped that mistake three times.
            //
            // `extensions:` reads the extension the upload actually carries,
            // which is the same thing store() re-checks below and the same thing
            // the per-format cap is chosen by. The cost is that a renamed file
            // passes this rule — which is survivable here and nowhere else in
            // the app: clinical files go to a private disk, are streamed back
            // through SecureMediaController, and are never executed or included.
            'extensions:' . implode(',', self::allowedExtensions()),
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

        // uploaded_by is NOT NULL in the schema, deliberately: every clinical
        // file has someone answerable for it. Auth::id() is null in a console
        // command, a queued job or a scheduled import, and without this guard
        // that arrives as a raw SQLSTATE 1364 from deep inside Eloquent — which
        // says nothing about what the caller forgot to pass.
        $uploadedBy = $context['uploaded_by'] ?? Auth::id();

        if (empty($uploadedBy)) {
            throw new \InvalidArgumentException(
                'ClinicalFileUploadService::store() requires uploaded_by in context when no user is authenticated '
                . '(console commands, queued jobs and imports must name the uploader).'
            );
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
            'uploaded_by'              => $uploadedBy,
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
        $extension = strtolower($file->getClientOriginalExtension());

        // The extension is checked FIRST, and only for these two, because their
        // MIME types are unreliable: a .stl and a .dcm both commonly arrive as
        // application/octet-stream, and some servers announce a .dcm as image/*.
        // That last case is the whole problem — it is exactly the route by which
        // a file nothing can render gets labelled a photo, rendered into an <img>
        // no browser can decode, and left as a permanently broken tile. The
        // comment here used to claim the extension won while the MIME test sat
        // above it and took every image/* DICOM first.
        if ($extension === 'stl') return 'stl';
        if ($extension === 'dcm') return 'cbct';

        $mime = $file->getMimeType();

        if (str_starts_with($mime, 'image/')) return 'photo';
        if (str_starts_with($mime, 'video/')) return 'video';
        if ($mime === 'application/pdf') return 'pdf';

        return 'other';
    }
}
