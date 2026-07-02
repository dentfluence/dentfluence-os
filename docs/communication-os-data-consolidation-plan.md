# Communication OS — Data Consolidation Plan (Step 6)

_Created 2026-06-26. **No migrations were created or run.** This is a review-first plan for the data-layer cleanup. Everything here touches the database, so it belongs in its own supervised session with a backup taken first. Per project rule, you run `migrate` yourself after reviewing._

## Verification first — what's actually dead vs alive

The earlier assessment suggested dropping some `communication_queue` columns. On closer inspection **most are still load-bearing — do NOT drop them:**

| Column | Verdict | Evidence |
|---|---|---|
| `move_to` | **ALIVE — keep** | Written in `CommunicationController` (set to `archive` / `prm_pipeline` / `follow_ups` / `stay` at lines ~208, 337, 444-455) and **read** in `manager/show.blade.php:503-508` ("Sent to: …"). |
| `opportunity_value` | **ALIVE — keep** | Written by `RecallEngineService`; **read** by `AutoEscalateHighValueLeads` (core threshold logic), `b2b/show.blade.php:122`, and `Mail/SlaAlert.php`. Validated in `B2BController`. |
| `comm_type='new_lead'`, `source_engine='inbound'`, `SLA_MINUTES['inbound']` | **Vestigial for the lead path** | The PRM upgrade routes inbound leads into the `leads` table via webhooks → these `communication_queue` lead fields are no longer the intake path, but they're harmless and may still be referenced by legacy manager rows. Re-scope, don't delete blindly. |

**Confirmed dead:** `CampaignLeadService::attributeLead()` — see below.

## Item 1 — `CampaignLeadService` is unwired (decide: wire or remove)

`app/Services/Marketing/CampaignLeadService.php` has **zero callers**. Its docblock says it's "called from `LeadController::store()`", but **no `LeadController` exists** — leads are created by `LeadIngestService` and `PrmController`. So UTM campaign attribution currently never runs for inbound leads.

Two clean options:

**(a) Wire it in** — in `App\Services\Prm\LeadIngestService::ingest()`, right after the `Lead` is created, add:

```php
\App\Services\Marketing\CampaignLeadService::attributeLead(
    $lead,
    $payload['utm_campaign'] ?? null,
    $payload['utm_source'] ?? null,
);
```
(Use whatever the ingest payload calls the UTM fields; the website webhook is the one that would carry them.) This is the only change needed — but it starts writing attribution rows, so test on one lead first.

**(b) Remove it** — if you're not doing campaign ROI attribution yet, delete the service and its `mkt_campaigns` `LIKE`-matching `getStats()` to avoid dead code. (Ask before deleting — project rule.)

_Recommendation: (a), since you just upgraded the PRM intake and channel ROI is on the roadmap — but it's a behavioural change, so it's yours to greenlight._

## Item 2 — Two "inboxes": `leads` vs `communication_queue` (design decision, not a migration)

The upgraded PRM pipeline made **`leads`** the real lead store (webhooks → `LeadIngestService` → `LeadObserver`). `communication_queue` remains the home of **recall + B2B + manually-logged comms**. They overlap only in name ("universal inbox").

Recommended resolution — **documentation, not schema change:**
- Formally declare `communication_queue` = "recall + B2B + manual comm log", and `leads` = "the lead pipeline". Add a one-paragraph note to each model's docblock so the next person doesn't rebuild lead intake on the wrong table.
- Leave the vestigial lead columns in place (they're cheap and some legacy rows use them). Only revisit if you later migrate old `new_lead` rows into `leads`.

## Item 3 — `patient_communications` vs `communication_queue` (consolidation candidate)

`PatientCommunicationController::index()` already **merges both tables** at read time for the patient profile (tagging rows `_source: patient_communications` vs `communication_list`). That's two tables holding "communications with a patient."

If you want one table later: migrate `patient_communications` rows into `communication_queue` (mapping channel/direction/notes), repoint the patient-profile read, then retire `patient_communications`. This is a **data migration with backfill** — its own session, backup first, not in scope here.

## Item 4 — Minor consistency nits (safe, low priority)

- **Role lookup is split:** `SendSlaAlert` / `AutoEscalateHighValueLeads` find managers via the legacy `role` **string** (`where role = 'admin'/'manager'`), while `LeadRoutingService` uses the new `role_id` / role model. Pick one (the `role_id` system) so escalations and routing agree on who a "manager" is.
- **Birthday outreach exists twice:** `RecallEngineService::recallBirthdayAnniversary()` and `followup_rules.php → special_occasion`. Decide which owns it to avoid double messages.
- **Duplicate migration timestamp prefix** `2026_06_13_300001` is used by two different migrations (b2b fields + recall tracking). Cosmetic; harmless.

## Bottom line

Nothing here should be run blind. The only **code** change worth doing soon is Item 1 (wire or remove `CampaignLeadService`). Items 2–3 are design decisions that, if acted on, are data migrations requiring a backup and your terminal. No destructive migration is recommended or provided.
