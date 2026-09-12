<?php

namespace App\Console\Commands\ClinicalLibrary;

use App\Jobs\GenerateWatermark;
use App\Models\ClinicalFile;
use App\Models\LabCase;
use App\Models\LabCaseAttachment;
use App\Services\ClinicalLibrary\TreatmentCategoryDetector;
use Illuminate\Console\Command;

/**
 * P1 — move legacy lab_case_attachments rows into clinical_files.
 *
 * Usage:
 *   php artisan lab:migrate-attachments --dry-run   (preview only)
 *   php artisan lab:migrate-attachments             (live)
 *
 * NOTHING IS MOVED ON DISK and nothing is deleted. The file stays exactly where
 * it was written (the private 'local' disk, under lab-attachments/); this only
 * creates the clinical_files row that points at it, so the same bytes become
 * visible from the patient's Documents tab, the Clinical Library and the Content
 * Manager instead of only from one lab screen.
 *
 * Idempotent: a row already migrated is recognised by
 * (source_type, source_id, path) and skipped, so running it twice is safe.
 *
 * Skipped on purpose, and reported rather than guessed:
 *   - an attachment whose lab case has no patient_id — a clinical file must be
 *     anchored to a patient, and inventing one would be worse than leaving it
 *   - an attachment whose lab case no longer exists
 */
class MigrateLabAttachments extends Command
{
    protected $signature = 'lab:migrate-attachments
                            {--dry-run : Show what would be created without writing anything}';

    protected $description = 'Clinical Library P1 — file legacy lab case attachments into clinical_files (no files are moved or deleted)';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('');
            $this->warn('  ⚡  DRY RUN — nothing will be written  ⚡');
            $this->warn('');
        }

        $attachments = LabCaseAttachment::with('labCase')->orderBy('id')->get();

        if ($attachments->isEmpty()) {
            $this->info('lab_case_attachments is empty — nothing to migrate.');

            return self::SUCCESS;
        }

        $created = 0;
        $skipped = 0;
        $blocked = [];

        foreach ($attachments as $attachment) {
            $labCase = $attachment->labCase;

            if (! $labCase instanceof LabCase) {
                $blocked[] = "#{$attachment->id} {$attachment->original_name} — its lab case no longer exists";
                continue;
            }

            if (! $labCase->patient_id) {
                $blocked[] = "#{$attachment->id} {$attachment->original_name} — lab case {$labCase->case_number} has no patient linked";
                continue;
            }

            $alreadyThere = ClinicalFile::withTrashed()
                ->where('source_type', LabCase::class)
                ->where('source_id', $labCase->id)
                ->where('path', $attachment->file_path)
                ->exists();

            if ($alreadyThere) {
                $skipped++;
                continue;
            }

            $procedure = trim(($labCase->work_category ?: 'Lab work') . ' ' . ($labCase->work_subtype ?: ''));

            $this->line(sprintf(
                '  %s  %s  →  patient #%d · %s',
                $isDryRun ? '[would file]' : '[filed]',
                $attachment->original_name,
                $labCase->patient_id,
                $procedure
            ));

            if ($isDryRun) {
                $created++;
                continue;
            }

            $labCase->loadMissing('items');

            $record = ClinicalFile::create([
                'patient_id'         => $labCase->patient_id,
                'visit_id'           => $labCase->treatment_visit_id,
                'procedure'          => $procedure,
                'treatment_category' => TreatmentCategoryDetector::detect($procedure),
                'tooth_number'       => $labCase->items->pluck('tooth_number')->filter()->unique()->implode(', ') ?: null,
                'stage'              => 'during',
                'file_type'          => $this->fileTypeFor($attachment),
                'disk'               => 'local',
                'path'               => $attachment->file_path,
                'original_filename'  => $attachment->original_name,
                'mime_type'          => $attachment->mime_type,
                'file_size'          => $attachment->size_bytes,
                'captured_at'        => $attachment->created_at,
                'uploaded_by'        => $attachment->uploaded_by,
                'source_type'        => LabCase::class,
                'source_id'          => $labCase->id,
                'consent_status'     => 'not_given',
                'sync_status'        => 'local_only',
            ]);

            if ($record->isImage()) {
                GenerateWatermark::dispatch($record);
            }

            $created++;
        }

        $this->newLine();
        $this->info(($isDryRun ? 'Would file: ' : 'Filed: ') . $created);
        $this->info('Already migrated, skipped: ' . $skipped);

        if ($blocked !== []) {
            $this->newLine();
            $this->warn('Left alone (' . count($blocked) . ') — these need a human, not a guess:');
            foreach ($blocked as $line) {
                $this->warn('  · ' . $line);
            }
        }

        $this->newLine();
        $this->comment('No file was moved or deleted. lab_case_attachments rows are left in place.');

        return self::SUCCESS;
    }

    /**
     * Map the legacy category/extension onto a clinical_files file_type.
     * Extension wins for stl/dcm: their MIME types are unreliable.
     */
    private function fileTypeFor(LabCaseAttachment $attachment): string
    {
        $extension = strtolower(pathinfo((string) $attachment->original_name, PATHINFO_EXTENSION));

        if ($extension === 'stl') return 'stl';
        if ($extension === 'dcm') return 'cbct';
        if ($extension === 'pdf') return 'pdf';

        return match ($attachment->category) {
            'stl'             => 'stl',
            'xray'            => 'xray',
            'prescription'    => 'lab_slip',
            'pdf'             => 'pdf',
            'intraoral_photo',
            'shade_photo'     => 'photo',
            default           => str_starts_with((string) $attachment->mime_type, 'image/') ? 'photo' : 'other',
        };
    }
}
