# Tulip Wake Word — "Hey Tulip" (on-device, private)

Detection runs **100% in the browser** via Porcupine — no audio leaves the
machine. This is a one-time setup. Until you finish it, wake word stays off and
push-to-talk (the mic button) still works.

## 1. Get a free Picovoice access key

1. Sign up at **https://console.picovoice.ai** (free tier is fine).
2. Copy your **AccessKey** from the dashboard.

## 2. Generate the "Hey Tulip" keyword file

1. In the Picovoice Console → **Porcupine** → **Create Wake Word**.
2. Phrase: **Hey Tulip**  ·  Language: **English**  ·  Platform: **Web (WASM)**.
3. Train (instant) and **download** the `.ppn` file
   (e.g. `Hey-Tulip_en_wasm_v3_0_0.ppn`).

## 3. Get the Porcupine English model file

Download `porcupine_params.pv` from Picovoice's GitHub:
**https://github.com/Picovoice/porcupine/raw/master/lib/common/porcupine_params.pv**

## 4. Put both files in the project

Create the folder `public/wake/` and place the two files there, renamed exactly:

```
public/wake/Hey-Tulip.ppn
public/wake/porcupine_params.pv
```

(They're served at `/wake/Hey-Tulip.ppn` and `/wake/porcupine_params.pv`, which
is what the config points to.)

## 5. Turn it on in .env

```
PICOVOICE_ACCESS_KEY=your_access_key_here
ASSISTANT_WAKE_ENABLED=true
```

Then:

```
php artisan config:clear
```

## 6. Use it

- Open the app on a **secure origin** (localhost / `php artisan serve` / https) —
  same requirement as the mic.
- Keep a Dentfluence tab open and **allow microphone** access.
- Say **"Hey Tulip"** → the panel opens, records for ~5 seconds, then auto-sends
  what you said. If voice replies are on, she answers aloud — fully hands-free.

## Notes & tuning

- The mic is continuously listening *on-device* for the wake word while the tab
  is open. Nothing is sent anywhere until you speak a command after "Hey Tulip".
- Adjust the listen window with `ASSISTANT_WAKE_LISTEN_SECS` (default 5).
- If the browser console shows a version error, the `.ppn` engine version must
  match the Porcupine library — tell me and I'll bump the CDN version in the widget.
- To disable: `ASSISTANT_WAKE_ENABLED=false` + `php artisan config:clear`.
