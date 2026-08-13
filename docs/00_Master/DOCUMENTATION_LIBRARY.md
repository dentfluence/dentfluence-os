# DENTFLUENCE DOCUMENTATION LIBRARY
**Generated 2026-08-07 · Repository Librarian pass · 151 documents organized · ZERO application files touched**

---

## 1. Repository Structure (documentation layer)

```
docs/  (9 files)
  00_Master/  (7 files)
  01_Governance/  (6 files)
  02_Product/  (7 files)
  03_Architecture/  (16 files)
  04_Modules/  (0 files)
    Appointments/  (3 files)
    Communication/  (12 files)
    Inventory/  (3 files)
    Marketing/  (3 files)
    Mobile/  (7 files)
    PatientJourney/  (10 files)
    Patients/  (17 files)
    PracticeProtocols/  (2 files)
    Settings/  (3 files)
    TreatmentPlans/  (4 files)
    TreatmentVisits/  (11 files)
    WhatsApp/  (5 files)
  05_Implementation/  (12 files)
  06_Operations/  (10 files)
  07_Testing/  (2 files)
  08_Research/  (4 files)
  09_Reference/  (3 files)
  10_Archive/  (4 files)
  abdm/  (9 files)
  architecture/  (2 files)
  archive/  (18 files)
  competitive/  (2 files)
  deploy/  (2 files)
  feature-specs/  (5 files)
  phase-0/  (7 files)
  phase-1/  (10 files)
  phase-2/  (3 files)
  phase-4/  (1 files)
  phase-5/  (3 files)
  phase-6/  (4 files)
  phase-7/  (1 files)
  prototypes/  (1 files)
  security/  (1 files)
```

Legacy folders (`abdm/, architecture/, archive/, competitive/, deploy/, feature-specs/, phase-0..7/, prototypes/, security/`) were **deliberately left in place** — their paths are referenced from application code comments and console output; moving them fails the 1%-uncertainty rule.

## 2. Documentation Tree — what lives where

| Folder | Contents | Files |
|---|---|---|
| 00_Master | DENTFLUENCE_MASTER, Master Roadmap docx, Register dashboard V3, DEVLOG, product-audit dashboard, execution backlog | 7 |
| 01_Governance | Canonical Treatment Lifecycle contract, CEO Directive #003, engineering/module governance, LOCKED_FEATURES | 6 |
| 02_Product | Pitch deck, presentation + spec, pricing, FIRE plan, what-is-PRE, OS feature roadmap | 7 |
| 03_Architecture | ARCHITECTURE, target architecture + diagrams, red-team review, event map, UI consistency audit, repo cleanup audit | 16 |
| 04_Modules/* | Per-module: Patients(17), TreatmentVisits(11), Communication(12), PatientJourney(10), Mobile(7), WhatsApp(5), Settings(3), Appointments(3), TreatmentPlans(4), Inventory(3), Marketing(3), PracticeProtocols(2) | 80 |
| 05_Implementation | **The 4 final audits (2026-08-07) + Master Implementation Checklist** + phase plans | 12 |
| 06_Operations | Deploy runbook, go-live checklist, smoke suite, automation map, handover, production hardening/readiness, Hostinger deploy notes | 10 |
| 07_Testing | Testing report PDF, KPI verification checklist | 2 |
| 08_Research | Competitive matrix, DBM gap analysis, PRM-AI plan, standalone CRM instructions | 4 |
| 09_Reference | OS Vocabulary Glossary (docx+pdf), voice-notes setup | 3 |
| 10_Archive | Dated status snapshots (06-27, 07-19), superseded completion notes | 4 |

## 3. Duplicate Report (nothing deleted — pairs kept side by side)

| Pair | Status |
|---|---|
| ceo-directive-003 .md / .html | Same content, two formats — keep both (01_Governance) |
| production-readiness-review .md / .html | Same content pair (06_Operations) |
| OS_Vocabulary_Glossary .docx / .pdf | Same content pair (09_Reference) |
| Settings_Redesign_IA vs Settings_Architecture_v2 | IA = **Older Version**, v2 governs (04_Modules/Settings) |
| pre-communication-simplification vs -cleanup-v3 | simplification = **Older Version**, v3 governs (04_Modules/Communication) |
| Treatment_Visit_V1_UX_Redesign_Spec vs Treatment_Visits_V1_UX_Freeze_Spec vs FINAL_UX_Design_Freeze | Chronological chain; FINAL governs (04_Modules/TreatmentVisits) |
| patients-module-audit vs patients-production-readiness-final-2026-08-03 | audit = **Older Version** |

## 4. Active Documents (the working set)

- **05_Implementation/Dentfluence_V1_Master_Implementation_Checklist.html** — THE canonical execution document (131 tasks)
- 05_Implementation/ four 2026-08-07 audit reports (Production, Implementation Readiness, Go-Live #4 + PDFs)
- 01_Governance/canonical-treatment-lifecycle-v1.md — supreme technical contract
- 00_Master/Dentfluence_Master_Register_Dashboard_V3.html · 00_Master/Dentfluence_Master_Roadmap.docx
- 06_Operations/deploy-runbook-2026-07-14.md · tulip-go-live-checklist.md · smoke-suite.md · automation-map.md
- 04_Modules/TreatmentVisits/Treatment_Visit_FINAL_UX_Design_Freeze.docx (governing UX spec)
- 04_Modules/Communication/pre-communication-cleanup-2026-08-04-v3.md
- docs/backend-orchestration-plan.md (pinned, active — referenced by 10+ code files)

## 5. Superseded Documents (kept, classified)

ceo-directive-003 (superseded by the 08-07 Implementation Directive — lives in project memory/journal) · Settings_Redesign_IA · pre-communication-simplification · patients-module-audit + early phase freeze docs (phase2/3/4 chain — historical record) · production-readiness-review 07-14 (superseded by 08-07 audits) · plan-os-feature-roadmap (superseded by CEO#003, itself superseded) · APP_STATUS/PROGRESS_STATUS snapshots · mobile-kickoff-huddle note · slice-2_2 completion note · patient-journey-v1_1-frozen-integration-contract (C-5 claim stale per memory).

## 6. Missing Documentation (gaps worth filling in V1.1 — not now)

- Per-role user manuals (Help Centre content exists in-app; no printable role handbook)
- Backup/restore runbook as its own doc (currently steps inside DEPLOY.md + checklist tasks)
- .env variable reference (being rebuilt as .env.example in Task 6)
- Flutter repo has no docs in this repository (app docs live with the app)
- V1.1 register (opens at Task 131)

## 7. Files Left in Root — and WHY

| File | Reason |
|---|---|
| DEPLOY.md | Referenced by deploy.sh:14, backup.sh:11-14, docker-compose.yml:48 — moving breaks documented ops paths |
| composer/package/vite/phpunit/artisan/docker files | Application runtime — out of scope by rule |
| deploy.sh, backup.sh | Executable scripts — out of scope by rule |

Pinned in docs/ root (referenced from app code — comments/console hints):
backend-orchestration-plan.md · patient-journey-v1_1-slice-2_4c-derivation-contract.md · marketing-module-reengineering-plan.md · marketing-module-technical-dossier.md · plan-smart-treatment-presentation.md · gap-analysis-treatment-planning-knowledge-bank.md · tulip-wake-word-setup.md · plan-relationship-engine-v1.md · slice-2_4a-clinical-progress-census.sql (1%-rule).

## 8. Safety Report

- **Moved: 151 documentation files** (md/docx/pdf/html/pptx/xlsx/mermaid only; `mv -n`, zero overwrites, zero deletions).
- **Application untouched — verified via `git status`:** the only modified files are the 5 pre-existing (uncommitted Treatment Visit sprint) files that were already modified BEFORE this task: TreatmentVisitController.php, treatment-visits-tab.blade.php, patients/show.blade.php, visits/print.blade.php, routes/web.php. No file under app/, bootstrap/, config/, database/, docker/, public/, resources/, routes/, storage/, tests/, vendor/, node_modules/ was created, modified, moved or deleted by this task (the untracked sprint blades/test predate this task).
- Laravel ✔ Flutter (not in repo) ✔ Git history ✔ Docker ✔ Composer ✔ Node ✔ Build ✔ Routes ✔ Storage ✔ Runtime unaffected ✔
- ⚠ **One pre-existing issue found (not caused by moves): a stale 0-byte `.git/index.lock` exists and cannot be removed from this sandbox.** Delete it from Windows (`del ".git\index.lock"`) before any git operation, or git will refuse to run.

### Commit recipe (run from Windows, two clean commits)
```
del ".git\index.lock"
git add app/ resources/ routes/ tests/
git commit -m "treatment-visits: final sprint (UX freeze + form page)"   # Task 4
git add -A
git commit -m "docs: organize documentation library (151 files, no code changes)"
```
