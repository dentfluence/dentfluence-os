<?php

namespace App\Console\Commands\Phase8;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase 8B — Merge cms_media → clinical_files
 *
 * Usage:
 *   php artisan phase8:merge-cms-media --dry-run   (preview only)
 *   php artisan phase8:merge-cms-media             (live — asks for confirmation)
 *
 * Idempotent: tracks migrated rows via source_type='cms_media' + source_id.
 *
 * Deduplication:
 *   Two-layer check before inserting any row:
 *   1. Already migrated from cms_media (source_type + source_id match) → skip.
 *   2. Same patient_id + original_path already exists in clinical_files
 *      (previously imported via 8A from clinical_media) → skip as duplicate.
 *
 * Marketing flags transferred:
 *   is_marketing (bool) → is_marketing_eligible
 *   consent_status      → consent_status  (values already match enum)
 *   marketing_status    → marketing_status (values already match enum)
 *
 * Stage remap:
 *   cms_media uses verbose names ('before_treatment', 'after_treatment' etc.)
 *   clinical_files uses short names ('before', 'after' etc.)
 *
 * Soft-deleted cms_media rows are included (deleted_at is preserved in clinical_files).
 * Does NOT delete or modify cms_media. Non-destructive.
 */
class MergeCmsMedia extends Command
{
    protected $signature = 'phase8:merge-cms-media
                            {--dry-run : Preview what would be inserted without writing anything}';

    protected $description = 'Phase 8B — Merge cms_media → clinical_files (deduplicates by patient_id + path)';

    const FALLBACK_USER_ID = 1;

    /** cms_media.treatment_stage → clinical_files.stage enum */
    const STAGE_MAP = [
        // Verbose old names
        'before_treatment' => 'before',
        'during_treatment' => 'during',
        'after_treatment'  => 'after',
        'follow_up'        => 'followup',
        // Short names (in case some rows already use them)
        'before'           => 'before',
        'during'           => 'during',
        'after'            => 'after',
        'followup'         => 'followup',
    ];

    /** cms_media.media_type → clinical_files.file_type */
    const MEDIA_TYPE_MAP = [
        'photo'  => 'photo',
        'xray'   => 'xray',
        'opg'    => 'opg',
        'cbct'   => 'cbct',
        'scan'   => 'intraoral_scan',
        'video'  => 'video',
        'pdf'    => 'pdf',
    ];

    /** Valid values for consent_status enum on clinical_files */
    const CONSENT_VALUES = ['not_given', 'pending', 'given'];

    /** Valid values for marketing_status enum on clinical_files */
    const MARKETING_STATUS_VALUES = ['pending', 'approved', 'rejected'];

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
        $total       = 0;
        $inserted    = 0;
        $skippedSelf = 0; // already migrated from cms_media
        $skippedDupe = 0; // duplicate path (already in clinical_files from 8A)
        $errors      = 0;

        // ── Load source records (including soft-deleted) ───────────────────────
        $records = DB::table('cms_media')->orderBy('id')->get();
        $total   = $records->count();

        $this->info("Found {$total} records in cms_media (including soft-deleted).");
        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
        $bar->setMessage('Starting...');
        $bar->start();

        foreach ($records as $row) {

            // ── Layer 1: Idempotency — already migrated from cms_media ─────────
            $alreadyMigrated = DB::table('clinical_files')
                ->where('source_type', 'cms_media')
                ->where('source_id', $row->id)
                ->exists();

            if ($alreadyMigrated) {
                $skippedSelf++;
                $bar->setMessage("Skipping ID {$row->id} (already migrated)");
                $bar->advance();
                continue;
            }

            // ── Layer 2: Dedup — same patient + same file path ─────────────────
            // Catches files imported via 8A from clinical_media that share the
            // same physical file (original_path) with a cms_media record.
            if (! empty($row->original_path) && ! empty($row->patient_id)) {
                $pathExists = DB::table('clinical_files')
                    ->where('patient_id', $row->patient_id)
                    ->where('path', $row->original_path)
                    ->exists();

                if ($pathExists) {
                    $skippedDupe++;
                    $bar->setMessage("Skipping ID {$row->id} (duplicate path)");
                    $bar->advance();
                    continue;
                }
            }

            // ── Map columns ────────────────────────────────────────────────────
            $fileType = self::MEDIA_TYPE_MAP[$row->media_type ?? ''] ?? 'other';
            $stage    = self::STAGE_MAP[$row->treatment_stage ?? ''] ?? 'general';

            // Validate enum values before inserting — fall back to safe defaults
            $consentStatus   = in_array($row->consent_status, self::CONSENT_VALUES)
                                 ? $row->consent_status
                                 : 'not_given';

            $marketingStatus = in_array($row->marketing_status, self::MARKETING_STATUS_VALUES)
                                 ? $row->marketing_status
                                 : null;

            $tags = $this->decodeJsonArray($row->searchable_tags ?? null);

            // ── Build insert payload ───────────────────────────────────────────
            $data = [
                // Anchors
                'patient_id'               => $row->patient_id,
                'visit_id'                 => $row->visit_id ?? null,
                'treatment_plan_item_id'   => null,
                // Clinical context
                'procedure'                => $row->treatment_name ?? null,
                'tooth_number'             => $row->tooth_no ?? null,
                'stage'                    => $stage,
                // Classification
                'file_type'                => $fileType,
                'title'                    => null,
                'notes'                    => null,
                // Storage
                'disk'                     => 'public',
                'path'                     => $row->original_path ?? '',
                'watermarked_path'         => $row->watermarked_path ?? null,
                // File metadata
                'original_filename'        => $row->original_filename
                                                ?? basename($row->original_path ?? 'unknown'),
                'mime_type'                => $row->mime_type ?? 'application/octet-stream',
                'file_size'                => $row->file_size ?? 0,
                'captured_at'              => $row->upload_date ?? null,
                'uploaded_by'              => $row->doctor_id ?? self::FALLBACK_USER_ID,
                // Source tracing
                'source_type'              => 'cms_media',
                'source_id'                => $row->id,
                'protocol_step_id'         => null,
                // Sync
                'sync_status'              => 'local_only',
                // Eligibility flags — transfer marketing flag; rest default false
                'is_marketing_eligible'    => (bool) ($row->is_marketing ?? false),
                'is_education_eligible'    => false,
                'is_teaching_eligible'     => false,
                'is_research_eligible'     => false,
                'is_case_library_eligible' => false,
                // Consent & approval — transferred from cms_media
                'consent_status'           => $consentStatus,
                'marketing_status'         => $marketingStatus,
                // Optional metadata
                'content_rating'           => null,
                'tags'                     => ! empty($tags) ? json_encode($tags) : null,
                // Review flag (not needed for cms_media — procedure is just a string copy)
                'needs_review'             => false,
                // Preserve timestamps; preserve soft-delete state
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
                    $this->error("  ✗ Failed on cms_media.id={$row->id}: " . $e->getMessage());
                    $bar->advance();
                    continue;
                }
            }

            $inserted++;
            $bar->setMessage("ID {$row->id} → OK");
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // ── Summary table ──────────────────────────────────────────────────────
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total source records (cms_media)',       $total],
                ['Rows inserted → clinical_files',         $isDryRun ? "{$inserted} (dry-run, not written)" : $inserted],
                ['Skipped — already migrated',             $skippedSelf],
                ['Skipped — duplicate path (from 8A)',     $skippedDupe],
                ['Errors',                                  $errors],
            ]
        );

        if ($isDryRun) {
            $this->warn('DRY RUN complete. No changes made.');
            $this->info('Run without --dry-run to execute live migration.');
        } else {
            $this->info('✅  Phase 8B merge complete.');
            if ($errors > 0) {
                $this->error("✗  {$errors} records failed — review errors above.");
            }
        }

        return $errors > 0 ? 1 : 0;
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function decodeJsonArray(?string $json): array
    {
        if (empty($json)) return [];
        $decoded = json_decode($json, true);
        return is_array($decoded) ? array_filter($decoded, 'is_string') : [];
    }
}
