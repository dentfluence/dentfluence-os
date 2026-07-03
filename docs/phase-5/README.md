# Phase 5 — Workflow Engine + PRM/Acquisition + Marketing

Blueprint deliverables: (1) a Workflow Engine that orchestrates multi-step
sequences without executing side effects itself, (2) PRM leads becoming real
Relationships (not a parallel system), (3) Marketing sends routed through the
same governed channels as everything else.

Research before building found PRM → Relationship linking was **already
mostly done** (Phase 1's `LeadObserver` links every lead unconditionally,
not flag-gated) — the actual gap was narrower than the blueprint's framing
suggested: one stub method, plus a UI wiring gap discovered while fixing it.
The Workflow Engine itself was not started this pass — it's sized closer to
all of Phase 2 Automation and needs its own dedicated session (see the
proposal doc, to be added).

## Piece 1 — PRM lead → Patient conversion actually creates a Patient

**Problem found:** `PrmController::convertToPatient()` only flipped
`lead.stage = 'converted'` and returned `patient_id: null` — a stub, never
wired to the Patient module even though the Patient module has existed for a
long time. The comment blamed "Patient module not built yet," which was
stale.

**Fix:** the method now creates (or reuses) a real `Patient`, explicitly
linked to the **same** `relationship_id` the lead already has — set
unconditionally by `LeadObserver::created() -> RelationshipEngine::linkLead()`
on every lead, regardless of how it was created (webhook, PRM board Add Lead,
Quick Add, import). This deliberately does **not** go through
`PatientRelationshipLinker` (flag-gated off, does fuzzy phone/email
matching) — the lead's `relationship_id` is already the authoritative link,
no fuzzy matching needed. Idempotent: converting an already-converted lead
reuses the existing linked Patient instead of creating a duplicate.

`PrmRelationshipAdapter::onConverted()` (already existed, Workstream F) is
still called afterwards to sync the relationship journey + activity log —
unchanged.

Files: `app/Http/Controllers/Communication/PrmController.php`
(`convertToPatient()`). Test: `tests/Feature/Relationship/PrmConvertToPatientTest.php`.

**A second, separate stale stub was found and deliberately NOT touched:**
`FollowUpController::convertToPatient()` (`routes/communication.php:74`,
`/{id}/convert`) still has its own "Lead model not yet built" comment and is
a different code path (FollowUp module, not PRM board). Left alone to avoid
scope creep — flag for a future pass if that surface is actually in use.

**Verify:**
```
php artisan test --filter=PrmConvertToPatientTest
```

## Piece 1b — The "Convert to Patient" button didn't call anything at all

While verifying the Piece 1 fix was actually reachable from the UI, found
the live PRM board (`communication.prm.index`/`board`, and
`communication.prm.lead-detail`) both wire their "Convert to Patient" button
to a **global stub function** in `public/js/communication/prm-board.js`:

```js
function openConvertToPatient(leadId) {
    alert('Convert lead #' + leadId + ' to patient — coming in Session 11');
}
```

This stub never called any route — the backend fix above would have been
unreachable from the actual app no matter how correct it was. (Most of the
other PRM board quick actions — move-stage on drag/drop, add-note,
reschedule, mark-done — are the **same kind of stub**, still marked "coming
in Session 11." Those were deliberately left alone; fixing all of them is a
much bigger job than this one button, and out of scope for this pass.)

Separately, there's an entire second, more fully-built "Convert to Patient"
modal (`resources/views/communication/prm/lead-drawer.blade.php` +
`public/js/communication/lead-drawer.js`, with a real form for editing
patient details before conversion) that is **not included in any live PRM
page** and calls a `PrmBoard.persistConvertToPatient()` method that doesn't
exist anywhere in the codebase — dead code from an earlier, unfinished pass.
Left as-is; noted here so it isn't mistaken for the live implementation
later. If the richer modal (name/phone/DOB editing before conversion,
optional follow-up scheduling) is actually wanted, that's a real feature to
build later, not a bug to silently fix now.

**Fix (minimal, matches existing stub style):** `openConvertToPatient()` now
confirms, POSTs to `prm.convert`, and reloads the page on success — same
pattern already used by nothing else on this page (everything else is still
a stub), but the smallest change that makes the Piece 1 backend fix actually
usable from the app today.

Files: `public/js/communication/prm-board.js`.

## Piece 2 — Marketing WhatsApp silent no-op fixed (fails honestly instead)

**Problem found:** `PublishController::store()` lets a user select `whatsapp`
as a marketing post platform (it's in the validated platform list alongside
instagram/facebook/google_business/wordpress). But
`ProcessScheduledPost::dispatchToPlatform()` had no real WhatsApp adapter —
it fell through to the generic "no `PlatformConnection` found" branch, which
returns `success: true`. The post would show as **"published"** on WhatsApp
in the calendar UI while nothing was ever actually sent.

**Why this wasn't a quick "add a case" fix:** unlike Instagram/Facebook/
Google Business/WordPress — which publish content to a public page —
"publishing" via WhatsApp for a dental clinic means broadcasting to a list
of patients/leads. That's a fundamentally different, consent-gated flow
(see Phase 4's `CommunicationGuard` / DPDP consent), not a simple page post,
and the real WhatsApp Business API isn't configured yet (confirmed via
`.env`: dry-run only; Sumit's buying the real API a few months after VPS
go-live). Building a real WhatsApp broadcast path now would be premature.

**Fix:** `dispatchToPlatform()` now explicitly intercepts `whatsapp` before
the connection lookup and returns `success: false` with a clear, specific
error message, so the variant is honestly marked **failed** with a visible
reason instead of a false "published."

**A second, related bug found while fixing the first:** the code that saves
each platform's publish result was writing to a `'meta'` key —
`mkt_post_variants` has no `meta` column (the real column is
`platform_specific_meta`), so Eloquent was silently dropping it, and
`external_id`/`publish_error` were **never being saved to their real
columns at all**, for ANY platform, not just WhatsApp. That meant even a
real Instagram/Facebook failure's error message was going nowhere the UI
could show it. Fixed to write `external_id`, `publish_error`, and
`platform_specific_meta` (the actual columns) instead.

**Not done (a product decision, not a bug fix):** the WhatsApp checkbox is
still selectable in the compose form — it now fails clearly per-post instead
of lying, but Sumit may want to hide/disable that option in the UI entirely
until real WhatsApp broadcast is built, so staff don't select it in the
first place. Flagging, not deciding.

Files: `app/Jobs/Marketing/ProcessScheduledPost.php`. Test:
`tests/Feature/Marketing/ProcessScheduledPostWhatsappTest.php`.

**Verify:**
```
php artisan test --filter=ProcessScheduledPostWhatsappTest
```

## Next: the Workflow Engine proposal

Not started this pass — needs its own dedicated, properly-scoped session
(comparable in size to all of Phase 2 Automation). See task list.
