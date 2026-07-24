# Phase 1 (V1) — Deployment Action Plan

Execution plan to take **Dentfluence OS (Phase 1)** to production-ready, per CEO Directive #003.
Phase 1 = *polish, harden, and cut over existing modules so Tulip runs entirely on Dentfluence.*
No new modules. Quality > breadth. WhatsApp **Web only**. (Meta Cloud API, GBP/Meta, Clinical Cloud,
AI Copilot are V1.5/V2/V3 — explicitly out of scope here.)

Reality check: most modules are coded but marked *untested / unmigrated*. So this plan is ~80%
**verify + fix + cut over**, ~20% finish. Testing the backlog of untested code is the real cost.

---

## Stage 0 — Baseline & Triage  (BLOCKS EVERYTHING — do first)

You can't sequence work on top of unverified claims. Establish ground truth once.

- Run `php artisan app:crawl-routes` (auto-tests every page) + `php artisan security:selftest` + `automation:parity` on a prod-like copy.
- Reconcile the **11 Criticals** from the Production Readiness Review (coupon/wallet trust, dead webhooks, dummy Timeline, reports `completed` vs `done`, etc.) — which are still open?
- Walk every "untested/unmigrated" memory entry → mark real status (works / broken / needs migrate / dead).
- Output: **one Phase-1 Definition-of-Done checklist** = the master tracker for all workstreams.

**Depends on:** nothing. **Blocks:** all workstreams below.

---

## Parallel Workstreams  (run simultaneously after Stage 0)

### A — Critical Blockers & Data Integrity  ⛔ (go-live gate)
Fix the confirmed Criticals; re-verify the Production Hardening Sprint fixes (they shipped untested);
close any money/data-trust bugs (coupon/wallet trust, billing `completed`-vs-`done` reporting).
Confirm Phase-A security (private files, MFA, tamper-evident audit) actually holds in prod.
Decide with CEO: PHI encryption + full clinic_id isolation are **parked pre-launch** — confirm that's acceptable for single-clinic Tulip (required before clinic #2, not before Tulip go-live).

### B — Canonical Logic / Architecture Debt  🔧 (CEO principle)
Kill duplicate business logic → **one canonical service per capability**; controllers = transport only.
Known offenders: appointment slot logic (BookAppointmentTool vs AppointmentService), publish engines,
wallet/billing paths. This is refactor-for-AI-readiness with **no new features**.
*Coordinates with C (touches the same module files) and feeds D (canonical WhatsAppLinkService).*

### C — Module Polish & Cutover  ✅ (parallel across modules)
Per module: run pending migrations → test → flip feature flags to production state → smooth the
"experimental" rough edges. Each module is an independent sub-track that can run in parallel:
PRE/Relationship · Automation · Communication (WhatsApp Web) · Marketing (Blog Hub + Reviews) ·
Billing/Finance/Wallet · Consultation · Treatment (confirm Case-Acceptance scope for P1) · Lab ·
Inventory · Huddle · Prescriptions.
*Blog editor rebuild currently lives on local — deploy it under this workstream.*

### D — Communication: WhatsApp Web  💬
Finish + test the WhatsApp click-to-chat engine (untested); wire recall / birthday / opportunity
sends through the one canonical `WhatsAppLinkService`. This is the **only** Phase-1 comms path.
*Depends on B's canonical service; otherwise parallel.*

### E — Deploy / Infra & Runbook  🚀
Harden the pipeline: `deploy.sh`, the env-change→container-recreate gotcha, queue-worker crash-loop
monitoring, backups. Write the go-live runbook + rollback steps.
*Parallel; required for the final cutover.*

### F — Mobile  📱 (parallel, **non-blocking** for web go-live)
Web↔mobile parity (~50%), fix the 5 API money bugs + 2FA lockout, test the untested mobile slices.
**Confirm with CEO:** if Tulip staff operate on web, mobile is a *trailing* track, not a Phase-1
go-live blocker.

---

## Final Stage — Go-Live Gate & UAT
Once **A + C + D + E** are green: 1-week UAT with Tulip staff on production, eliminate every workaround,
confirm the DoD checklist is all-green → declare Phase 1 done.
**Depends on:** A, C, D, E complete. **F (mobile)** can finish after.

---

## Dependency & Parallelization Map (at a glance)

```
Stage 0 (Baseline/Triage)  ──►  blocks everything
        │
        ├─► A  Critical Blockers ─────────┐
        ├─► B  Canonical Logic ──┬─► feeds │
        ├─► C  Module Polish ◄───┘ (shared │  ──►  Go-Live Gate (UAT)  ──► Phase 1 DONE
        ├─► D  WhatsApp Web  ◄─── B         │
        └─► E  Deploy/Infra ───────────────┘
        └─► F  Mobile ───────────► (parallel, trails go-live)
```

- **Sequential gates:** Stage 0 → (A,C,D,E) → Go-Live.
- **Fully parallel:** A, C (across modules), E, F.
- **Coordinate (shared files):** B with C; B before/with D.
- **Not blocking web go-live:** F (mobile).

---

## Recommended first move
Kick off **Stage 0** now — run the crawler + security selftest + reconcile the 11 Criticals into the
DoD checklist. That single artifact tells us the true size of each workstream and lets us assign
parallel tracks with confidence, instead of trusting "untested" labels.
