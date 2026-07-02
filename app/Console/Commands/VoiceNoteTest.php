<?php

namespace App\Console\Commands;

use App\Services\Voice\ClinicalNoteService;
use App\Services\Voice\TranscriptionService;
use Illuminate\Console\Command;

/**
 * voicenote:test — full voice clinical-notes pipeline test.
 * ----------------------------------------------------------------------------
 *   php artisan voicenote:test "C:\path\to\consultation.m4a"
 *
 * Transcribes the audio, then extracts structured dental clinical notes — so you
 * can see the whole "record → notes" flow before the UI is wired.
 */
class VoiceNoteTest extends Command
{
    protected $signature = 'voicenote:test {file : Audio file of a consultation}';
    protected $description = 'Transcribe a consultation recording and extract structured clinical notes.';

    public function handle(TranscriptionService $stt, ClinicalNoteService $notes): int
    {
        $file = $this->argument('file');
        if (!is_file($file) && is_file(base_path($file))) {
            $file = base_path($file);
        }
        if (!is_file($file)) {
            $this->error("File not found: {$file}");
            return self::FAILURE;
        }

        // 1. Transcribe
        $this->info('Transcribing…');
        $t0 = microtime(true);
        try {
            $tr = $stt->transcribe(realpath($file));
        } catch (\Throwable $e) {
            $this->error('Transcription failed: ' . $e->getMessage());
            return self::FAILURE;
        }
        $this->line('<fg=gray>(' . round(microtime(true) - $t0, 1) . 's, lang: ' . ($tr['language'] ?? '?') . ')</>');
        $this->newLine();
        $this->line('<fg=cyan>TRANSCRIPT:</>');
        $this->line($tr['text'] ?: '(empty)');
        $this->newLine();

        // 2. Extract clinical notes
        $this->info('Extracting clinical notes…');
        $t1 = microtime(true);
        try {
            $structured = $notes->analyze($tr['text']);
        } catch (\Throwable $e) {
            $this->error('Note extraction failed: ' . $e->getMessage());
            return self::FAILURE;
        }
        $this->line('<fg=gray>(' . round(microtime(true) - $t1, 1) . 's)</>');
        $this->newLine();

        $this->line('<fg=green>CLINICAL NOTES:</>');
        foreach ($structured as $key => $val) {
            if ($val === '' || $key === '_raw') continue;
            $label = ucwords(str_replace('_', ' ', $key));
            $this->line("<fg=yellow>{$label}:</> {$val}");
        }
        if (!empty($structured['_raw'])) {
            $this->warn('Model did not return clean JSON; raw output:');
            $this->line($structured['_raw']);
        }

        return self::SUCCESS;
    }
}
