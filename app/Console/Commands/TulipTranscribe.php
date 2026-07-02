<?php

namespace App\Console\Commands;

use App\Services\Voice\TranscriptionService;
use Illuminate\Console\Command;

/**
 * tulip:transcribe — test the local Whisper transcription pipeline.
 * ----------------------------------------------------------------------------
 *   php artisan tulip:transcribe path\to\audio.mp3
 *
 * Use any audio/video file to confirm faster-whisper is installed and working
 * before wiring the mic into the chat widget.
 */
class TulipTranscribe extends Command
{
    protected $signature = 'tulip:transcribe {file : Path to an audio file (absolute or relative to project root)}';
    protected $description = 'Transcribe an audio file with the local Whisper model (voice test).';

    public function handle(TranscriptionService $service): int
    {
        $file = $this->argument('file');

        // Allow a path relative to the project root.
        if (!is_file($file)) {
            $candidate = base_path($file);
            if (is_file($candidate)) {
                $file = $candidate;
            }
        }

        if (!is_file($file)) {
            $this->error("File not found: {$file}");
            return self::FAILURE;
        }

        $this->info('Transcribing… (first run downloads the model, please wait)');
        $start = microtime(true);

        try {
            $result = $service->transcribe(realpath($file));
        } catch (\Throwable $e) {
            $this->error('Error: ' . $e->getMessage());
            return self::FAILURE;
        }

        $secs = round(microtime(true) - $start, 1);

        $this->newLine();
        $this->line('<fg=cyan>Language:</> ' . ($result['language'] ?? 'unknown'));
        $this->line('<fg=cyan>Transcript:</>');
        $this->line($result['text'] ?: '(empty)');
        $this->newLine();
        $this->comment("({$secs}s)");

        return self::SUCCESS;
    }
}
