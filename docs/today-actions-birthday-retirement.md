# Today's Actions — Birthday category retired

**Date:** 2026-07-26 · **Decision:** CEO · **Scope:** board composition only. No data deleted, no capability removed, no new automation built.

## Why

Today's Actions must carry only **work that requires a human today**. A birthday greeting does not: it is a message that should be sent automatically. The intended flow is

```
patient birthday → automation → WhatsApp sent → Activity logged → NO human card
automation FAILS → exception/recovery obligation → Today's Actions → human handles it
```

The board previously produced a card per birthday (21 on the last production health run), which is 21 pieces of daily "work" that nobody needs to think about.

## What changed

- `TodayActionsEngine::generate()` no longer registers `birthdays`. **The board contract is now 14 categories, not 15** — changed honestly rather than leaving a ghost category returning zero forever.
- `generateUpcoming()` (date-picker preview) likewise no longer lists birthdays.
- `annotateDone()` resolver and `TodayController::DISMISSIBLE_MODELS` entries removed — a category that cannot appear cannot be dismissed.
- `CATEGORY_LABELS` / `CATEGORY_ICONS` / `CATEGORY_PRIORITY` entries removed.
- Tests updated: `TodayActionsCategoriesTest::ALL_CATEGORIES` (14 keys; the health assertion counts from that list), `TodayActionsDismissTest` no longer treats birthdays as dismissible.

## What was deliberately preserved

| Capability | Status |
|---|---|
| `TodayActionsEngine::birthdays()` / `birthdaysOnDate()` readers | **kept**, unregistered, documented as the future automation's data source |
| `RecallEngineService::recallBirthday()` — queues `purpose = recall_birthday` into `communication_queue` | **untouched**, still governed by AppSetting `recall.birthday_enabled` + `recall.birthday_window_days` |
| `birthdays:scan` scheduled command → `birthday.approaching` → RulesEngine `birthday_3d` | **untouched** |
| `MessageTemplate` of type `birthday` + `composeMessage('birthday', …)` | **untouched** |
| `POST /relationship/today/birthday-whatsapp` (one-click send + `whatsapp.sent` Activity) | **kept** — no longer reachable from a board card; reserved for the future automation/exception surface |
| Patient `date_of_birth`, `dob_unknown` | **untouched** |

## Honest caveat — birthdays can still reach the board

`RecallEngineService::recallBirthday()` writes `communication_queue` rows with `purpose = recall_birthday`, and `recallCalls()` matches any purpose containing `recall`. So **birthday re-engagement still appears under Recall Calls** when the recall engine queues it. That is the recall engine's output, governed by its own setting — not this category. Retiring the `birthdays` category removes the *derived-from-DOB* card set; it does not silence recall-engine birthday rows. Turning those off is a separate decision (`recall.birthday_enabled` in Settings).

## What the future automation still needs (not built here)

An actual sender (the current path is click-to-chat, i.e. human-initiated), a send-result record, and a failure signal that becomes a Today's Actions exception card. None of that exists yet and none of it was invented in this change.
