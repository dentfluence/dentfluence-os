# DENTFLUENCE V1 — DOCUMENTATION CONSOLIDATION REPORT

**Generated 2026-08-07 · Second pass over the documentation library (first pass: 00_Master/DOCUMENTATION_LIBRARY.md, 08-07 earlier) · Scope: `docs/` only, nothing outside it touched · Nothing deleted.**

---

## 1. Active Documents

**51 canonical documents** across 18 topics (7 program-level folders + 11 module folders touched this pass; 6 additional module/reference folders were already single-topic and untouched). Full list with the reason each was chosen: `00_Master/INDEX.md`.

## 2. Archived Documents

**88 documents moved into `Archive/` subfolders** — 26 classified `Reference` (still useful, cited from their folder's README), 62 classified `Superseded` (fully replaced). Zero deletions, zero overwrites — every move used a non-clobbering operation and skipped anything already present at the destination.

## 3. Canonical Documents (why each was chosen)

Full reasoning is written into each folder's own `README.md` (18 READMEs generated this pass) — the same reasoning is indexed centrally in `00_Master/INDEX.md`. Selection method, applied consistently: (a) most recent dated audit/freeze/completion document wins over earlier drafts of the same topic; (b) an explicit 'FINAL' or version-numbered document (e.g. `_v3`, `_v2`, `FINAL_UX_Design_Freeze`) wins over unnumbered predecessors; (c) implementation/completion documents win over the design or planning documents that preceded them; (d) documents already named 'reference' or 'master' in the project's own governance record were kept at Reference tier rather than promoted to canonical if a more recent audit superseded their status content.

## 4. Duplicate Topics Eliminated

| Topic | Before (competing docs) | Now (one canonical) |
|---|---|---|
| Treatment Visits UX | 11 versions (redesign spec → modal design → freeze review → V1 freeze spec → FINAL) | `Treatment_Visit_FINAL_UX_Design_Freeze.docx` |
| Settings architecture | 3 versions (Redesign IA → CTO Audit → Architecture v2) | `Dentfluence_Settings_Architecture_v2.docx` |
| Communication/PRE | 9 docs incl. 3 same-day variants + retired Communication-OS set | `pre-communication-cleanup-2026-08-04-v3.md` + 2 supporting docs |
| Patients module status | 13 docs across 4 phase-freeze cycles | `patients-variants-production-audit-2026-08-03.md` (+ readiness companion) |
| Patient Journey V1.1 | 10 slice/baseline/census docs | `journey-timeline-production-audit-2026-08-04.md` + roadmap |
| Mobile completion state | 6 overlapping sprint/audit/roadmap docs | `mobile-completion-sprint-final-report.md` |
| WhatsApp cleanup | 5 docs (audit → note → debt → readiness) | `whatsapp-cleanup-v1-production-readiness.md` |
| Treatment Plans freeze | audit + implementation plan | `treatment-plans-v1-freeze-implementation-plan.md` |
| CEO product roadmap | Directive #003 (2 formats) | Superseded entirely — current directive lives in project memory, not docs/ |
| Architecture planning/timeline | 6 overlapping blueprint/timeline docs | `ARCHITECTURE.md` + `target-architecture-engine-first.md` |
| Production readiness status | 4 pre-08-07 review docs | The 08-07 Final Go-Live Audit #4 (05_Implementation) |

**11 duplicate-topic clusters eliminated this pass.**

## 5. Topics Still Missing Documentation

(Carried forward from the prior pass, still true)

- Per-role printed user manuals (in-app Help Centre exists; no standalone handbook)
- A dedicated backup/restore runbook as its own document (currently steps live inside the Master Implementation Checklist tasks 26-29)
- A single current `.env` variable reference document (env vars are being consolidated into `.env.example` as Checklist Task 6, not yet a docs/ page)
- Flutter/Android documentation (the app's source is not in this repository; no docs to consolidate here)
- V1.1 register (intentionally does not exist yet — opens only at Master Implementation Checklist Task 131, per the CEO Implementation Directive's change-control rule)

## 6. Safety Report

- **Scope respected: only files under `docs/` were touched.** Zero files moved, renamed, or modified outside `docs/`.
- **Zero deletions.** Every archived file was moved with a non-clobbering operation (skip-on-conflict) into a sibling `Archive/` folder — recoverable, browsable, still in the repository.
- **Zero overwrites.** No destination file was replaced; any naming collision would have been skipped and logged (none occurred).
- **Zero modification of file contents.** Every moved file is byte-identical to before the move; only READMEs and this index are new content.
- **Laravel code: untouched.** No file under `app/`, `bootstrap/`, `config/`, `database/`, `routes/`, `resources/`, `tests/` was touched.
- **Flutter code: not present in this repository — nothing to touch.**
- **Runtime configuration: untouched.** `.env*`, `docker-compose*`, `Dockerfile`, `composer.json/.lock`, `package.json` untouched.
- **Git history: untouched.** No commits, resets, or history rewrites were performed — all changes are working-tree file moves awaiting your own commit (see 00_Master/DOCUMENTATION_LIBRARY.md for the two-commit recipe and the pre-existing `.git/index.lock` note from the prior pass).
- **Path-pinned documentation deliberately left alone:** `DEPLOY.md` (root) and the 9 docs/ files referenced directly from application code comments, plus the legacy `abdm/`, `architecture/`, `archive/`, `competitive/`, `deploy/`, `feature-specs/`, `phase-0` through `phase-7/`, `prototypes/`, `security/` folders — none of these were reorganized in either pass, by design, because code references their exact paths.

✔ No application files touched · ✔ No code modified · ✔ No runtime changes · ✔ No deletions · ✔ Documentation only.
