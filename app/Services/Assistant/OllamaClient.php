<?php

namespace App\Services\Assistant;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OllamaClient — thin wrapper around the local Ollama chat API.
 * ----------------------------------------------------------------------------
 * Talks to the Ollama service running on this machine (default
 * http://127.0.0.1:11434). Nothing leaves the box.
 *
 * Only one method matters: chat() — send the running message list (+ optional
 * tool definitions) and get the assistant's next turn back.
 */
class OllamaClient
{
    public function __construct(
        protected ?string $baseUrl = null,
        protected int $timeout = 120,
    ) {
        $this->baseUrl = rtrim($baseUrl ?? config('assistant.ollama_url', 'http://127.0.0.1:11434'), '/');
    }

    /**
     * Call /api/chat once and return the assistant 'message' array:
     *   ['role' => 'assistant', 'content' => '...', 'tool_calls' => [...]]
     *
     * @param array       $messages  Full chat history in Ollama format.
     * @param array       $tools     Tool definitions (OpenAI-style function schema).
     * @param string|null $model     Override model; defaults to config.
     * @param array       $options   Extra Ollama options (temperature, etc.).
     */
    public function chat(array $messages, array $tools = [], ?string $model = null, array $options = []): array
    {
        $payload = [
            'model'    => $model ?: config('assistant.model', 'qwen2.5:7b'),
            'messages' => $messages,
            'stream'   => false,
            // Keep the model loaded in GPU memory between requests so only the
            // first call of a session pays the "cold start" load time.
            'keep_alive' => config('assistant.keep_alive', '30m'),
            'options'  => array_merge([
                'temperature' => (float) config('assistant.temperature', 0.3),
            ], $options),
        ];

        if (!empty($tools)) {
            $payload['tools'] = $tools;
        }

        $baseTemp = $payload['options']['temperature'] ?? 0.3;
        $maxAttempts = 3;   // small models occasionally emit a malformed tool call

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            // On a retry, nudge temperature up so the model re-samples differently.
            $payload['options']['temperature'] = $baseTemp + (($attempt - 1) * 0.2);

            try {
                $response = Http::timeout($this->timeout)
                    ->acceptJson()
                    ->post($this->baseUrl . '/api/chat', $payload);
            } catch (\Throwable $e) {
                // Connection problem — Ollama down. Don't retry, fail clearly.
                throw new RuntimeException(
                    "Couldn't reach the local AI engine (Ollama) at {$this->baseUrl}. " .
                    "Is Ollama running? Original error: " . $e->getMessage(),
                    0,
                    $e
                );
            }

            if ($response->successful()) {
                $message = $response->json('message');
                if (is_array($message)) {
                    return [
                        'role'       => $message['role'] ?? 'assistant',
                        'content'    => $message['content'] ?? '',
                        'tool_calls' => $message['tool_calls'] ?? [],
                    ];
                }
                // No message in body — treat as a transient hiccup and retry.
                continue;
            }

            $body = $response->json('error') ?? $response->body();

            // Known TRANSIENT error: the model produced a malformed tool call and
            // Ollama couldn't parse it. Retry with a different sample.
            $isToolParseError = str_contains($body, 'looks like object')
                || str_contains($body, "closing")
                || str_contains($body, 'unexpected');

            if ($isToolParseError && $attempt < $maxAttempts) {
                usleep(200000); // 0.2s breather
                continue;
            }

            // Permanent errors (e.g. model not pulled) — fail immediately.
            throw new RuntimeException(
                "Ollama returned an error: {$body}. " .
                "If it mentions a missing model, run: ollama pull " . $payload['model']
            );
        }

        throw new RuntimeException(
            "The AI model kept producing a malformed tool call. This can happen with the " .
            "smaller model on complex requests — try again, or switch ASSISTANT_MODEL to qwen2.5:7b."
        );
    }

    /** Quick health check used by the controller / tinker. */
    public function isUp(): bool
    {
        try {
            return Http::timeout(3)->get($this->baseUrl . '/api/tags')->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /** List models currently installed in Ollama. */
    public function models(): array
    {
        try {
            return collect(Http::timeout(5)->get($this->baseUrl . '/api/tags')->json('models') ?? [])
                ->pluck('name')->all();
        } catch (\Throwable) {
            return [];
        }
    }
}
