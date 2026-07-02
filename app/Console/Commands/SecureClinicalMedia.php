<?php

namespace App\Console\Commands;

use App\Models\ClinicalFile;
use App\Models\ClinicalMedia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * media:secure-clinical  (Phase A — Security backfill)
 * ----------------------------------------------------
 * Moves EXISTING clinical files off the public disk and onto the private
 * "local" disk, then updates their `disk` column to 'local'.
 *
 * New uploads already go to the private disk (see ClinicalMediaService and
 * ClinicalLibraryController). This command fixes the files that were uploaded
 * BEFORE the change and are still publicly reachable under /storage/...
 *
 * Safe to re-run: it only touches rows whose disk is still 'public'.
 *
 *   php artisan media:secure-clinical --dry-run   # show what would move
 *   php artisan media:secure-clinical             # actually move them
 */
class SecureClinicalMedia extends Command
{
    protected $signature = 'media:secure-clinical {--dry-run : List files that would move without changing anything}';

    protected $description = 'Move existing clinical files from the public disk to the private disk (Phase A security backfill).';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $this->info($dry ? 'DRY RUN — no files will be moved.' : 'Moving public clinical files to the private disk...');

        $moved = 0;
        $skipped = 0;
        $failed = 0;

        // ── clinical_files (Phase 9 table) ──────────────────────────────────
        foreach (ClinicalFile::where('disk', 'public')->cursor() as $file) {
            $paths = array_filter([$file->path, $file->watermarked_path]);
            $ok = true;

            foreach ($paths as $path) {
                $result = $this->movePath($path, $dry);
                if ($result === 'failed') { $ok = false; }
            }

            if ($ok && ! $dry) {
                $file->update(['disk' => 'local']);
            }
            $ok ? $moved++ : $failed++;
        }

        // ── clinical_media (legacy table) ───────────────────────────────────
        foreach (ClinicalMedia::where('disk', 'public')->cursor() as $media) {
            $paths = array_filter([$media->original_path, $media->watermarked_path]);
            $ok = true;

            foreach ($paths as $path) {
                $result = $this->movePath($path, $dry);
                if ($result === 'failed') { $ok = false; }
            }

            if ($ok && ! $dry) {
                $media->update(['disk' => 'local']);
            }
            $ok ? $moved++ : $failed++;
        }

        $this->newLine();
        $this->table(['Records moved', 'Failed'], [[$moved, $failed]]);

        if ($dry) {
            $this->warn('Dry run complete. Re-run without --dry-run to apply.');
        } else {
            $this->info('Done. Clinical files are now private. Verify a few open fine in the app, then you may delete leftover public copies.');
        }

        return self::SUCCESS;
    }

    /**
     * Copy one file from the public disk to the private (local) disk, keeping the
     * same relative path, then remove the public copy. Returns a status string.
     */
    private function movePath(string $path, bool $dry): string
    {
        $public = Storage::disk('public');
        $local  = Storage::disk('local');

        if (! $public->exists($path)) {
            // Already gone (maybe a previous run handled it) — nothing to do.
            $this->line("  skip (not on public): {$path}");
            return 'skipped';
        }

        if ($dry) {
            $this->line("  would move: {$path}");
            return 'ok';
        }

        try {
            // Stream the file across so large x-rays/CBCT don't blow up memory.
            $stream = $public->readStream($path);
            $local->writeStream($path, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            // Only delete the public copy once the private copy is confirmed.
            if ($local->exists($path)) {
                $public->delete($path);
                $this->line("  moved: {$path}");
                return 'ok';
            }

            $this->error("  FAILED to write private copy: {$path}");
            return 'failed';
        } catch (\Throwable $e) {
            $this->error("  FAILED: {$path} — " . $e->getMessage());
            return 'failed';
        }
    }
}
