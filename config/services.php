<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
    ],

    // ── Local Voice-Notes AI pipeline (Phase: Voice Notes) ────────────────────
    // 100% local & free. faster-whisper transcribes audio (GPU/CUDA), Ollama
    // runs the LLM that turns the transcript into structured clinical notes.
    // Nothing leaves the Tulip-Dental machine. Defaults below match a fresh
    // Ollama + faster-whisper install; override per-machine in .env.
    'voice' => [
        // Path to the python that has faster-whisper installed. On Laragon this
        // is usually just 'python'; if you used a venv, point to its python.exe.
        'whisper_python'  => env('WHISPER_PYTHON', 'python'),
        // Absolute path to the transcription helper script (added in Phase 2).
        'whisper_script'  => env('WHISPER_SCRIPT', base_path('scripts/voice/transcribe.py')),
        'whisper_model'   => env('WHISPER_MODEL', 'small'),   // tiny|base|small|medium|large-v3
        'whisper_device'  => env('WHISPER_DEVICE', 'cuda'),   // 'cuda' (RTX 3050) or 'cpu'
        'whisper_compute' => env('WHISPER_COMPUTE', 'int8_float16'),
        'language'        => env('WHISPER_LANGUAGE', 'en'),

        // Ollama local LLM (clinical-note extraction).
        'ollama_url'      => env('OLLAMA_URL', 'http://127.0.0.1:11434'),
        'ollama_model'    => env('OLLAMA_MODEL', 'llama3.1:8b'),

        // Where audio is stored — keep on a PRIVATE disk (PHI).
        'disk'            => env('VOICE_DISK', 'local'),
        // Max upload length we accept, in seconds (safety cap).
        'max_seconds'     => (int) env('VOICE_MAX_SECONDS', 1800),
    ],

    // ── Marketing Platform OAuth ──────────────────────────────────────────
    // Add these to your .env when you're ready to go live.
    // Get credentials from: https://developers.facebook.com (for Meta/Instagram)
    // and https://console.cloud.google.com (for Google Business / Analytics)

    'meta' => [
        'app_id'     => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET'),
    ],

    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    ],

];
