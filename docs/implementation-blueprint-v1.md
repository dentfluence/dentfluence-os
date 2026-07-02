# Dentfluence — Implementation Blueprint v1.0 (Architecture Baseline v1.0)

**Author:** Lead Technical Architect
**Date:** 2026-07-02
**Baseline:** Architecture Baseline v1.0 — **FINAL** (no redesign in this document).
**Inputs:** Architecture Audit · Target Architecture Rev 3 · Gap Analysis · Red-Team Review.
**Nature:** Implementation blueprint only. **No code. No file changes. No migrations executed. No refactoring.** This is the master roadmap that will *govern* the eventual build.

**Governing goals (non-negotiable):** **Zero data loss · Zero broken workflows · Maximum backward compatibility · Incremental deployment · Feature-flagged cutovers · Safe rollback at every step.**

> **Four red-team conditions baked into this blueprint (clarifications, not redesign):**
> 1. **Consent is never overridden.** Urgency in the Communication Guard may relax *frequency* and *quiet-hours* only — never DPDP consent. This is a Phase-0 invariant and a hard gate in every later phase.
> 2. **Retry ownership is resolved.** The **Automation Engine** owns all scheduling/retry/backoff/cooldown/expiration mechanics. A **System Task** is only a *record + display* in the Task Engine; its execution is performed by Automation. No overlap.
> 3. **Events are idempotent and order-tolerant from day one.** They run synchronously in-process at the solo/pilot tier, but no subscriber may assume synchronous completion of another, so the later sync→async transport switch is a config change, not a rewrite.
> 4. **Right-sized to tier.** We build *today's required complexity only*. Projections (Timeline/Search/Analytics) start as simple views; Workflow ships linear-first; Insights ships 3 signals first. Enterprise/DSO machinery is deferred until a paying tier demands it.

---

## Section 1 — Overall migration philosophy

### Incremental migration
Nothing is rebuilt "big bang." Each capability moves in the smallest safe slice: **add alongside → dual-run → compare → cut over behind a flag → deprecate the old path.** The application must be fully working and shippable after *every* merge. A phase is a theme, not a release; within it we ship many small, individually-reversible changes.

### Strangler pattern
The new engines grow *around* the current code and slowly take its traffic, exactly as the codebase already hints (RecallEngine dual-writes to ActivityEngine; PRM redirects to the relationship profile). For each capability: the new engine first *shadows* the legacy path (writes/reads in parallel, logs differences), then serves reads, then serves writes, then the legacy path is silenced (not deleted). The legacy code stays warm until the new path has proven itself in production.

### Backward compatibility
- **`/api/v1` contracts are frozen.** The Flutter app depends on exact response shapes — no field is renamed, removed, or re-typed. New data is additive; breaking changes require a new version, never an in-place edit.
- **Public URLs are preserved** (especially review links `/r/{token}`). Retired web routes **redirect**, never 404.
- **Internal delegation over URL churn:** controllers get thinner behind the same routes rather than routes moving.

### Feature flags
Every cutover is gated by a flag (see Section 5). Flags are **per-capability**, default to the **legacy behavior**, and can be flipped **per environment and per branch/clinic**. A flag is only removed after the new path has run flag-on in production for a defined soak period with no regressions.

### Database strategy — **expand → migrate → contract**
1. **Expand:** add new tables/columns *additively* and nullable. Never rename or drop in place.
2. **Dual-write / backfill:** write to both old and new; backfill history with idempotent, restartable jobs; validate with row-count + checksum comparison.
3. **Read cutover:** flip reads to the new source behind a flag; keep writing both for a soak period.
4. **Contract (much later, never during migration):** once the new source is authoritative and soaked, the legacy table becomes a **read-only archive**. **No live data is ever deleted in this program.**

### Rollback strategy
Every step is reversible by one of three mechanisms, in order of preference: **(a) flip the feature flag back** (instant, no data change); **(b) fall back to the still-warm legacy path** (kept running through the soak); **(c) rebuild the projection from the Activity ledger** (for read-model corruption). Because migrations are expand-only, there is never a destructive DB rollback to perform.

### Deployment strategy
Promotion through gates: **Development → Tulip Dental (internal dogfood) → Pilot Clinics → Beta → Public.** Each gate has explicit entry criteria (Section 7). No capability reaches Public until it has soaked flag-on at every prior gate. The solo-builder reality is respected: phases are **gated, not time-boxed** — a phase ships when its Definition of Done (Section 9) is met, however long that takes.

---

## Section 2 — Implementation phases (adopted, with adjustments)

I adopt the proposed Phase 0–7 structure with three deliberate adjustments, each explained:

| Phase | Theme |
|---|---|
| **Phase 0** | Safety & Foundations — tests, flags, logging, monitoring, **event backbone skeleton**, **Guard emergency-hardening**, **Decision Log** |
| **Phase 1** | Relationship Foundation — Relationship Engine, Identity (`linkPatient` + backfill), Journeys, single Activity ledger |
| **Phase 2** | Automation — Rules consolidation, Automation Engine, reminder/recall consolidation |
| **Phase 3** | Work surfaces — Task Engine (human/system), Today's Actions projection, Daily Huddle rewire |
| **Phase 4** | Communication — Communication Engine, full 8-factor Guard, Notification consolidation |
| **Phase 5** | Workflow — Workflow Engine (linear-first), PRM/acquisition integration, Marketing rewire |
| **Phase 6** | Read & Insights — Insights (3 signals first), Analytics projections, Search index |
| **Phase 7** | Integration — Integration Engine, external systems behind the boundary |

**Adjustment 1 — pull three items *earlier* into Phase 0 (WHY).** The event-contract skeleton, the **Guard emergency-hardening (fail-closed + consent-never-overridden)**, and the **Decision Log** are foundations, not features. The Guard hardening is a *compliance* fix (DPDP) that must not wait until Phase 4; the event contracts must exist before Phase 1 can publish anything; the Decision Log must exist before any automation is trusted. Phase 4 still builds the *full* Communication Engine — Phase 0 only does the minimal safety hardening of the existing Guard.

**Adjustment 2 — Workflow ships linear-first (WHY).** The red-team correctly flagged that loops/parallel/versioning are enterprise gold-plating for the current stage. Phase 5 delivers a *linear* Workflow Engine plus one real template (RCT **or** Implant), with the non-linear primitives designed-for but built only when a clinic's real procedure demands them. Ship the 80% that pays now.

**Adjustment 3 — Insights ships 3 signals first (WHY).** The full 9-signal panel is deferred. Phase 6 delivers **Relationship Health, Lifetime Value, and Risk** — the three that drive Today's Actions and retention — as independent projections. Additional signals are added later, each as a new subscriber. This avoids signal-sprawl before there is demand.

**Interpretation note:** "PRE" in the proposed Phase 5 is read as **PRM / acquisition integration** (wiring the lead pipeline into Workflows + Relationship). If a different meaning was intended, this is the one assumption to confirm before Phase 5.

---

## Section 3 — Phase-by-phase detail

> Format per phase: **Objectives · Deliverables · Dependencies · Risks · Rollback · Testing · Success Criteria.**

### Phase 0 — Safety & Foundations
- **Objectives:** Make change *safe* before making change. Establish the ability to observe, flag, test, and reverse everything that follows.
- **Deliverables:** characterization tests around current engagement behavior (recall, follow-ups, notifications, PRM stage moves); the feature-flag mechanism; structured logging + a metrics/monitoring baseline; the **domain-event contract skeleton** (event names, payload shapes, publish/subscribe registration — synchronous, in-memory, idempotent); **Guard emergency-hardening** (flip fail-open→fail-closed; assert consent is never overridden); the **Decision Log** store (extend `relationship_rule_logs` to the full record).
- **Dependencies:** none (this is the base).
- **Risks:** hardening the Guard could suppress a currently-sent message. *Mitigation:* shadow-mode first (log what *would* be blocked), review, then enforce.
- **Rollback:** flags off returns to exact current behavior; the Guard change ships in shadow then enforce, each reversible.
- **Testing:** characterization tests must pass *as a description of current behavior* (the safety net); event-contract unit tests; Guard decision tests including consent-never-overridden.
- **Success criteria:** every subsequent phase can be built, observed, flag-gated, and rolled back. No behavior change visible to users except intended Guard compliance.

### Phase 1 — Relationship Foundation
- **Objectives:** One person = one Master Relationship, reliably. One activity ledger.
- **Deliverables:** `linkPatient()` wired into patient creation; idempotent **backfill** of `relationship_id` across leads + patients with a **dedup review queue** (no auto-merge of ambiguous matches); Journeys made authoritative *in shadow* (dual-written with `leads.stage`/`opportunity.status`, differences logged); `activities` becomes the sole write target while `lead_activities`/`comm_activity_logs` are mirrored read-only.
- **Dependencies:** Phase 0 (events, flags, tests).
- **Risks:** **mis-merging two people** (clinical-safety incident). *Mitigation:* conservative match rules; ambiguous matches go to a human review queue; every merge recorded in merge history and reversible.
- **Rollback:** flags return reads to legacy stage/logs; backfill is additive (new column populated, old untouched).
- **Testing:** identity resolution + merge/split tests; backfill validation (counts/checksums, sample audits); dual-write divergence report near-zero before cutover.
- **Success criteria:** 100% of new people get a Relationship; backfill validated; timeline reads from `activities`; journey shadow matches legacy stage within tolerance.

### Phase 2 — Automation (Rules + Automation + reminder consolidation)
- **Objectives:** One decision brain, one time-based executor, one reminder path — no double-contact.
- **Deliverables:** `FollowUpRulesService` config ported into the Rules Engine (legacy invocation retired after parity); the **Automation Engine** stood up (owns scheduling/retry/cooldown/expiration); recall + appointment-reminder triggers moved into Automation; the duplicate emitter silenced; every automated decision written to the **Decision Log**.
- **Dependencies:** Phase 0 (Decision Log, events), Phase 1 (relationship identity for targeting).
- **Risks:** dropping a live automation; duplicate reminders during transition. *Mitigation:* shadow-run new automation against legacy, compare outputs per trigger, cut over one trigger at a time; idempotency/cooldown in Automation prevents duplicates.
- **Rollback:** per-trigger flags; legacy recall/reminder path kept warm through soak.
- **Testing:** rule-parity tests (new vs legacy decisions identical on a replay set); idempotency/cooldown tests; "no double-contact" regression test.
- **Success criteria:** one reminder path in production; zero duplicate-contact incidents; every automation explainable via Decision Log.

### Phase 3 — Work surfaces (Task Engine + Today's Actions + Huddle)
- **Objectives:** Reliable, fast "my work for today"; system jobs invisible to reception.
- **Deliverables:** Task Engine gains **Human/System classification** (System Tasks are records executed by Automation — see resolved ownership); the **Today's Actions projection** replaces the ~12-domain live reads; Daily Huddle rewired to read Task/Today/Analytics views.
- **Dependencies:** Phases 1–2 (events, tasks, automation feeding the projection).
- **Risks:** projection lag hiding a just-created action; system jobs leaking to reception. *Mitigation:* projection updated on the same events that create work; strict class-based visibility; keep legacy Today's Actions available behind a flag until parity confirmed.
- **Rollback:** flag returns to the legacy live-read Today's Actions.
- **Testing:** projection-parity tests (new list == legacy list for the same data); write-amplification/performance test; reception-visibility test (no System Tasks shown).
- **Success criteria:** reception dashboard loads from one view in a single query; parity with legacy; no system job visible to clinical staff.

### Phase 4 — Communication (Engine + full Guard + Notification)
- **Objectives:** One gateway to the patient; intelligent, compliant, never-annoying contact; one internal alert store.
- **Deliverables:** Communication Engine as the single send/receive path (all senders — recall, reviews, marketing, workflow, staff — route through it); the **full 8-factor Guard** (preference, consent, frequency, quiet-hours, urgency, context, channel, history) with **consent never overridden**; Notification consolidated to a single store.
- **Dependencies:** Phase 0 Guard hardening; Phase 1 relationship; Phase 6 preference signal is a *nice-to-have* input (Guard works without it initially).
- **Risks:** suppressing needed messages; consent/DPDP error; notification gaps during store consolidation. *Mitigation:* Guard in shadow before enforce; consent-override forbidden by test; dual-write notifications during consolidation.
- **Rollback:** per-sender flags route back to legacy send; notification dual-write allows instant fallback.
- **Testing:** Guard decision matrix tests (incl. urgency-relaxes-frequency-but-not-consent); per-channel send/receive integration tests; DPDP consent security tests.
- **Success criteria:** no patient message leaves except through the Guard; zero consent violations; single notification store live.

### Phase 5 — Workflow (linear-first) + PRM/acquisition + Marketing
- **Objectives:** Model real dental procedures reusably; keep acquisition intelligence; make Marketing route through the spine.
- **Deliverables:** **linear** Workflow Engine + one real template (RCT or Implant); PRM lead pipeline wired to Relationship identity + (optionally) a lead-nurture workflow; Marketing audiences via Search/Insights and **sends via Communication+Guard** (no direct patient contact); non-linear primitives *designed* but built only on demand.
- **Dependencies:** Phases 1–4 (relationship, automation, tasks, communication).
- **Risks:** Workflow absorbing business logic / overlapping Journeys; Marketing bypassing the Guard. *Mitigation:* Workflow *requests* from other engines only; branch conditions consume facts, never embed clinical logic; Marketing has no send path except Communication.
- **Rollback:** Workflow is additive (new capability, flag-gated); Marketing send rewire flag-reversible to legacy.
- **Testing:** workflow step-advance/branch tests; "workflow never sends/executes directly" boundary test; Marketing-through-Guard test.
- **Success criteria:** one procedure runs end-to-end as a Workflow; Marketing cannot contact a non-consenting patient; PRM leads become Relationships.

### Phase 6 — Read & Insights (Insights 3-signal + Analytics + Search)
- **Objectives:** Actionable intelligence and fast reads, event-fed, no god-readers.
- **Deliverables:** **Insights** with Health, LTV, Risk as independent projections (AI-agnostic); Analytics as incremental aggregate projections; a Search index projection; **read contracts** insulating Insights/Today's-Actions from raw domain tables.
- **Dependencies:** Phase 1 (ledger), Phase 3 (Today's Actions consuming signals).
- **Risks:** signal sprawl; projection staleness; determinism breaks "rebuild from ledger." *Mitigation:* start with 3 signals; projections pure/deterministic (no external calls/clock/random in rebuild path); staleness monitored.
- **Rollback:** projections rebuildable; flags fall back to legacy score/reports.
- **Testing:** signal-correctness tests; rebuild-determinism tests; performance tests at projected scale.
- **Success criteria:** RelationshipScore replaced by 3 trusted signals; Today's Actions/Insights no longer touch raw domain tables; search served from the index.

### Phase 7 — Integration (boundary + external systems)
- **Objectives:** One anti-corruption boundary; business engines provider-agnostic.
- **Deliverables:** Integration Engine wrapping WhatsApp, Google (Calendar/Reviews), Meta, website, ABDM, payment gateways; inbound normalized to domain events; Communication delivers *through* Integration.
- **Dependencies:** Phase 4 (Communication uses the wire).
- **Risks:** integration regressions during rewrap. *Mitigation:* wrap one provider at a time, dual-run old direct call vs new connector, compare, cut over.
- **Rollback:** per-provider flags to the legacy direct call.
- **Testing:** connector contract tests; inbound-normalization tests; per-provider regression tests.
- **Success criteria:** no vendor SDK remains inside a business engine; all external I/O passes the boundary; no integration regressions.

---

## Section 4 — Database migration strategy (expand → migrate → contract)

**Universal rules:** additive-only; nullable new columns; dual-write during transition; idempotent restartable backfills; validate with counts + checksums + sample audits; **never delete or rename live data in this program**; legacy tables become read-only archives at the very end.

| Table | Current | Future | Migration (expand→contract) | Compatibility | Rollback | Validation |
|---|---|---|---|---|---|---|
| `activities` | one of 3 logs | sole ledger | Become sole write target; others mirror | Reads unaffected during dual-write | Flag reads back to merged view | Count/checksum vs merged legacy |
| `lead_activities` | PRM log | archive | Backfill→`activities`; stop writes last | Kept readable | Re-enable writes via flag | Row parity with mirrored facts |
| `comm_activity_logs` | comm log | archive | Same as above | Kept readable | Flag | Parity |
| `relationships` | partial | identity SSOT | Backfill `relationship_id` on leads+patients | New nullable FKs additive | Null FK = legacy behavior | Dedup review queue signed off |
| `relationship_journeys` | shadow | pipeline SSOT | Dual-write with stage/status; log divergence | Stage/status columns retained | Reads fall back to stage | Divergence ≈ 0 before cutover |
| `leads.stage` | authoritative | projection | Becomes read-through of journey | Column kept, still written in transition | Flag | Journey==stage checks |
| `treatment_opportunities.status` | authoritative | projection | Same | Kept | Flag | Journey==status checks |
| `relationship_rule_logs` | firing log | Decision Log | Extend schema additively | Old rows valid | Additive, no rollback needed | Schema back-compat test |
| `relationship_contact_log` | 3-rule | 8-factor | Extend record additively | Old rows valid | Additive | Guard decision audit |
| `relationship_notifications` | dual | single store | Become primary; `app_notifications` mirrors then archives | Both written during soak | Flag to dual | Delivery parity |
| `app_notifications` | dual | archive/mirror | Stop new writes last | Kept readable | Flag | Parity |
| `follow_ups`,`follow_up_notes` | live | tasks + schedules | Recreate as Tasks/Automation; keep table readable | Legacy queue works during transition | Flag to legacy queue | Task/queue parity |
| `communication_queue` | inbox | inbox + ledger feed | Emit facts additively | Unchanged | Additive | Fact emission audit |
| `tasks` | flat | +class column | Add nullable `class` (human/system) | Old rows default human | Additive | Visibility tests |
| `wa_threads`,`wa_messages` | Whatsapp | semantics + Integration wire | Transport moves to Integration; data stays | Inbox unaffected | Per-provider flag | Send/receive parity |
| Stable domains (clinical/finance/lab/etc.) | truth | truth + events | **Only additive event emission** | Fully unchanged | N/A | Event emission audit |

---

## Section 5 — Feature-flag strategy

Every flag **defaults to legacy**, is flippable **per environment and per clinic**, and is removed only after a clean production soak.

| Flag | Controls | Default | Enable when | Disable/rollback |
|---|---|---|---|---|
| `guard.fail_closed` | Guard blocks on uncertainty | off→**shadow**→on | after shadow review shows no wrongful blocks | flip off = legacy send |
| `guard.full_8factor` | full Guard evaluation | off | Phase 4 parity | flip to 3-rule |
| `identity.link_patient` | auto-link patients to relationships | off | Phase 1 | off = no auto-link |
| `identity.reads_relationship` | reads use relationship spine | off | after backfill validated | off = legacy leads/patients |
| `activity.single_ledger_reads` | timeline reads from `activities` | off | Phase 1 parity | off = merged read |
| `journey.authoritative` | journey drives pipeline state | off (shadow) | divergence ≈ 0 | off = stage/status authoritative |
| `automation.engine` | Automation owns recall/reminders | off (shadow) | per-trigger parity | per-trigger off = legacy |
| `rules.single_engine` | legacy FollowUpRules retired | off | rule parity | off = legacy rules |
| `today.projection` | Today's Actions from projection | off | Phase 3 parity | off = live-read version |
| `tasks.human_system_split` | class-based task visibility | off | Phase 3 | off = flat tasks |
| `comm.single_gateway` | all sends via Communication Engine | off (per-sender) | per-sender parity | per-sender off = legacy send |
| `notifications.single_store` | one notification store | off (dual-write) | parity | off = dual |
| `workflow.engine` | Workflow Engine active | off | Phase 5 template ready | off = legacy treatment tracking |
| `marketing.via_guard` | Marketing sends through Guard | off | Phase 5 | off = legacy marketing send |
| `insights.signals` | 3-signal Insights replaces score | off | Phase 6 parity | off = RelationshipScore |
| `search.index` | search from index | off | Phase 6 | off = live query |
| `integration.<provider>` | provider via Integration boundary | off (per-provider) | per-provider parity | per-provider off = direct call |

---

## Section 6 — Testing strategy

- **Unit tests:** each engine's pure logic (Guard decisions, rule evaluation, journey transitions, workflow step-advance, signal computation). Highest coverage on Guard and identity/merge.
- **Integration tests:** engine-to-engine via events (publish → subscriber reacts → projection updates), and `/api/v1` contract tests (frozen shapes).
- **Workflow tests:** template start/advance/branch/skip; the boundary test that Workflow never sends or executes directly.
- **Regression tests:** the Phase-0 characterization suite guards current behavior through every refactor; a "no double-contact" and "no consent violation" suite runs on every change.
- **Event tests:** contract tests per event (payload shape, additive-only); **idempotency** tests (same event twice = one effect); **order-tolerance** tests (out-of-order still correct) — so the future async switch is safe.
- **Performance tests:** Today's Actions projection load; Guard throughput on a campaign blast; Activity-ledger write and projection-rebuild time at projected scale.
- **Security tests:** consent-never-overridden; multi-tenant isolation (no cross-clinic read); AI read-scope; PHI access control on ledger/decision/contact logs.
- **Acceptance tests:** real clinic workflows dogfooded at Tulip Dental (recall goes out once, correctly; a procedure runs as a workflow; reception sees only human work).

**Gate rule:** parity/shadow comparison must be clean before any cutover flag flips; the characterization suite must stay green throughout.

---

## Section 7 — Deployment strategy & gates

| Stage | Purpose | Entry gate |
|---|---|---|
| **Development** | build + local verification | characterization suite green; new tests written; flags default-legacy |
| **Tulip Dental (internal dogfood)** | run the change in a real clinic we control | shadow comparison clean; no `/api/v1` contract change; rollback flag verified |
| **Pilot Clinics** | a few friendly external clinics | 1–2 weeks flag-on at Tulip with zero regressions; DPDP/consent tests pass; support runbook + rollback rehearsed |
| **Beta** | wider opt-in clinics | pilot soak clean; performance within budget at pilot scale; monitoring/alerts live |
| **Public** | general availability | beta soak clean; security review passed; backward-compat verified; flag can still roll back per clinic |

**Deployment gates are cumulative:** a capability never skips a stage, and each stage must soak flag-on with no regression before promotion. Because Tulip Dental is a real operating clinic, it is the primary safety net — a broken workflow shows up on our own front desk first.

---

## Section 8 — Daily development order (one step at a time)

The exact build sequence. Each step is shippable and reversible before the next begins.

1. Characterization test harness around current engagement behavior.
2. Feature-flag mechanism (per-env, per-clinic).
3. Structured logging + monitoring baseline.
4. Domain-event contract skeleton (sync, in-memory, idempotent).
5. Guard emergency-hardening: shadow mode (log would-block).
6. Guard enforce fail-closed + **consent-never-overridden** invariant.
7. Decision Log store (extend rule-log to full record).
8. `linkPatient()` wired into patient creation (flagged).
9. Identity backfill job + **dedup review queue** (validated).
10. `activities` as sole write target; legacy logs mirror read-only.
11. Journeys dual-written in shadow; divergence report.
12. Rules Engine: port `FollowUpRules` config; parity replay.
13. Automation Engine skeleton (schedule/retry/cooldown/expire).
14. Move recall triggers into Automation (shadow → per-trigger cutover).
15. Move appointment reminders into Automation; silence duplicate emitter.
16. Task Engine: add human/system class; System Tasks executed by Automation.
17. Today's Actions projection (shadow → parity → cutover).
18. Daily Huddle rewired to read Task/Today/Analytics.
19. Communication Engine gateway; route senders through it one at a time.
20. Full 8-factor Guard (shadow → enforce).
21. Notification single store (dual-write → cutover).
22. Workflow Engine (linear) + first template (RCT/Implant).
23. PRM acquisition wired to Relationship; Marketing sends via Guard.
24. Insights: Health, LTV, Risk projections; retire RelationshipScore.
25. Analytics projections; Search index; read contracts.
26. Integration Engine; wrap providers one at a time (shadow → cutover).

---

## Section 9 — Definition of Done (per phase)

A phase is DONE only when **all six** dimensions are satisfied:

| Dimension | Meaning (applies to every phase) |
|---|---|
| **Functional** | The capability works end-to-end for a real clinic workflow; parity/shadow comparison with legacy is clean. |
| **Technical** | New tests (unit/integration/event/idempotency) green; characterization suite still green; flags in place; expand-only DB; rollback rehearsed. |
| **Business** | It improves a step of the Clinic Operating Cycle (huddle→…→analytics); no live workflow regressed. |
| **User** | Reception/doctor experience is equal-or-simpler; no new on-screen complexity; system jobs invisible to clinical staff. |
| **Performance** | Within budget at the current tier (dashboard load, Guard throughput, projection freshness); no regression vs legacy. |
| **Security** | Consent never overridden; multi-tenant isolation holds; PHI stores access-controlled; AI read-scope respected. |

"Done" means shipped behind a flag, soaked at Tulip Dental with no regression, and reversible. Not "code merged."

---

## Section 10 — Project risks & mitigation

| Category | Biggest risk | Mitigation |
|---|---|---|
| **Time** | Solo builder + 13-engine surface = multi-year; risk of never finishing | Phases gated not time-boxed; ship smallest slices; right-size (linear Workflow, 3-signal Insights); each phase independently valuable so partial progress still helps |
| **Architecture** | Engines absorbing each other's jobs (drift back to today's mess) | Ownership map enforced in review; Workflow requests-only; Automation owns retry (not Task); Decision Log makes drift visible |
| **Technical** | Sync→async event semantics break subtly later | Idempotent + order-tolerant subscribers from day one; event tests enforce it; transport switch is config, not rewrite |
| **Business** | A refactor breaks a live clinic workflow (lost revenue/trust) | Strangler + shadow + flags + Tulip Dental dogfood catches it on our own front desk first; legacy path kept warm |
| **Adoption** | Staff confused by change | Equal-or-simpler UX gate; "my work for today" framing; change only what improves the operating cycle |
| **Migration** | Identity mis-merge (clinical safety); duplicate reminders; data loss | Dedup review queue + reversible merges; per-trigger cutover with cooldowns; expand-only DB (zero deletion), validated backfills |

---

## Section 11 — Execution principles (every change obeys these)

1. **Engine First** — build reusable engines; features consume them; never a feature-specific reimplementation.
2. **One Source of Truth** — one owner per capability (see baseline §5); never write the same fact to two authorities.
3. **Event Driven** — announce facts; subscribe to react; no engine calls another for side effects. Think *events*, not "bus."
4. **Clinic Operating Cycle** — every change must improve a step (huddle→reception→doctor→treatment→billing→lab→follow-up→analytics). If it improves none, don't build it.
5. **Simplicity First** — complexity lives inside engines, never on the dentist's screen.
6. **AI never owns business logic** — Tulip reads projections and requests actions through engines/Guard/Decision-Log only.
7. **Build only today's required complexity** — linear before non-linear; 3 signals before 9; projections before "engines"; defer DSO machinery until a paying tier needs it.
8. **Backward compatibility** — `/api/v1` frozen; public URLs preserved; retire via redirect; additive DB only.
9. **Progressive enhancement** — add alongside, dual-run, compare, cut over behind a flag, deprecate (never delete) the old path.
10. **Ship small** — the app is working and shippable after every merge; every step is independently reversible.

**Plus three red-team invariants that override convenience:** consent is never overridden; retry is owned by Automation alone; every subscriber is idempotent and order-tolerant.

---

## Closing

This blueprint turns a frozen architecture into a **safe, reversible, backward-compatible, incrementally-deployed** migration — not a rewrite. The order is deliberate: **safety → identity → automation → work surfaces → communication → workflow → insights → integration**, each phase shippable, flag-gated, dogfooded at Tulip Dental, and rollback-ready. Nothing here requires deleting live data, breaking the mobile API, or halting the clinic. Build the smallest safe slice, prove it in a real clinic, keep the old path warm, then move on.

**Per instruction: this is the implementation blueprint only. No code, no migrations, no refactoring performed. Do not begin building from this document until the team formally starts Phase 0, Step 1.**

*End of Implementation Blueprint v1.0.*
