<?php

namespace App\Services\Voice;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * TranscriptionService — runs the local faster-whisper script and returns text.
 * ----------------------------------------------------------------------------
 * Shells out to scripts/voice/transcribe.py with the configured model/device.
 * 100% local — audio never leaves the machine.
 */
class TranscriptionService
{
    /**
     * Transcribe an audio file at an ABSOLUTE path.
     *
     * @return array{text:string, language:?string}
     */
    public function transcribe(string $absolutePath): array
    {
        $cfg = config('services.voice');

        if (!is_file($absolutePath)) {
            throw new RuntimeException("Audio file not found: {$absolutePath}");
        }
        if (!is_file($cfg['whisper_script'])) {
            throw new RuntimeException("Whisper script missing at {$cfg['whisper_script']}");
        }

        // Build a complete environment for the child process. CRITICAL on
        // Windows: ensure SystemRoot is present — without it Winsock can't
        // initialise and Python's import fails with WinError 10106. The web
        // server often strips it, so we set it (and friends) explicitly.
        $parentEnv = is_array(getenv()) ? getenv() : [];
        $env = array_merge($parentEnv, [
            'SystemRoot'  => getenv('SystemRoot')  ?: 'C:\\Windows',
            'SystemDrive' => getenv('SystemDrive') ?: 'C:',
            'windir'      => getenv('windir')      ?: 'C:\\Windows',
        ]);

        $process = new Process([
            $cfg['whisper_python'] ?? 'python',
            $cfg['whisper_script'],
            $absolutePath,
            '--model',    $cfg['whisper_model']   ?? 'small',
            '--device',   $cfg['whisper_device']  ?? 'cuda',
            '--compute',  $cfg['whisper_compute'] ?? 'int8_float16',
            '--language', 'auto',   // detect any language (English/Hindi/Marathi…)
        ], base_path(), $env);
        $process->setTimeout(600); // allow first-run model download

        $process->run();

        // The script prints a JSON line to stdout for BOTH success and handled
        // errors. Parse that first — even if the exit code is non-zero — so the
        // REAL reason surfaces instead of harmless stderr warnings.
        $stdout = $process->getOutput();
        $lines  = array_values(array_filter(array_map('trim', explode("\n", $stdout))));

        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $data = json_decode($lines[$i], true);
            if (is_array($data)) {
                if (!empty($data['error'])) {
                    throw new RuntimeException($data['error']);
                }
                return [
                    'text'     => trim($data['text'] ?? ''),
                    'language' => $data['language'] ?? null,
                ];
            }
        }

        // No JSON found at all — a genuine crash/kill. Surface whatever we have.
        throw new RuntimeException(
            'Transcription failed. ' . trim($process->getErrorOutput() ?: $stdout ?: 'no output')
        );
    }
}
