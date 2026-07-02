<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * tulip:pull — download an Ollama model through the local Ollama server.
 * ----------------------------------------------------------------------------
 * Sidesteps the `ollama` CLI entirely (handy when it isn't on PATH). Talks to
 * the same server Tulip uses (http://127.0.0.1:11434) and streams progress.
 *
 *   php artisan tulip:pull qwen2.5:7b
 *   php artisan tulip:pull llama3.1:8b
 */
class TulipPull extends Command
{
    protected $signature = 'tulip:pull {model : Model name to download, e.g. qwen2.5:7b}';
    protected $description = 'Download an Ollama model via the local server (no CLI needed).';

    public function handle(): int
    {
        $model = $this->argument('model');
        $url   = rtrim(config('assistant.ollama_url', 'http://127.0.0.1:11434'), '/') . '/api/pull';

        $this->info("Pulling '{$model}' via the local Ollama server.");
        $this->line("This downloads a few GB — leave it running. Ctrl+C to abort.");
        $this->newLine();

        try {
            // timeout(0) = no limit; stream the progress back.
            $response = Http::withOptions(['stream' => true])->timeout(0)
                ->post($url, ['name' => $model, 'stream' => true]);
        } catch (\Throwable $e) {
            $this->error("Couldn't reach Ollama at {$url}. Is the Ollama app running?");
            $this->line($e->getMessage());
            return self::FAILURE;
        }

        if ($response->failed()) {
            $this->error('Ollama error: ' . $response->body());
            return self::FAILURE;
        }

        $body       = $response->toPsrResponse()->getBody();
        $buffer     = '';
        $lastStatus = '';

        while (!$body->eof()) {
            $buffer .= $body->read(8192);

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line   = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);
                if ($line === '') continue;

                $data = json_decode($line, true);
                if (!is_array($data)) continue;

                if (!empty($data['error'])) {
                    $this->newLine();
                    $this->error('Pull failed: ' . $data['error']);
                    return self::FAILURE;
                }

                $status = $data['status'] ?? '';

                if (isset($data['total'], $data['completed']) && $data['total'] > 0) {
                    $pct  = (int) round($data['completed'] / $data['total'] * 100);
                    $gb   = number_format($data['total'] / 1073741824, 1);
                    $this->output->write("\r  {$status}: {$pct}%  of {$gb} GB        ");
                } elseif ($status !== $lastStatus) {
                    $this->newLine();
                    $this->output->write("  {$status}");
                    $lastStatus = $status;
                }
            }
        }

        $this->newLine(2);
        $this->info("Done — '{$model}' is installed and ready.");
        return self::SUCCESS;
    }
}
