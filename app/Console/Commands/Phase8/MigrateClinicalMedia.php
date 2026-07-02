<?php

namespace App\Console\Commands\Phase8;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase 8A — Migrate clinical_media → clinical_files
 *
 * Usage:
 *   php artisan phase8:migrate-clinical-media --dry-run   (preview only)
 *   php artisan phase8:migrate-clinical-media             (live — asks for confirmation)
 *
 * Idempotent: tracks migrated rows via source_type='clinical_media' + source_id.
 * Safe to run multiple times — already-migrated rows are skipped.
 *
 * Visit resolution logic:
 *   - If visit_id is already set on the source row → use it directly.
 *   - If visit_id is null but treatment_name is set → try to find a matching
 *     treatment_visit for the same patient by treatment_name.
 *     - Match found  → set visit_id + procedure from the matched visit.
 *     - No match     → copy treatment_name into procedure, set needs_review = true.
 *   - If both null  → leave procedure null, needs_review = false.
 *
 * Does NOT delete or modify clinical_media. Non-destructive.
 */
class MigrateClinicalMedia extends Command
{
    protected $signature = 'phase8:migrate-clinical-media
                            {--dry-run : Preview what would be inserted without writing anything}';

    protected $description = 'Phase 8A — Migrate clinical_media → clinical_files';

    /** Fallback uploaded_by when doctor_id is null (agreed: user ID 1 = first admin). */
    const FALLBACK_USER_ID = 1;

    /** clinical_media.media_type → clinical_files.file_type */
    const MEDIA_TYPE_MAP = [
        'photo'  => 'photo',
        'xray'   => 'xray',
        'opg'    => 'opg',
        'cbct'   => 'cbct',
        'scan'   => 'intraoral_scan', // renamed in new schema
        'video'  => 'video',
        'pdf'    => 'pdf',
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
        $skipped  = 0; // already migrated (idempotency)
        $flagged  = 0; // needs_review = true (unresolvable treatment_name)
        $errors   = 0;

        // ── Load source records ────────────────────────────────────────────────
        $records = DB::table('clinical_media')->orderBy('id')->get();
        $total   = $records->count();

        $this->info("Found {$total} records in clinical_media.");
        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
        $bar->setMessage('Starting...');
        $bar->start();

        foreach ($records as $row) {

            // ── Idempotency ────────────────────────────────────────────────────
            // Use source_type + source_id to detect previously migrated rows.
            // Safe to re-run multiple times.
            $alreadyMigrated = DB::table('clinical_files')
                ->where('source_type', 'clinical_media')
                ->where('source_id', $row->id)
                ->exists();

            if ($alreadyMigrated) {
                $skipped++;
                $bar->setMessage("Skipping ID {$row->id} (already migrated)");
                $bar->advance();
                continue;
            }

            // ── Visit & Procedure Resolution ───────────────────────────────────
            $resolvedVisitId = $row->visit_id ?? null;
            $procedure       = null;
            $needsReview     = false;

            if ($resolvedVisitId) {
                // Visit already linked — pull procedure from the visit record
                $visit     = DB::table('treatment_visits')->find($resolvedVisitId);
                $procedure = $visit->treatment_name ?? $row->treatment_name ?? null;

            } elseif (! empty($row->treatment_name)) {
                // No visit_id but a treatment_name string exists.
                // Try to find the most recent matching visit for this patient.
                $matched = DB::table('treatment_visits')
                    ->where('patient_id', $row->patient_id)
                    ->where('treatment_name', $row->treatment_name)
                    ->orderByDesc('visit_date')
                    ->first();

                if ($matched) {
                    // Resolved — link to found visit
                    $resolvedVisitId = $matched->id;
                    $procedure       = $matched->treatment_name;
                } else {
                    // Unresolvable — preserve the string, flag for human review
                    $procedure   = $row->treatment_name;
                    $needsReview = true;
                    $flagged++;
                }
            }

            // ── Map file_type ──────────────────────────────────────────────────
            $fileType = self::MEDIA_TYPE_MAP[$row->media_type ?? ''] ?? 'other';

            // ── Merge tags (both columns) ──────────────────────────────────────
            $tags1 = $this->decodeJsonArray($row->tags ?? null);
            $tags2 = $this->decodeJsonArray($row->searchable_tags ?? null);
            $tags  = array_values(array_unique(array_merge($tags1, $tags2)));

            // ── captured_at: prefer media_date, fall back to visit_date ────────
            $capturedAt = $row->media_date ?? $row->visit_date ?? null;

            // ── Build insert payload ───────────────────────────────────────────
            $data = [
                // Anchors
                'patient_id'               => $row->patient_id,
                'visit_id'                 => $resolvedVisitId,
                'treatment_plan_item_id'   => null,
                // Clinical context
                'procedure'                => $procedure,
                'tooth_number'             => $row->tooth_no ?? null,
                'stage'                    => $this->mapStage($row->treatment_stage ?? null),
                // Classification
                'file_type'                => $fileType,
                'title'                    => null,
                'notes'                    => $row->notes ?? null,
                // Storage
                'disk'                     => $row->disk ?? 'public',
                'path'                     => $row->original_path ?? '',
                'watermarked_path'         => $row->watermarked_path ?? null,
                // File metadata
                'original_filename'        => $row->original_filename
                                                ?? basename($row->original_path ?? 'unknown'),
                'mime_type'                => $row->mime_type ?? 'application/octet-stream',
                'file_size'                => $row->file_size ?? 0,
                'captured_at'              => $capturedAt,
                'uploaded_by'              => $row->doctor_id ?? self::FALLBACK_USER_ID,
                // Source tracing — used for idempotency and audit
                'source_type'              => 'clinical_media',
                'source_id'                => $row->id,
                'protocol_step_id'         => null,
                // Sync (all historical = local_only)
                'sync_status'              => 'local_only',
                // Eligibility flags — no data to infer from; all false by default
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
                'tags'                     => ! empty($tags) ? json_encode($tags) : null,
                // Review flag (Phase 8 temp column)
                'needs_review'             => $needsReview,
                // Preserve original timestamps
                'created_at'               => $row->created_at ?? now(),
                'updated_at'               => $row->updated_at ?? now(),
                'deleted_at'               => null,
            ];

            if (! $isDryRun) {
                try {
                    DB::table('clinical_files')->insert($data);
                } catch (\Throwable $e) {
                    $errors++;
                    $this->newLine();
                    $this->error("  ✗ Failed on clinical_media.id={$row->id}: " . $e->getMessage());
                    $bar->advance();
                    continue;
                }
            }

            $inserted++;
            $bar->setMessage($needsReview ? "ID {$row->id} → flagged needs_review" : "ID {$row->id} → OK");
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // ── Summary table ──────────────────────────────────────────────────────
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total source records (clinical_media)',  $total],
                ['Rows inserted → clinical_files',         $isDryRun ? "{$inserted} (dry-run, not written)" : $inserted],
                ['Skipped — already migrated',             $skipped],
                ['Flagged needs_review (unresolved name)', $flagged],
                ['Errors',                                  $errors],
            ]
        );

        if ($isDryRun) {
            $this->warn('DRY RUN complete. No changes made.');
            $this->info('Run without --dry-run to execute live migration.');
        } else {
            $this->info('✅  Phase 8A migration complete.');
            if ($flagged > 0) {
                $this->warn("⚠️   {$flagged} records flagged needs_review.");
                $this->warn('    Filter with: ClinicalFile::where(\'needs_review\', true)->get()');
            }
            if ($errors > 0) {
                $this->error("✗  {$errors} records failed — review errors above.");
            }
        }

        return $errors > 0 ? 1 : 0;
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Map clinical_media.treatment_stage → clinical_files.stage enum.
     * clinical_media values already match the new enum — just guard unknown values.
     */
    private function mapStage(?string $stage): string
    {
        return match ($stage) {
            'before'   => 'before',
            'during'   => 'during',
            'after'    => 'after',
            'followup' => 'followup',
            default    => 'general',
        };
    }

    /**
     * Safely decode a JSON column that may be null, empty, or invalid.
     * Always returns a flat array of strings.
     */
    private function decodeJsonArray(?string $json): array
    {
        if (empty($json)) return [];
        $decoded = json_decode($json, true);
        return is_array($decoded) ? array_filter($decoded, 'is_string') : [];
    }
}
