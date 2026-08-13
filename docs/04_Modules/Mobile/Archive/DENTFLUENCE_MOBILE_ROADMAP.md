# Dentfluence — Backend-to-Mobile Roadmap

**From an API-first Laravel backend to a polished, Play-Store-ready Android app (with Tulip AI and a future iOS app riding on the same backend).**

Last updated: 25 June 2026

---

## The one idea everything is built on

**One brain, many faces.**

All business rules (book an appointment, log a call, take a payment, who-can-do-what) live **once**, on the server, inside a *Service Layer*. The web app, the Android app, the future iOS app, and Tulip AI are all just *faces* that ask the same brain to do things through one shared API.

```
Web App   Mobile App   Tulip AI        <- faces (no rules of their own)
   \           |          /
        /api/v1  (one door)            <- the nerves
            |
     Service Layer (the brain)         <- every rule lives here, once
            |
       MySQL Database                  <- one source of truth
```

Because of this, **every phase below benefits the web app, the mobile app, and Tulip at the same time.** You never build a feature three times.

---

## Decisions locked in

| Decision | Choice | Why |
|---|---|---|
| Mobile framework | **Flutter** | Smooth native UI on cheap Android phones, one codebase for Android + future iOS, scales as a real product |
| Auth | **Laravel Sanctum** (recommended) | Built-in mobile token auth, less custom code to break than hand-rolled JWT |
| Web frontend | **Keep Blade for now** | The API serves mobile + Tulip immediately; web can move to Next.js later with zero backend change |
| Distribution | **Internal first → Play Store later** | Same Flutter app both times; only the distribution channel changes |
| Call-log feature | **Internal build = auto-read call log; Play-Store build = in-app "click to call"** | Google blocks the call-log permission for non-dialer apps, so the public product logs calls by owning the dial itself |

> **Important consequence:** auto-reading the Android system call log can only ship in the **internal** build. The public Play-Store app must log calls through in-app "click to call" instead. Design the PRM so call-logging is *fed two ways*, not hard-wired to one.

---

## The software you'll use

**Backend (mostly already on your machine via Laragon)**

- Laragon — local server (Apache/Nginx + MySQL + PHP) — *already installed*
- Laravel + Composer — the app framework — *already installed*
- MySQL — database — *already installed*
- Laravel Sanctum — API authentication (one composer package)
- Git + GitHub — version control & backups
- **Postman** (or Insomnia) — test API endpoints **before any app exists**
- **Scribe** or **Swagger/OpenAPI** — auto-generate the API documentation Flutter is built from

**Mobile (new — installed during Phase 1)**

- **Flutter SDK** + Dart — builds the Android/iOS app
- **Android Studio** — gives you the Android SDK + the on-screen emulator
- **VS Code** — lighter editor for day-to-day Flutter coding (optional, many prefer it)
- An **Android phone** + USB cable — to test on a real device
- **Figma** (free tier) — optional, to design screens before building them

**AI / notifications / launch (later phases)**

- **Ollama** + local models — Tulip's brain — *already in your stack*
- **Firebase Cloud Messaging (FCM)** — free push notifications
- **Google Play Console** — one-time US$25, for internal testing track + public launch

---

## How you'll *see* the app at each stage

You asked to visualise progress at every phase. Here's literally how you'll look at it:

| Phase | How you see it |
|---|---|
| 0 | **Postman** — you fire requests and watch real JSON come back. No app yet, but the brain is provably working. |
| 1 | **Android emulator** in Android Studio — the first real screens (login + patient list) appear on a simulated phone on your PC. |
| 2–3 | **Your real Android phone** over USB — install the in-progress app and tap through schedule, calls, payments live. |
| 4 | Same phone — now talk to Tulip, snap a bill, get a push notification. |
| 5 | **Play Console internal testing track** — install it like a real Play Store app, then flip to public listing. |

---

## The phases

### Phase 0 — Foundations (backend scaffolding)

**Goal:** build the shared plumbing every module will reuse, so we never retrofit it later.

**What gets built (backend only):**

- The `/api/v1` route structure (versioned API).
- **Standard response shape** used everywhere: `{ success, message, data }` and `{ success:false, message, errors }`.
- A **base API controller** + **pagination helper** (every list supports `page`, `limit`, `search`, filters, sorting).
- **Sanctum authentication** wiring.
- **Audit-log** system (records who/when/what/old value/new value/device type) as a reusable trait.
- **Role-Based Access Control** middleware (Owner, Dentist, Associate, Receptionist, Assistant).
- Security basics: CORS config, rate limiting, secure headers.

**Software introduced:** Sanctum, Postman, Git/GitHub.

**How you visualise it:** Postman — log in, get a token, hit a protected test endpoint, see the standard JSON envelope and an audit row appear in the database.

**Done when:** you can authenticate and call a sample endpoint that returns the standard format, paginates, checks a role, and writes an audit log — all proven in Postman. **The existing web app still works untouched.**

---

### Phase 1 — First real module + first real screens (Auth + Users/Roles + Patients)

**Goal:** prove the whole pattern end-to-end on one module, and stand up the Flutter app for the first time.

**Backend:**

- Auth API: login, logout, refresh token, password reset.
- Users & Roles API.
- **Patients** module refactored so all its logic lives in a `PatientService` — and **both** the existing Blade pages **and** the new API call that same service. (This is the reference pattern every later module copies.)

**App (Flutter — first build):**

- Login screen.
- Patient list (search + pagination).
- Patient detail screen.

**Software introduced:** Flutter SDK, Android Studio, VS Code, Android emulator.

**How you visualise it:** the app boots on the emulator — you log in and browse real patients pulled from your real database.

**Done when:** you can log in on the phone and view/search patients, and the web Patients page still works because both share `PatientService`.

---

### Phase 2 — Core clinical workflow (Appointments, Consultations, Treatment Plans, Clinical Notes)

**Goal:** make the app useful for an actual clinic day.

**Backend:** each module refactored into a service + clean API, following the Phase 1 pattern.

**App:**

- Today's schedule.
- Book / reschedule an appointment (kept dead-simple — few taps).
- Patient timeline (consultations, notes, treatment plan).

**How you visualise it:** install on your **real phone** over USB and run a mock clinic day.

**Done when:** a receptionist could run the day's schedule from the phone, and everything mirrors the web instantly.

---

### Phase 3 — Money + PRM + Call logging (Billing, Payments, Membership, PRM)

**Goal:** payments and the call manager you specifically want.

**Backend:**

- Billing, Payments, Membership APIs.
- **`CallLogService`** — receives a call record, **normalises the phone number**, **de-duplicates**, **matches it to a patient**, and files it on the PRM timeline. Unmatched numbers go to an "unknown caller / possible lead" bucket.
- `POST /api/v1/calls/sync` endpoint.

**App:**

- Payment capture + invoice view.
- PRM call manager.
- **Click-to-call** (the Play-Store-safe way to log calls) + the internal-only **auto call-log sync**.

**How you visualise it:** tap a patient's number to call from inside the app and watch the call appear on their timeline automatically.

**Done when:** calls and payments flow into the PRM with correct patient matching.

---

### Phase 4 — Tulip AI + Notifications + Files

**Goal:** the mobile-first AI front door and the things a phone does best.

**Backend:**

- Notification service (in-app now, push-ready).
- File upload APIs (patient photos, x-rays, clinical images, documents) — return **URLs + metadata**, never raw binary.
- Tulip endpoints that let the AI safely use the same services any staff member can.

**App:**

- **Tulip as the mobile front door** — voice-first: "show my day", "log this call", "add this patient" (snap the form).
- Camera capture (bills, intake forms, x-rays).
- **Push notifications** via Firebase Cloud Messaging.
- Confirm-card before anything clinical or financial.

**Software introduced:** Firebase Cloud Messaging.

**How you visualise it:** talk to Tulip on your phone, snap a bill and watch the expense form pre-fill, receive a push.

**Done when:** Tulip can answer questions and (with confirmation) take actions through the same brain.

---

### Phase 5 — Polish, harden, and launch

**Goal:** turn the working app into a product real clinics trust.

**Backend:**

- Performance pass (return only needed fields, lazy loading, trim joins for mobile networks).
- Security hardening (rate limits, request validation, SQL-injection/XSS protection, secure headers, HTTPS-ready).
- **Offline-ready data shape** (every record carries created/updated/deleted timestamps + a version number — architecture only, no sync yet).
- **Auto-generated API documentation** (Scribe/Swagger) so Flutter/iOS/AI work can proceed from docs alone.

**App:**

- Visual polish, branding, smooth transitions, empty/error states.
- Biometric login (fingerprint/face) on mobile.
- App icon, splash, store screenshots.

**Software introduced:** Google Play Console, Scribe/Swagger.

**How you visualise it:** install via Play's **internal testing track** like a real download — then flip the same app to a **public Play Store** listing.

**Done when:** clinic staff are using the internal build daily; the public listing is submitted.

---

## What comes "for free" after this

Because every module now lives behind the one brain and one API:

- The **iOS app** is mostly a second Flutter face on the *same* backend.
- The **AI Command Center** reads KPIs, revenue, appointments, inventory, marketing, reports — all through the same secured services, never through screens.
- Moving the **web frontend to Next.js** later needs **zero** backend changes.

---

## The non-negotiable rule throughout

**Do not break the running web app.** Every module is refactored *incrementally* — the Blade page and the new API call the same service, so the website keeps working the whole way. We convert one module, confirm the web still works, then move on.
