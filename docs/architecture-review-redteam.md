# Dentfluence — Adversarial Architecture Review (Red Team)

**Reviewer:** Principal Architect (external design-review posture — Apple / Stripe / Linear / Epic / Salesforce / Google lens)
**Date:** 2026-07-02
**Subject:** `docs/target-architecture-engine-first.md` Rev 3 ("frozen"), with `docs/gap-analysis-current-to-target.md`.
**Mandate:** Attack the design. Find weaknesses before implementation. No redesign, no code, no politeness.

> **Opening shot — the premise is already suspect.** This document was revised three times in roughly one week (Rev 1 → 2 → 3), each revision materially changing engine names, engine count, and core responsibilities (Identity was a separate engine, then wasn't; Temporal became Automation; two engines were added). An architecture that changed this much in seven days is now declared "stable and frozen for a decade." That is not stability; that is a snapshot. Good architectures at Stripe/Google are **not frozen** — they evolve behind stable interfaces. "Freeze for 10 years" is itself an anti-pattern, and I will not bless it. I will judge the *design*, then answer the freeze question honestly at the end.

---

## 1. Which decisions are excellent?

Credit where due — these are genuinely strong and I would defend them in any review room:

- **One Master Relationship per person.** Every serious platform converges here (Epic's patient, Salesforce's Party). Correct spine.
- **Append-only Activity ledger as the source of truth, with derived read views.** Pragmatic event-sourcing-lite. The "rebuild the view from the ledger" property is the right instinct.
- **AI as an interface, not a surface (§12A).** This is ahead of most teams shipping AI in 2026. Forcing Tulip to act only through engines/Guard/Decision-Log is the single best decision in the document. It prevents the #1 industry AI failure (the model becoming a shadow god-service).
- **Communication Guard as a single, fail-closed consent/frequency gate.** For a DPDP-regulated product, one mandatory choke point beats scattered ad-hoc checks. Right call.
- **Modular monolith over microservices for the solo tier.** Correct pragmatism; microservices for a one-dentist clinic would be malpractice.
- **Decision Log.** Most teams skip "why did the automation do that" and regret it for years. Including it up front is mature.
- **Refusing to touch stable clinical/billing/lab domains.** Discipline. The instinct to concentrate change in the engagement layer is correct.

That is a real list. Now the knives.

---

## 2. Which decisions may become technical debt within 5 years?

- **"CQRS-lite where domains keep state tables AND emit events AND feed projections."** This is a **dual-write reality** dressed as event-sourcing. Every projection is a cache that can silently diverge, and every domain now has two jobs (own its table, publish a faithful event). In 5 years you have projection-rebuild code nobody trusts and "the number on screen doesn't match the table" bugs. You criticized the current system for hand-synced tables; this reintroduces the same hazard with better branding.
- **The event catalog as an "additive-only, versioned" contract — with no governance tooling named.** Discipline erodes. In 5 years there are 150+ event types, three teams (or one exhausted solo dev) unsure who owns `AppointmentCompleted`'s payload, and nobody dares change it. Hand-waved event versioning is a classic 5-year debt bomb.
- **"Transport is the only dial" (in-memory sync → async broker).** This is the most dangerous latent assumption in the document (expanded in §13/#17). Code written against synchronous, ordered, exactly-once in-process events will subtly break under at-least-once, out-of-order async delivery. That is not a dial; that is a re-architecture wearing a dial's costume.
- **Insights as "a panel of independent signals."** Signal sprawl. Without governance, 5 years yields 25 signals, half stale, and no one knowing which are trustworthy. The MarketingScore-stays-separate carve-out already hints at the coming fragmentation.
- **Journeys (coarse) + Workflows (fine) both modeling "where is this case."** These will blur under real requirements and become a perpetual "which one owns this state?" argument.

---

## 3. Which engines are unnecessary?

Brutally: **"13 engines" is partly a vanity metric.** Several are not engines; they are projections or thin variants, and calling them engines inflates the system's apparent (and cognitive) weight.

- **Timeline Engine** — has no write authority and makes no decisions. It is a *query over the ledger*. Calling it an engine is naming inflation.
- **Search Engine** and **Analytics Engine** — at the solo/multi-chair tier these are a database index and a handful of aggregate queries. They are projections, not engines, for ~90% of tenants.
- **Notification Engine** vs **Communication Engine** — defensible, but at small scale this is "send a message to an internal human" vs "send a message to an external human." One engine with an internal channel would serve the solo tier fine. The split earns its keep only at scale.

None of these are *wrong to exist*, but promoting all of them to "engine" flattens the distinction between things that hold truth/decisions (Relationship, Activity, Rules) and things that are disposable views (Timeline, Search, Analytics). That taxonomy confusion is a real cost (see §10).

---

## 4. Which engines have unclear responsibilities?

- **Relationship Engine.** It now owns identity + dedup + merge + journeys + is "the façade every surface reads through." The doc insists it is "the hub, not the warehouse," but the façade role (composing projections on `get()`) is *exactly* how a hub quietly becomes a warehouse. Its boundary is the fuzziest precisely because it is central and was expanded (Identity folded in during Rev 2).
- **Workflow Engine.** "Orchestrates but never executes and never contains business logic" — yet it evaluates branch conditions like *"if healing successful."* Deciding whether healing succeeded, or which branch to take, **is** business logic. The claim is aspirational and will be violated in the first real RCT template.
- **Automation Engine.** Bundles scheduling + retries + cooldowns + expirations + recurring evaluation. "Cooldown" is described as an execution mechanic here, but a cooldown is also a *policy* ("don't contact within 24h") — which the doc elsewhere assigns to Rules/Guard. The line is asserted, not clean.

---

## 5. Which engines overlap?

- **Automation Engine vs Task Engine's System Tasks — genuine, unresolved overlap introduced in Rev 3.** Automation "owns retries." System Tasks include "retry webhook, retry WhatsApp, retry integration." So *who owns retry* — Automation or the Task Engine? Both, per the document. That is precisely the kind of ambiguity this whole exercise exists to kill, and Rev 3 added it.
- **Decision Log (Rules) vs Activity Ledger (Activity) vs Contact Log (Guard) vs Analytics.** The document lambasts the current system for **three activity logs** — then specifies an Activity Ledger, a Decision Log, and a Contact Log as separate stores. The justification (patient facts vs brain decisions vs contact attempts) is reasonable, but a skeptic will note you have re-created "multiple append-only logs" and simply argued these ones are fine. That's a double standard unless the boundaries are enforced ruthlessly.
- **Insights vs Communication Guard on "preference."** Insights computes preferred channel; Guard enforces it. Defensible seam, but preference logic now lives in two places.
- **Journeys vs Workflows** (again).

---

## 6. Which responsibilities violate Single Responsibility?

- **Relationship Engine:** identity resolution + lifecycle state + read façade = three responsibilities. Rev 2 *deliberately* folded Identity in "for simplicity" — a knowing SRP regression. Fine as a pragmatic call, but do not pretend it's SRP-clean.
- **Communication Engine:** threads + templates + channel adapters + delivery status + an 8-factor Guard (consent, frequency, quiet hours, urgency, context, preference, history). The Guard alone is 8 concerns. This is the most overloaded component in the system, and it sits on the most regulated, highest-legal-risk path.
- **Automation Engine:** 5 distinct mechanisms in one box.

SRP is a guideline, not scripture — but the document *sells itself* on "one owner per capability," so it should be held to its own standard, and by that standard three engines are doing multiple jobs.

---

## 7. Which parts may become bottlenecks?

- **The Activity Ledger.** Every event writes to it; every projection reads from it. At DSO scale (thousands of clinics, hundreds of millions of facts) this is the single hottest object in the platform — a write chokepoint and a rebuild time-bomb. "Just rebuild from the ledger" is comforting until the ledger is billions of rows and a rebuild is measured in days, not seconds.
- **The Communication Guard.** 8 lookups per outbound message. A 10,000-patient marketing blast = 80,000 lookups synchronously gated. Guard is a throughput ceiling on exactly the operation (campaigns) that most wants throughput.
- **The Relationship Engine façade** on every patient open (composes multiple projections) — hot path.
- **Per-branch, per-role Today's Actions projection** — every relevant event fans out into write-amplified projection updates.

---

## 8. Which parts may hurt performance?

- **Eventual consistency is a performance-perception trap.** Staff act on projections that lag the write model. Two receptionists, a just-completed appointment not yet reflected, a recall fired against stale state — the exact "double contact / double book" failure the design claims to eliminate can re-enter through projection lag. Read-your-own-writes is not guaranteed and is not addressed.
- **Projection rebuild cost** at scale (above).
- **Guard latency** on the send path.
- **Fan-out write amplification:** one `AppointmentCompleted` can trigger Activity + Timeline + Insights (several signals) + Analytics + Today's Actions + Journey + Workflow updates. That is a lot of work per clinical click.

---

## 9. Which parts may hurt developer productivity?

- **This is designed for a team; it is being built by a solo builder.** That is the elephant. Event-driven + CQRS-lite + 13 engines + projections + an event catalog is a large surface for one person to hold in their head and maintain. The user's own stated preference is "explain in simple language, I am a solo builder." The architecture optimizes for a 30-engineer org and taxes a team of one.
- **Debugging by event-tracing, not call stack.** "Why did this happen?" means chasing event → subscriber → projection across many files. The Decision Log helps for *Rules* decisions only; the general event flow has no equivalent. This is the well-known event-driven debugging tax, and it lands hardest on a small team.
- **"Which log do I write to?"** (Activity vs Decision vs Contact) and **"which state owns this?"** (Journey vs Workflow) are decisions a dev makes wrong repeatedly.

---

## 10. Which parts may confuse future developers?

- **"Think in Events, never the bus."** The bus exists; you are told not to think about it — until you are debugging a lost/duplicated/out-of-order event, at which point you *must*. Telling developers to ignore the transport is fine until the transport fails, and then the abstraction actively misleads.
- **Engine vs projection vs surface taxonomy** (a "Timeline Engine" that is really a view; a "Today's Actions" that is explicitly *not* an engine but has a "Today's Actions view" projection *and* a presentation layer of the same name).
- **Journey vs Workflow** — semantically near-identical English words for deliberately different concepts. Guaranteed to be confused.
- **Automation vs Rules** — both read as "the thing that automates," a collision the author already flagged in Rev 2 and still did not fully resolve by naming.

---

## 11. Which parts are over-engineered?

**This is the headline finding.** For the solo and multi-chair tiers — which is where the product actually is today — the architecture is **substantially over-engineered.** Full CQRS-lite, an append-only ledger, 13 engines, projections, a Decision Log, Workflow versioning/loops/parallel branches, and multi-signal Insights are enterprise/DSO machinery imposed on a clinic that needs a solid CRUD app with a dozen reliable automations. "Same foundation solo→DSO" is sold as a strength, but it cuts both ways: **the solo dentist pays the full complexity tax of enterprise architecture** to serve a DSO tier that may not exist for years. Workflow parallel-branches-with-versioning for a product with an early customer base is gold-plating.

---

## 12. Which parts are under-engineered?

Conversely, the genuinely hard 20% is hand-waved:

- **Async event semantics** (ordering, at-least-once, idempotency, partial failure) — the hardest part, reduced to "switch the transport."
- **Multi-tenant isolation.** "Branch/org is a dimension on every event" is not isolation. Noisy neighbors, per-tenant rebuilds, cross-tenant query-bug leakage of PHI, and India data-residency (ABDM) are barely touched.
- **Event schema governance/versioning** — asserted, not specified.
- **Projection consistency / read-your-writes / staleness UX** — unaddressed.
- **Identity merge/split.** "Reversible merges" is one line. Merging patient records is one of the hardest, highest-clinical-risk problems in health software (Epic staffs teams on it). Getting it wrong merges two people's medical histories.
- **PHI security of the concentrated ledger** (see §16).

The design is detailed where it is fun (engine taxonomy, the operating cycle) and thin where it is hard (distributed-systems failure modes, tenancy, identity safety).

---

## 13. Which assumptions may fail across tiers?

- **Solo:** the assumption that a solo tenant *needs or tolerates* this machinery. It doesn't; it's over-served and, if self-hosted/maintained by one person, over-taxed.
- **Multi-chair:** the assumption that eventual consistency is invisible. Two chairs / two receptionists on a shared schedule and shared relationship will surface staleness and race conditions.
- **DSO:** the assumption that "one monolith, flip to a broker" scales. A single large DSO tenant can dwarf all solo tenants combined; the hot ledger, projection rebuilds, and noisy-neighbor isolation become first-order problems the doc defers to a *reserved, undesigned* Organization Engine. Cross-clinic patient movement — explicitly deferred — is one of the hardest parts and is where DSO value actually lives.
- **Enterprise:** data residency, org-level RBAC/SSO, and integration with hospital systems (HL7/Epic) are a different universe; "Integration Engine" is named but enterprise integration is not solo-vendor-adapter work.

The five-tiers-one-foundation promise is elegant on a slide and unproven where it is hardest (the top two tiers).

---

## 14. Which naming could be improved?

- **"Engine"** is used 13 times for things of very different weight (truth-owners vs disposable views). It has lost meaning.
- **"Automation Engine" vs "Rules Engine"** — still colliding; both say "automate." The author flagged this in Rev 2 and papered over it with a boundary paragraph rather than a name.
- **"Journey" vs "Workflow"** — too close; rename one.
- **"Master Relationship" / "Relationship Engine"** — "Relationship" is an overloaded English word and jargony for a dentist. Epic/Salesforce chose "Patient"/"Party" for a reason.
- **"Activity Engine" / "Fact Ledger"** — two names for one thing in the same document.
- **"Today's Actions"** names three things (a concept, a projection "view," and a presentation layer).

---

## 15. Which future products may struggle with this architecture?

- **Chairside, real-time, offline.** A dentist mid-procedure cannot wait on projection lag and cannot lose events when the operatory Wi-Fi drops. An eventual-consistency, server-side event/projection model is a poor fit for offline-first, sub-second chairside — and the Flutter app *already exists* and is offline-capable. How do offline-generated events reconcile, re-order, and de-duplicate against the server ledger? Unanswered, and it's central to the flagship "Chairside" surface.
- **Marketplace.** Multi-party, cross-tenant identity and referrals strain a person-centric, single-tenant-shaped model.
- **Enterprise BI/analytics.** Operational projections are not a warehouse; serious analytics will demand a separate OLAP path anyway, making the "Analytics Engine" a half-measure.
- **Anything needing strong consistency** (billing disputes, medico-legal records) sits awkwardly on eventual-consistency reads.

---

## 16. Which security concerns exist?

- **Consent-vs-urgency in the Guard is a latent legal landmine.** The doc says urgency overrides *frequency and quiet-hours*. It must **never** override **consent** — if an "urgent" flag can bypass DPDP consent, that is an unlawful communication. This needs to be stated in ink; right now it's inferable but not nailed, and "urgency overrides" is exactly the kind of clause that gets abused.
- **The Activity Ledger concentrates all patient facts in one store** — a single, maximally attractive breach target. Encryption-at-rest, field-level protection, and access control on the ledger are unaddressed. Same for the new Decision Log and Contact Log (more PHI-adjacent stores to secure).
- **AI read scope.** Tulip has broad read access to Timeline/Insights/Search. That is a data-exfiltration and prompt-injection surface. Per-user authorization of what the AI may read and request is not specified. "Acts through engines" governs *writes*; it says nothing about *read* scoping.
- **Multi-tenant isolation in a shared monolith/DB.** One missing `where branch_id = ?` leaks cross-clinic PHI. "A dimension on every event" is not an isolation boundary.

---

## 17. Which data consistency concerns exist?

- **Dual sources (write tables + projections) with eventual consistency** — the central tension. No read-your-writes guarantee; staff-facing staleness.
- **"leads.stage / opportunity.status become read-through projections of the Journey."** Keeping a projection synced to the journey is *sync-by-side-effect* — the precise sin the design condemns in the current code, relabeled as "projection."
- **Ordering & idempotency** once async — out-of-order events apply journey transitions wrongly; every subscriber must be idempotent. Asserted nowhere concretely.
- **Identity merge** mutates the node that every event references — stale references, orphaned facts, and "which relationship_id won" hazards.

---

## 18. Which testing concerns exist?

- **The architecture raises the testing bar dramatically for a codebase that scores 3/10 on testing today.** Event contract tests, projection-rebuild determinism tests, idempotency tests, ordering tests, and end-to-end async timing tests are all now *required* — and almost none exist. The gap between required test maturity and actual is enormous, and it is the difference between "elegant event system" and "haunted mystery."
- **Projection determinism.** "Rebuild from the ledger" only holds if projections are pure/deterministic. Any clock, randomness, or external call in a projection breaks the guarantee — and nothing forbids them.

---

## 19. Which migration concerns exist?

- **Surface area vs team size.** 13 engines (most partial or absent) migrated by a solo builder via strangler-fig is a multi-year program for one person. Realistic? The gap analysis's own P0–P6 is sober, but the calendar risk is severe.
- **The sync→async switch is itself a migration** with different failure semantics, not a config flip.
- **Identity backfill/dedup is clinically dangerous** — merging the wrong two patients is a patient-safety incident, not a bug.
- **Projection rollback = rebuild**, which is slow at scale; there is no fast rollback story.

---

## 20. Would I freeze this for 10 years? Score and verdict.

**No — and asking to freeze it is the wrong question.** The best shops (Stripe, Google, Linear) do not freeze architectures; they stabilize *interfaces* and evolve *implementations*. A 10-year freeze on a design that changed three times in one week is a contradiction. Freeze the **principles** (one relationship, one fact ledger, events over direct calls, one Guarded communication path, AI-as-interface). Do **not** freeze the **engine map** — it will and should change as tiers 4–5 become real.

**Score: 7 / 10** as a *north-star target*. It has an excellent spine and several best-in-class decisions (AI-as-interface, the ledger, the Guard, the Decision Log). It loses three points for being **over-engineered for the product's actual stage**, **under-specified on the genuinely hard problems** (async semantics, multi-tenant isolation, identity-merge safety, PHI security of the concentrated ledger), and carrying **self-inflicted Rev-3 overlaps** (retry ownership; three-logs double standard) plus **naming collisions** it already knew about and didn't fix.

As an *immediate build for a solo builder*, it would score lower — maybe 5 — because the complexity-to-stage mismatch is the biggest practical risk to the project actually shipping.

---

## Verdict

**APPROVE WITH MINOR CHANGES** — where "minor" means *the structure and principles stand; no redesign is needed* — but I am attaching **hard, non-optional conditions that must be resolved before implementation begins.** These are clarifications, not a new architecture:

1. **State in ink that urgency NEVER overrides consent** in the Guard. (Legal.)
2. **Resolve retry ownership** — Automation *or* Task System-Tasks, not both.
3. **Specify async event semantics** (ordering, idempotency, at-least-once, failure) now — do not defer it to "flip the transport."
4. **Specify multi-tenant isolation and PHI protection of the Activity/Decision/Contact logs** — "a dimension on every event" is not an answer.
5. **Right-size the implementation to the current tier.** Build the solo/multi-chair reality first with the *minimum* engines that pay for themselves (Relationship, Activity, Rules, Automation, Communication+Guard, Task, Notification). Treat Timeline/Search/Analytics as projections, not ceremony. Defer Workflow's loops/parallel/versioning and multi-signal Insights until a paying tier demands them.
6. **Rename the Automation/Rules and Journey/Workflow collisions.**
7. **Drop the "frozen for 10 years" framing.** Freeze the principles; version the engine map.

If those seven are addressed, this is a genuinely strong platform architecture and I would sign off. If the team instead treats the current document as literally frozen and builds all 13 engines up front on a solo budget, I would **not** approve — that path over-builds the easy 80% and under-builds the hard 20%, and risks the product never shipping.

*Brutal honesty delivered as requested. No redesign performed. No code written.*
