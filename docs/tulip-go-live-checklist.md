# Tulip — Go-Live Checklist (internal clinic launch)

A short punch-list to get Tulip ready for your weekend internal launch.

## Environment (do these first)

- [ ] **Open the app via `http://localhost/...` or enable Laragon SSL** (`https://dentfluence.test`). The microphone is blocked by browsers on plain `http://...test`.
- [ ] **Ollama is running** (llama icon in the system tray) — and set it to **start automatically** on boot, so Tulip is never silent mid-clinic.
- [ ] **GPU speed for voice (optional but recommended):** `pip install nvidia-cublas-cu12 nvidia-cudnn-cu12`. Re-run `php artisan tulip:transcribe <file>` — the time should drop ~10×.
- [ ] After any `.env` change: `php artisan config:clear`.

## Kill-switch (your safety control)

- To turn Tulip OFF instantly: set `ASSISTANT_ENABLED=false` in `.env`, then `php artisan config:clear`. The widget disappears and her endpoints return 404.
- Turn back on: `ASSISTANT_ENABLED=true` + `php artisan config:clear`.

## Functional test pass (do these as a staff member would)

- [ ] **Chat:** open Tulip, say "Hi" → she replies.
- [ ] **Find patient:** "find patient <real name>" → correct match.
- [ ] **Patient summary:** "tell me about <real patient>" → age, alerts, balance, last visit.
- [ ] **Balance:** "what does <patient> owe?" → correct figure.
- [ ] **History:** "show <patient>'s recent visits".
- [ ] **Schedule:** "what's on the schedule today?"
- [ ] **Huddle:** "run the daily huddle" → full briefing.
- [ ] **Create task (auto):** "remind me to call <patient> tomorrow" → appears in Tasks (tomorrow's list).
- [ ] **Clinical note (confirm card):** "add a note to <patient> that …" → amber confirm card → tap Confirm → note saved. Try Cancel on another (nothing saved).
- [ ] **Voice:** click mic → speak → stop → transcript fills the box → send.

## Review what she did

- [ ] After a day of use, review the `ai_action_logs` table — it records every read and write Tulip performed (who, what, when, confirmed or not). This is your usage + audit trail, and tells you what staff actually use.

## Known limits (set expectations with staff)

- She runs on a small local model (qwen2.5:3b) — fast, but can occasionally pick a slightly-off tool or embellish wording. The 7B model (`ASSISTANT_MODEL=qwen2.5:7b`) is more precise if needed.
- Clinical/financial actions always require a confirm tap — by design.
- Everything is local: no patient data leaves the clinic machine.

## Not built yet (future)

- Spoken replies (C3), wake-word "Hey Tulip" (Phase E), book appointments (D3), mobile companion.
