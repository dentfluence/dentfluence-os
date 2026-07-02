<?php

namespace App\Jobs;

use App\Models\ClinicalFile;
use App\Services\ClinicalLibrary\WatermarkService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Phase 10 — GenerateWatermark Job
 *
 * Dispatched by ClinicalFileController::store() after a new clinical file is saved.
 * Runs in the background via the configured queue (default: database).
 *
 * What it does:
 *   1. Calls WatermarkService::generate() to create wm_{filename}.jpg
 *   2. Updates ClinicalFile::watermarked_path with the result
 *   3. On any failure: logs a warning and exits cleanly (never crashes the queue)
 *
 * IMPORTANT: The original file path (ClinicalFile::$path) is NEVER modified here.
 *
 * Usage:
 *   GenerateWatermark::dispatch($clinicalFile);
 *   GenerateWatermark::dispatch($clinicalFile)->onQueue('watermarks'); // dedicated queue
 *
 * To process the queue in Laragon:
 *   php artisan queue:work --queue=watermarks,default
 */
class GenerateWatermark implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times to retry on failure before giving up.
     * 3 attempts covers transient storage hiccups.
     */
    public int $tries = 3;

    /**
     * Seconds to wait before retrying after a failure.
     */
    public int $backoff = 10;

    /**
     * Seconds the job may run before it is killed (safety net for large images).
     */
    public int $timeout = 120;

    // ── Constructor ────────────────────────────────────────────────────────────

    /**
     * @param  ClinicalFile  $file  The newly uploaded clinical file.
     *                              SerializesModels will re-fetch it from the DB on handle().
     */
    public function __construct(
        public readonly ClinicalFile $file
    ) {
        // Route to a dedicated 'watermarks' queue so heavy image work
        // doesn't block other jobs. Falls back to 'default' if that queue
        // isn't being worked.
        $this->onQueue('watermarks');
    }

    // ── Handle ─────────────────────────────────────────────────────────────────

    /**
     * Execute the watermark generation.
     */
    public function handle(WatermarkService $service): void
    {
        $file = $this->file;

        // Guard: re-check the file still exists in the DB (not soft-deleted after dispatch)
        if (! $file->exists || $file->trashed()) {
            Log::info("GenerateWatermark: ClinicalFile #{$file->id} was deleted before job ran. Skipping.");
            return;
        }

        // Guard: only image types are watermarkable
        if (! $file->isImage()) {
            Log::info("GenerateWatermark: ClinicalFile #{$file->id} is not an image type ({$file->file_type}). Skipping.");
            return;
        }

        // Guard: watermark already exists — don't regenerate unless explicitly cleared
        if (! empty($file->watermarked_path)) {
            Log::info("GenerateWatermark: ClinicalFile #{$file->id} already has a watermarked copy. Skipping.");
            return;
        }

        // Load visit + doctor relationship so WatermarkService can pull doctor name
        $file->loadMissing(['visit.doctor']);

        // Generate the watermarked copy
        $watermarkedPath = $service->generate($file);

        if ($watermarkedPath === null) {
            // WatermarkService already logged the reason — nothing more to do here
            return;
        }

        // Persist the watermarked path — original $file->path is untouched
        $file->updateQuietly(['watermarked_path' => $watermarkedPath]);

        Log::info("GenerateWatermark: ClinicalFile #{$file->id} watermarked at [{$watermarkedPath}].");
    }

    // ── Failure Handling ───────────────────────────────────────────────────────

    /**
     * Called when all $tries are exhausted.
     * We log but do NOT throw — a missing watermark is non-fatal.
     * The original file remains perfectly accessible.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error(
            "GenerateWatermark: permanently failed for ClinicalFile #{$this->file->id} — " .
            $exception->getMessage()
        );
    }
}
