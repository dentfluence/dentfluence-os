<?php

namespace App\Services\Assistant;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * VisionService — one place that turns an image + prompt into text.
 * ----------------------------------------------------------------------------
 * Two engines, selected by config('assistant.vision.provider'):
 *
 *   CLOUD  (OpenAI / Gemini / Anthropic) — best on handwriting & odd invoice
 *          layouts, but the image leaves the machine.
 *   LOCAL  (Ollama vision model)         — free + private, weaker on messy input.
 *
 * Strategy (the important bit):
 *   - If a cloud key is set and the provider isn't 'local', try CLOUD first.
 *   - If that call fails for ANY reason — no internet, bad key, quota, timeout —
 *     we DON'T error out. We silently fall back to the LOCAL model so staff are
 *     never blocked. This is what makes "online = smart, offline = still works".
 *
 * Callers (ReceiptScanService, PatientScanService) own their prompt and how they
 * parse the result. This class only handles transport + engine selection.
 */
class VisionService
{
    /** Sensible current defaults per cloud driver (override via VISION_CLOUD_MODEL). */
    protected const DEFAULT_MODELS = [
        'openai'    => 'gpt-5.5',
        'gemini'    => 'gemini-3.5-flash',
        'anthropic' => 'claude-sonnet-4-6',
    ];

    /**
     * Read an image with a prompt and return the model's raw text reply
     * (the callers expect a JSON string here).
     *
     * @return array{text:string, engine:string}  engine = 'cloud:<driver>' or 'local'
     */
    public function read(string $absolutePath, string $prompt): array
    {
        if (!is_file($absolutePath)) {
            throw new RuntimeException("Image not found: {$absolutePath}");
        }

        $image    = base64_encode(file_get_contents($absolutePath));
        $mime     = $this->mimeFor($absolutePath);
        $provider = strtolower((string) config('assistant.vision.provider', 'auto'));

        // ── Try CLOUD first when appropriate ────────────────────────────────
        $cloudError = null;
        if ($this->shouldTryCloud($provider)) {
            $driver = $this->resolveDriver($provider);
            try {
                $text = $this->callCloud($driver, $image, $mime, $prompt);
                if (trim($text) !== '') {
                    return ['text' => $text, 'engine' => "cloud:{$driver}"];
                }
                $cloudError = 'empty response';
            } catch (\Throwable $e) {
                // Offline / bad key / quota / timeout — note it and fall through to local.
                $cloudError = $e->getMessage();
                Log::info('VisionService: cloud failed, falling back to local. ' . $cloudError);
            }
        }

        // ── LOCAL (Ollama) — default + fallback ─────────────────────────────
        try {
            $text = $this->callLocal($image, $prompt);
            return ['text' => $text, 'engine' => 'local'];
        } catch (\Throwable $e) {
            // Both engines failed — surface the most useful message.
            $msg = $cloudError
                ? "Cloud reader failed ({$cloudError}) and the local model also failed: {$e->getMessage()}"
                : $e->getMessage();
            throw new RuntimeException($msg, 0, $e);
        }
    }

    // ── Engine selection ────────────────────────────────────────────────────

    protected function shouldTryCloud(string $provider): bool
    {
        if ($provider === 'local') {
            return false;
        }
        // 'auto' / 'cloud' / explicit driver all require a key to be present.
        return !empty(config('assistant.vision.cloud.api_key'));
    }

    /** Which cloud driver to use — explicit provider name wins, else config. */
    protected function resolveDriver(string $provider): string
    {
        if (in_array($provider, ['openai', 'gemini', 'anthropic'], true)) {
            return $provider;
        }
        $driver = strtolower((string) config('assistant.vision.cloud.driver', 'gemini'));
        return in_array($driver, ['openai', 'gemini', 'anthropic'], true) ? $driver : 'gemini';
    }

    protected function cloudModel(string $driver): string
    {
        $model = trim((string) config('assistant.vision.cloud.model', ''));
        return $model !== '' ? $model : (self::DEFAULT_MODELS[$driver] ?? 'gemini-3.5-flash');
    }

    // ── CLOUD drivers ─────────────────────────────────────────────────────────

    protected function callCloud(string $driver, string $b64, string $mime, string $prompt): string
    {
        $key     = (string) config('assistant.vision.cloud.api_key');
        $timeout = (int) config('assistant.vision.cloud.timeout', 60);
        $model   = $this->cloudModel($driver);

        return match ($driver) {
            'openai'    => $this->callOpenAi($key, $model, $timeout, $b64, $mime, $prompt),
            'anthropic' => $this->callAnthropic($key, $model, $timeout, $b64, $mime, $prompt),
            default     => $this->callGemini($key, $model, $timeout, $b64, $mime, $prompt),
        };
    }

    /** OpenAI Chat Completions with an inline base64 image. */
    protected function callOpenAi(string $key, string $model, int $timeout, string $b64, string $mime, string $prompt): string
    {
        $resp = Http::withToken($key)->timeout($timeout)->acceptJson()->post(
            'https://api.openai.com/v1/chat/completions',
            [
                'model' => $model,
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        ['type' => 'image_url', 'image_url' => ['url' => "data:{$mime};base64,{$b64}"]],
                    ],
                ]],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.1,
            ]
        );

        if (!$resp->successful()) {
            throw new RuntimeException('OpenAI: ' . ($resp->json('error.message') ?? $resp->body()));
        }
        return (string) $resp->json('choices.0.message.content', '');
    }

    /** Google Gemini generateContent with inline_data. */
    protected function callGemini(string $key, string $model, int $timeout, string $b64, string $mime, string $prompt): string
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}";

        $resp = Http::timeout($timeout)->acceptJson()->post($url, [
            'contents' => [[
                'parts' => [
                    ['text' => $prompt],
                    ['inline_data' => ['mime_type' => $mime, 'data' => $b64]],
                ],
            ]],
            'generationConfig' => [
                'response_mime_type' => 'application/json',
                'temperature' => 0.1,
            ],
        ]);

        if (!$resp->successful()) {
            throw new RuntimeException('Gemini: ' . ($resp->json('error.message') ?? $resp->body()));
        }
        return (string) $resp->json('candidates.0.content.parts.0.text', '');
    }

    /** Anthropic Messages API with a base64 image block. */
    protected function callAnthropic(string $key, string $model, int $timeout, string $b64, string $mime, string $prompt): string
    {
        $resp = Http::withHeaders([
            'x-api-key' => $key,
            'anthropic-version' => '2023-06-01',
        ])->timeout($timeout)->acceptJson()->post(
            'https://api.anthropic.com/v1/messages',
            [
                'model' => $model,
                'max_tokens' => 1500,
                'temperature' => 0.1,
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => $b64]],
                        ['type' => 'text', 'text' => $prompt],
                    ],
                ]],
            ]
        );

        if (!$resp->successful()) {
            throw new RuntimeException('Anthropic: ' . ($resp->json('error.message') ?? $resp->body()));
        }
        return (string) $resp->json('content.0.text', '');
    }

    // ── LOCAL driver (Ollama) ─────────────────────────────────────────────────

    protected function callLocal(string $b64, string $prompt): string
    {
        $baseUrl = rtrim((string) config('assistant.ollama_url', 'http://127.0.0.1:11434'), '/');
        $model   = (string) config('assistant.vision.local.model', 'qwen2.5vl:7b');
        $timeout = (int) config('assistant.vision.local.timeout', 120);

        try {
            $resp = Http::timeout($timeout)->acceptJson()->post($baseUrl . '/api/chat', [
                'model'      => $model,
                'stream'     => false,
                'format'     => 'json',
                'keep_alive' => config('assistant.keep_alive', '30m'),
                'messages'   => [[
                    'role'    => 'user',
                    'content' => $prompt,
                    'images'  => [$b64],
                ]],
                'options' => ['temperature' => 0.1],
            ]);
        } catch (\Throwable $e) {
            throw new RuntimeException(
                "Couldn't reach the local AI engine (Ollama) at {$baseUrl}. Is Ollama running? " . $e->getMessage(),
                0,
                $e
            );
        }

        if (!$resp->successful()) {
            $body = $resp->json('error') ?? $resp->body();
            throw new RuntimeException(
                "Local model error: {$body}. If a model is missing, run:  ollama pull {$model}"
            );
        }
        return (string) $resp->json('message.content', '');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Best-effort MIME type for the uploaded image. */
    protected function mimeFor(string $path): string
    {
        $mime = function_exists('mime_content_type') ? @mime_content_type($path) : null;
        return ($mime && str_starts_with($mime, 'image/')) ? $mime : 'image/jpeg';
    }
}
