# Clinical Library — MODULE LOCKED

Locked: 13 September 2026
Scope: Dentfluence OS (web) + Dentfluence mobile (Flutter)

This module is closed. It is not open for feature work. Anything below marked
**INVARIANT** is load-bearing — it was broken once already and cost real debugging
time. Do not "clean it up".

---

## 1. What is in the module

**Web**
- `app/Services/ClinicalLibrary/ClinicalFileUploadService.php` — the single upload choke point.
  Every surface (patient profile, library, lab, mobile) goes through `store()`. There is no
  second upload path, and no second one may be added.
- `app/Services/ClinicalLibrary/ClinicalLibrarySearchService.php` — the single query.
  Web and mobile both call it. Filters narrow; they never widen.
- `app/Http/Controllers/Api/V1/ClinicalLibraryController.php` — `options`, `search`,
  `showcase`, `raw`. Holds no vocabulary and no query of its own.
- `app/Http/Controllers/Api/V1/PatientProfileController.php` — `documents`,
  `storeDocument`, `storeClinicalFile`.
- `resources/views/components/tooth-chart.blade.php` — the only tooth chart. Reuse it.
- `resources/views/clinical-library/partials/file-viewer.blade.php` — clinical context editor.
- `resources/views/patients/partials/documents-upload-modal.blade.php`.
- `docker/php/php.ini`, `docker/nginx/default.conf` — the upload gates.

**Mobile**
- `lib/services/library_options.dart`, `lib/ui/tooth_picker.dart`,
  `lib/ui/clinical_context_sheet.dart`, `lib/ui/clinical_file_widgets.dart`
- `lib/screens/clinical_library_screen.dart`, `case_showcase_screen.dart`,
  `library_upload_screen.dart`
- `lib/services/api_client.dart` — library methods and `imageHeaders`.

---

## 2. Invariants

**INVARIANT 1 — validation uses `extensions:`, never `mimes:`.**
`mimes:` sniffs file content. STL and DICOM have no registered type, get guessed as
`.bin`, and are rejected silently. Every STL upload was broken from P1 until P5 found it.

**INVARIANT 2 — `detectFileType()` checks extension BEFORE MIME.**
A DICOM file can report `image/*`. Checked in MIME-first order it becomes a photo, gets
thumbnailed, and the CBCT type is lost. Order: `stl` → `dcm` → MIME.

**INVARIANT 3 — `store()` refuses to run without `uploaded_by`.**
`uploaded_by` is NOT NULL. Console commands, queued jobs and imports have no
`Auth::id()`. Without the guard the failure is a raw SQLSTATE 1364 with no clue in it.

**INVARIANT 4 — patient name is never a watermark option.**
Not in the web watermark, not in the mobile one. It is burned into pixels and cannot be
undone. DPDP exposure with no recovery path.

**INVARIANT 5 — showcase `present` mode is anonymous and double-gated.**
Requires `consent_status = given` AND `marketing_status = approved`, and strips
`patient_id`, `patient_name` and `notes`. Both gates, every time.

**INVARIANT 6 — the API refuses with `HttpResponseException`, not `abort()`.**
`bootstrap/app.php` does not handle `HttpException` on this API, so `abort(403)` renders
as a 500. Use the `refuse()` helper.

**INVARIANT 7 — upload size caps are open at all five gates.**
nginx `client_max_body_size` → PHP `post_max_size` → PHP `upload_max_filesize` →
PHP `max_execution_time` → nginx `fastcgi_read_timeout`. Tightening any one of them
re-introduces a silent failure at a different size than the others. `php.ini` and
`nginx.conf` are baked into the Docker image — changing them needs `./deploy.sh`,
a `git pull` alone does nothing.

**INVARIANT 8 — mobile holds no vocabulary.**
Treatments, stages, file types, statuses, teeth and the upload allowlist all come from
`GET /clinical-library/options`. The app never falls back to a guessed list.

---

## 3. The guard

63 tests. They are the lock, not this document.

| Suite | Tests | Guards |
|---|---|---|
| `ClinicalFileVaultTest` | 17 | upload path, extensions, type detection, `uploaded_by` |
| `ClinicalLibrarySearchTest` | 15 | filters narrow, multi-tooth `FIND_IN_SET` matching |
| `MobileLibraryApiTest` | 13 | options, search, showcase gates, branch scope, `raw` |
| `SecureMediaAccessTest` | 7 | token vs session auth, cross-branch refusal |
| `WatermarkRulesTest` | 11 | watermark rules, no patient name |

`php artisan test --filter="ClinicalFile|ClinicalLibrary|MobileLibrary|SecureMedia|Watermark"`

Red here means the lock is broken. Fix the code, not the test.

---

## 4. Known open items — accepted, not bugs

- **Web search is not branch-scoped; mobile is.** Deliberate. Product decision pending.
  `branch_id` on the search service is optional; web does not pass it.
- **No chunking or resume on upload.** Felt above ~50 MB on a weak connection. A failed
  upload restarts from zero.
- **Storage is uncapped and unwatched** on an 80 GB VPS. Needs a disk alert.
- **`google-services.json` missing.** The Google Services Gradle plugin is commented out in
  `android/settings.gradle.kts` and `android/app/build.gradle.kts`. Package is
  `in.dentfluence.app` (the `applicationId`, not the namespace). Uncomment only after the
  file exists.
- **Mobile repo has no git remote.** ~2,900 lines live on one disk.
  `PUSH_V1_MOBILE.bat` must never be re-run — it does `git init` and moves `.git`.

---

## 5. To reopen this module

1. Run the five suites first and confirm 63 green. If they are not green, the module
   drifted after this lock — find out how before changing anything.
2. State which invariant the change touches, and why it is safe.
3. Add the test before the code.
4. Re-run all 63. Update this file in the same commit.

Nothing goes into this module through a script that copies files between working
directories. A packaging script with a `||` fallback silently reverted four fixes at once
during P5; the tests caught it within the minute. Edit in place, commit from the repo.
