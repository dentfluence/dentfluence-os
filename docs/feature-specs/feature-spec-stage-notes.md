# Feature Spec — Notes at Pipeline / Opportunity Stages

> **Created:** 2026-07-06
> **Author:** Sumit / Dentfluence
> **Module:** PRE — Lead Pipeline + Opportunity Pipeline
> **Status:** Not built. Spec only.

## 1. Problem

Both the Lead edit form (`resources/views/relationship/pipeline/add-lead.blade.php`, ~line 191) and the Opportunity Detail modal (`resources/views/relationship/opportunities/_detail-card.blade.php`, ~line 92) have exactly one `notes` field: a single flat text column on the `leads` / `treatment_opportunities` table. Editing it overwrites whatever was there before. There is no way to:

- log something the patient said or asked at a specific point in the pipeline ("said she needs to check with her husband first," "wants to know if EMI is available"),
- see *when* a note was added or *who* added it,
- tell a staff-authored observation apart from a patient-reported response.

Every time front-desk adds a note today, it either overwrites the last one or gets appended as a run-on paragraph with no timestamp.

## 2. Why this matters commercially

Lead Pipeline and Opportunity Pipeline are the two screens where conversion actually gets decided — an implant lead who "says price is a concern" today and "confirms she'll pay after her husband's payday" next week is exactly the kind of soft signal that determines whether someone follows up at the right moment or lets a warm lead go cold. This is squarely inside "Ruthless Prioritization → increase treatment acceptance" — worth building. It's cheap, too, because the infrastructure already exists (see below).

## 3. Reframing the ask — one Note action, not two separate ones

The request as written asks for two new UI elements: a "Suggestion" card and a "Response" card. Before building both, it's worth pushing back a little: a "Suggestion" (staff observation) and a "Response" (what the patient said) are really the same underlying action — *add a timestamped, attributed note tied to this stage* — with a different tag on top. Building two separate buttons/cards/forms for what is functionally one feature is exactly the kind of feature-bloat the project should avoid (five small features where one powerful workflow would do).

**Recommendation:** one "Add Note" action, with a small type toggle inside the same small form:
- **Type:** ○ Staff note (suggestion / internal observation)  ○ Patient response
- **Text:** free text
- Submitted note appears immediately in a small reverse-chronological list under the existing Notes section, each entry showing type, author, and timestamp.

Same UI cost as building "two cards," but one form, one endpoint, one list renderer, and the door is open to add a third type later without a new button (e.g. "objection raised") without it being a schema change.

## 4. Don't build a new table — reuse `ActivityEngine` / `Activity`

The app already has exactly the infrastructure this needs: `App\Services\Relationship\ActivityEngine::log()` writes to a polymorphic `activities` table (`subject_type`, `subject_id`, `relationship_id`, `event`, `actor`, `metadata`, `description`, `occurred_at`), and it already powers the Timeline. `TodayController::logAction()` already uses it for call notes (`event: 'call.logged'`, `metadata.notes`).

Adding two new event keys is enough — no migration, no new model:

- `opportunity.note_added` (metadata: `note_type` = `suggestion` | `response`, `text`, `stage_at_time` = the opportunity's status when the note was added)
- `lead.note_added` (same shape, for the Pipeline)

This also means these notes automatically show up in the patient/relationship Timeline alongside calls, WhatsApp messages, and stage changes — which is more useful than a note buried only inside one modal, since staff reviewing a patient's full history will see it in context.

## 5. What changes

**Backend**
- `OpportunityPipelineController` — add `POST /relationship/opportunities/{opportunity}/notes` → validates `note_type` (`suggestion`|`response`) and `text` (required, max 1000), calls `ActivityEngine::log($opportunity, 'opportunity.note_added', auth()->user(), [...], $opportunity->relationship_id, $description)`.
- Equivalent `POST /relationship/pipeline/{lead}/notes` on the Lead Pipeline controller.
- Both return the newly created note (or the small refreshed list) as JSON for the modal to append without a full page reload.
- A small read helper — e.g. `ActivityEngine::forSubject($opportunity, ['opportunity.note_added'])` or reuse `Activity::forRelationship()` filtered by `event` — to pull just this subject's notes into the modal.

**Frontend**
- `_detail-card.blade.php` — replace the current read-only `@if($opportunity->notes)` block (line 92) with: existing legacy `notes` field shown once as "Original note" (don't lose old data), then a small "Add Note" form (type toggle + textarea + Save), then the running list of `opportunity.note_added` activities, newest first.
- `add-lead.blade.php` / Pipeline edit modal — same pattern; the existing flat `notes` textarea can stay as-is for the lead's "headline" note (what they called about), with the new timestamped list added below it as the ongoing log. No need to remove the existing field — it's the "why this lead exists" summary, distinct from the ongoing conversation log.

## 6. Out of scope (V1)

- No @-mentions, no reminders-from-a-note, no rich text — plain timestamped text entries only.
- No edit/delete of past notes (audit-trail principle — once logged, it stays; this matches how `Activity` already behaves everywhere else in the app).
- Not extending this to every module immediately — Lead Pipeline and Opportunity Pipeline are the two the request named and the two where it earns its keep. If it proves useful, the same pattern (one more `ActivityEngine::log()` call) is a ten-minute add to any other modal later — no architecture rework needed.

## 7. Build size estimate

Small: no migration. 2 new controller endpoints (or 1 shared one keyed by subject type), small Blade partial changes in 2 files, ~1 small reusable "note list + add form" Blade component worth extracting since both screens need it.

## 8. Artisan commands to run (after implementation)

None required — no schema change. `php artisan route:clear` after adding the two routes is the only housekeeping step.
