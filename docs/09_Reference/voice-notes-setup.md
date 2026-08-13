# Voice Notes — Local AI Setup (Tulip-Dental)

This module runs **100% locally**. No API keys, no cloud, no per-use cost.
Patient audio never leaves this machine.

Two local engines do the work:

- **faster-whisper** — turns the recording into text (runs on your RTX 3050 GPU)
- **Ollama (Llama 3.1 8B)** — turns that text into structured clinical notes

Run the steps below **once**. After that the app just talks to them automatically.

---

## 1. Run the Laravel migration (creates the `voice_notes` table)

```bash
php artisan migrate
```

---

## 2. Install Ollama (the local LLM)

1. Download Ollama for Windows: https://ollama.com/download
2. Install it (it runs as a background service on `http://127.0.0.1:11434`).
3. Pull **both** assistant models (we A/B compare them) and test:

```bash
ollama pull qwen2.5:7b
ollama pull llama3.1:8b
ollama run qwen2.5:7b "Say OK if you can read this."
```

If it replies, Ollama is ready. Each model is ~4.5–4.7 GB and fits your 6 GB GPU
(they load one at a time). The assistant ("Tulip") uses `qwen2.5:7b` by default —
switch anytime via `ASSISTANT_MODEL` in `.env`.

---

## 3. Install faster-whisper (the transcriber)

```bash
pip install faster-whisper
```

### GPU acceleration (recommended — uses your RTX 3050)

faster-whisper needs NVIDIA's cuBLAS + cuDNN libraries for GPU mode:

```bash
pip install nvidia-cublas-cu12 nvidia-cudnn-cu12
```

Quick test that the GPU path works (downloads the `small` model on first run):

```bash
python -c "from faster_whisper import WhisperModel; WhisperModel('small', device='cuda', compute_type='int8_float16'); print('GPU OK')"
```

If you see `GPU OK`, you're set. If it errors about CUDA/cuDNN, fall back to CPU
by setting `WHISPER_DEVICE=cpu` in `.env` (still works, just slower).

> **Note:** staff can dictate in any language (Hindi/Marathi/English), so when we
> build the voice phase we'll use the multilingual `medium` model
> (`WHISPER_MODEL=medium`). The assistant still *replies* in English.

---

## 4. Confirm the .env settings (already added)

```
WHISPER_PYTHON=python
WHISPER_MODEL=small
WHISPER_DEVICE=cuda
WHISPER_COMPUTE=int8_float16
WHISPER_LANGUAGE=en
OLLAMA_URL=http://127.0.0.1:11434
OLLAMA_MODEL=llama3.1:8b
VOICE_DISK=local
VOICE_MAX_SECONDS=1800
```

After editing `.env`, refresh config:

```bash
php artisan config:clear
```

---

## What's built so far (Phase 1)

- `voice_notes` table — migration `2026_06_22_700001_create_voice_notes_table.php`
- `VoiceNote` model (polymorphic `noteable`, patient link, status state machine)
- `voiceNotes()` relation added to Consultation, TreatmentVisit, Patient
- `config/services.php` → `voice` block + `.env` keys

## Next phases (not built yet)

- **Phase 2** — `transcribe.py` script + service classes (Whisper + Ollama) + controller + routes
- **Phase 3** — the in-browser recorder widget (record / stop / upload / review)
- **Phase 4** — drop the widget into consultation, treatment-visit, and patient screens
