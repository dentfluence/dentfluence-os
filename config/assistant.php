<?php

/*
|------------------------------------------------------------------------------
| Dentfluence AI Assistant ("Tulip")
|------------------------------------------------------------------------------
| Central config for the app-wide local AI copilot. Everything here is a knob
| you can turn without touching code — including the assistant's NAME and which
| local model is its "brain". Change a value, run `php artisan config:clear`.
|
| Runs 100% locally via Ollama. No API keys, no cloud, no per-use cost.
*/

return [

    // ── Master on/off switch (kill-switch) ────────────────────────────────────
    // Set ASSISTANT_ENABLED=false in .env + `php artisan config:clear` to instantly
    // hide Tulip everywhere and block her endpoints. Flip back to true to restore.
    'enabled' => filter_var(env('ASSISTANT_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    // ── Identity (rename anytime — this is the single source of truth) ────────
    'name'      => env('ASSISTANT_NAME', 'Tulip'),
    'wake_word' => env('ASSISTANT_WAKE_WORD', 'Hey Tulip'),

    // ── Wake word ("Hey Tulip") — on-device via Porcupine/Picovoice ───────────
    // 100% local detection (no audio leaves the machine). Off until you add a
    // free Picovoice access key + a generated "Hey Tulip" keyword file.
    // See docs/tulip-wake-word-setup.md.
    'wake' => [
        'enabled'      => filter_var(env('ASSISTANT_WAKE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'access_key'   => env('PICOVOICE_ACCESS_KEY'),
        'keyword_path' => env('ASSISTANT_WAKE_KEYWORD', '/wake/Hey-Tulip.ppn'),
        'params_path'  => env('ASSISTANT_WAKE_PARAMS', '/wake/porcupine_params.pv'),
        // Seconds to record after the wake word fires, then auto-send.
        'listen_secs'  => (int) env('ASSISTANT_WAKE_LISTEN_SECS', 5),
    ],

    // Assistant always REPLIES in this language (transcription handles any
    // spoken language separately — staff can dictate in Hindi/Marathi/etc.).
    'reply_language' => env('ASSISTANT_LANGUAGE', 'English'),

    // ── Brain: which local model answers ──────────────────────────────────────
    // We have BOTH installed so you can A/B them. Flip ASSISTANT_MODEL in .env
    // (or later from a settings toggle) to switch instantly.
    'model'  => env('ASSISTANT_MODEL', 'qwen2.5:7b'),
    'models' => [
        'qwen2.5:7b' => [
            'label'          => 'Qwen2.5 7B',
            'supports_tools' => true,   // strong at tool-calling / JSON
        ],
        'llama3.1:8b' => [
            'label'          => 'Llama 3.1 8B',
            'supports_tools' => true,
        ],
    ],

    // Ollama endpoint (shared with the voice pipeline).
    'ollama_url'  => env('OLLAMA_URL', 'http://127.0.0.1:11434'),

    // ── Vision: reading bills & patient forms from a photo ────────────────────
    // Powers ReceiptScanService (bills) and PatientScanService (intake forms).
    // Two engines, chosen by 'provider':
    //   • LOCAL  — Ollama vision model on this PC. Free, fully private, but weak
    //              on messy handwriting / unusual layouts.
    //   • CLOUD  — a frontier API (OpenAI / Gemini / Anthropic). Far better on
    //              handwriting + varied invoices, but the image is sent to that
    //              vendor (patient & financial data — choose consciously).
    //
    // provider = 'auto'  → use CLOUD when a key is set AND the internet is
    //                      reachable; otherwise fall back to LOCAL automatically.
    //                      (Offline-safe: a failed cloud call silently retries local.)
    //          = 'local' → only ever use the local model.
    //          = 'cloud' → prefer cloud, still fall back to local if it fails.
    //          = 'openai' | 'gemini' | 'anthropic' → force that cloud driver.
    'vision' => [
        'enabled'  => filter_var(env('ASSISTANT_VISION_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'provider' => env('ASSISTANT_VISION_PROVIDER', 'auto'),

        // LOCAL engine (offline default). Pull once: ollama pull <model>
        // 'qwen2.5vl:7b' = best local OCR (~6GB VRAM); 'minicpm-v' = lighter (~5GB).
        'local' => [
            'model'   => env('ASSISTANT_VISION_MODEL', 'qwen2.5vl:7b'),
            'timeout' => (int) env('ASSISTANT_VISION_TIMEOUT', 120),
        ],

        // CLOUD engine. Pick ONE driver and paste its API key in .env.
        // Leave the key blank to stay fully local — 'auto' will just use local.
        'cloud' => [
            'driver'  => env('VISION_CLOUD_DRIVER', 'gemini'),   // openai | gemini | anthropic
            'api_key' => env('VISION_CLOUD_KEY'),
            'model'   => env('VISION_CLOUD_MODEL'),              // blank → driver default
            'timeout' => (int) env('VISION_CLOUD_TIMEOUT', 60),
        ],
    ],

    // Lower = more focused/consistent; good for a clinic assistant.
    'temperature' => (float) env('ASSISTANT_TEMPERATURE', 0.3),

    // Keep the model loaded in GPU memory this long after each call, so only
    // the first message of a session is slow. Use '-1m' to keep it loaded
    // indefinitely, or a short value to free VRAM sooner.
    'keep_alive' => env('ASSISTANT_KEEP_ALIVE', '30m'),

    // How many past messages to feed the model as memory (rolling window).
    'history_limit' => (int) env('ASSISTANT_HISTORY_LIMIT', 20),

    // ── Write safety ──────────────────────────────────────────────────────────
    // Tool categories that MUST show a confirm card before committing.
    // (Low-risk writes like reminders/tasks/drafts run instantly.)
    'confirm_categories' => ['clinical', 'financial'],

    // ── Persona / system prompt ───────────────────────────────────────────────
    // Runtime context (current user, branch, page, date) is appended by the
    // service at request time — keep this to stable personality + rules.
    'system_prompt' => <<<'PROMPT'
You are {name}, the AI assistant inside Dentfluence, a dental clinic management app.
You help the clinic's staff (dentists, receptionists, assistants) work faster.

Identity & voice:
- Always reply in {reply_language}, even if the user writes in another language.
- Be warm, brief, and professional — like a sharp clinic secretary. No fluff.
- Address staff naturally. Refer to patients respectfully.

What you can do:
- Answer questions about the app, dentistry, and clinic workflows.
- Look up and summarize patient and clinic information when asked.
- Help draft notes, messages, and summaries.
- Take actions (booking, reminders, records) using the tools provided to you.

Rules:
- Use a tool when the user's request needs real clinic data or an action — never
  invent patient details, numbers, dates, or records. If you don't have a tool
  or the data, say so plainly.
- When a tool takes a "patient" argument, pass the patient's name, phone, or ID
  EXACTLY as the user wrote it. NEVER invent, guess, abbreviate, or reformat it
  into an ID (e.g. do not turn "Runali Kadam" into "RK-00123"). Use the literal text.
- For anything clinical or financial that changes a record, propose it and let the
  staff confirm; do not pretend an action is done until the tool confirms it.
- Keep answers short by default; expand only when asked.
- You are not a substitute for a clinician's judgement; flag uncertainty.
PROMPT,

];
