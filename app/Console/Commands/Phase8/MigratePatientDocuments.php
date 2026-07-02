<?php

namespace App\Console\Commands\Phase8;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase 8C — Migrate patient_documents → clinical_files
 *
 * Usage:
 *   php artisan phase8:migrate-patient-documents --dry-run   (preview only)
 *   php artisan phase8:migrate-patient-documents             (live — asks for confirmation)
 *
 * Idempotent: tracks migrated rows via source_type='patient_documents' + source_id.
 *
 * Scope: patient_documents are patient-scoped only.
 *   visit_id = null (patient_documents has no visit link)
 *   stage    = 'general'
 *   procedure = null
 *
 * category → file_type mapping:
 *   consent   → consent
 *   invoice   → invoice
 *   estimate  → estimate
 *   xray      → xray
 *   pdf       → pdf
 *   lab_slip  → lab_slip
 *   stl       → stl
 *   photo     → photo
 *   video     → video
 *   (anything else) → other
 *
 * Soft-deleted patient_documents rows are included (deleted_at is preserved).
 * Does NOT delete or modify patient_documents. Non-destructive.
 */
class MigratePatientDocuments extends Command
{
    protected $signature = 'phase8:migrate-patient-documents
                            {--dry-run : Preview what would be inserted without writing anything}';

    protected $description = 'Phase 8C — Migrate patient_documents → clinical_files (patient-scope only)';

    /** patient_documents.category → clinical_files.file_type enum */
    const CATEGORY_MAP = [
        'consent'  => 'consent',
        'invoice'  => 'invoice',
        'estimate' => 'estimate',
        'xray'     => 'xray',
        'pdf'      => 'pdf',
        'lab_slip' => 'lab_slip',
        'stl'      => 'stl',
        'photo'    => 'photo',
        'video'    => 'video',
    ];

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        // ── Mode banner ────────────────────────────────────────────────────────
        if ($isDryRun) {
            $this->warn('');
            $this->warn('  ⚡  DRY RUN — no data will be written  ⚡');
            $this->warn('');
        } else {
            $this->info('');
            $this->error('  🚀  LIVE MODE — data WILL be inserted into clinical_files  ');
            $this->info('');
            if (! $this->confirm('  Type yes to confirm and proceed with live migration')) {
                $this->info('Aborted.');
                return 0;
            }
        }

        // ── Counters ───────────────────────────────────────────────────────────
        $total    = 0;
        $inserted = 0;
        $skipped  = 0;
        $errors   = 0;

        // ── Load ALL source records including soft-deleted ─────────────────────
        // We preserve soft-delete state, so nothing is ever lost.
        $records = DB::table('patient_documents')->orderBy('id')->get();
        $total   = $records->count();

        $this->info("Found {$total} records in patient_documents.");
        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
        $bar->setMessage('Starting...');
        $bar->start();

        foreach ($records as $row) {

            // ── Idempotency ────────────────────────────────────────────────────
            $alreadyMigrated = DB::table('clinical_files')
                ->where('source_type', 'patient_documents')
                ->where('source_id', $row->id)
                ->exists();

            if ($alreadyMigrated) {
                $skipped++;
                $bar->setMessage("Skipping ID {$row->id} (already migrated)");
                $bar->advance();
                continue;
            }

            // ── Map category → file_type ───────────────────────────────────────
            $fileType = self::CATEGORY_MAP[$row->category ?? ''] ?? 'other';

            // ── uploaded_by guard ──────────────────────────────────────────────
            // patient_documents.uploaded_by is filled by the controller on every
            // upload, but guard against nulls in historical data.
            $uploadedBy = $row->uploaded_by ?? 1;

            // ── Build insert payload ───────────────────────────────────────────
            $data = [
                // Anchors — patient-scope only, no visit link
                'patient_id'               => $row->patient_id,
                'visit_id'                 => null,
                'treatment_plan_item_id'   => null,
                // Clinical context — not applicable for general patient docs
                'procedure'                => null,
                'tooth_number'             => null,
                'stage'                    => 'general',
                // Classification
                'file_type'                => $fileType,
                'title'                    => $row->title ?? null,
                'notes'                    => $row->notes ?? null,
                // Storage
                'disk'                     => 'public',
                'path'                     => $row->path ?? '',
                'watermarked_path'         => null,
                // File metadata
                'original_filename'        => $row->original_name
                                                ?? basename($row->path ?? 'unknown'),
                'mime_type'                => $row->mime_type ?? 'application/octet-stream',
                'file_size'                => $row->file_size ?? 0,
                // Use created_at as captured_at — patient_documents has no capture date
                'captured_at'              => $row->created_at ?? null,
                'uploaded_by'              => $uploadedBy,
                // Source tracing
                'source_type'              => 'patient_documents',
                'source_id'                => $row->id,
                'protocol_step_id'         => null,
                // Sync
                'sync_status'              => 'local_only',
                // Eligibility flags — all false; these are admin/clinical docs
                'is_marketing_eligible'    => false,
                'is_education_eligible'    => false,
                'is_teaching_eligible'     => false,
                'is_research_eligible'     => false,
                'is_case_library_eligible' => false,
                // Consent & approval
                'consent_status'           => 'not_given',
                'marketing_status'         => null,
                // Optional metadata
                'content_rating'           => null,
                'tags'                     => null,
                // Review flag — not needed for patient docs (no visit resolution required)
                'needs_review'             => false,
                // Preserve timestamps and soft-delete state
                'created_at'               => $row->created_at ?? now(),
                'updated_at'               => $row->updated_at ?? now(),
                'deleted_at'               => $row->deleted_at ?? null,
            ];

            if (! $isDryRun) {
                try {
                    DB::table('clinical_files')->insert($data);
                } catch (\Throwable $e) {
                    $errors++;
                    $this->newLine();
                    $this->error("  ✗ Failed on patient_documents.id={$row->id}: " . $e->getMessage());
                    $bar->advance();
                    continue;
                }
            }

            $inserted++;
            $bar->setMessage("ID {$row->id} → OK ({$fileType})");
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // ── Summary table ──────────────────────────────────────────────────────
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total source records (patient_documents)', $total],
                ['Rows inserted → clinical_files',           $isDryRun ? "{$inserted} (dry-run, not written)" : $inserted],
                ['Skipped — already migrated',               $skipped],
                ['Errors',                                    $errors],
            ]
        );

        if ($isDryRun) {
            $this->warn('DRY RUN complete. No changes made.');
            $this->info('Run without --dry-run to execute live migration.');
        } else {
            $this->info('✅  Phase 8C migration complete.');
            if ($errors > 0) {
                $this->error("✗  {$errors} records failed — review errors above.");
            }
        }

        return $errors > 0 ? 1 : 0;
    }
}
