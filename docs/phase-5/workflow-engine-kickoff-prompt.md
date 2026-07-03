# Workflow Engine — Kickoff Prompt (paste this into a NEW chat)

This is a reusable prompt. Paste the whole thing (everything below the line)
into a fresh chat to build the Workflow Engine one slice at a time. It's
written to be pasted again for slice 2, slice 3, etc. — each time, Claude
should read the plan + this session's memory to see what's already built
and pick up the next unbuilt slice.

---

I want to build the Dentfluence Workflow Engine — the last piece of Phase 5
of the Relationship Platform rebuild. Before writing any code:

1. Read `docs/phase-5/workflow-engine-proposal.md` in full — it's the sized
   plan for this (schema sketch, class shape, slice breakdown, guardrails).
   It was written in a prior session specifically so this one didn't have
   to start from scratch.
2. Check your memory for `project_phase5_prm_marketing` and
   `project_phase5_workflow_engine` (if it exists yet) to see what's
   already built vs still open.
3. Check `config/features.php` for the `workflow.engine` flag — it's
   already declared (default off), don't redeclare it.

**Build ONE slice per chat session, not the whole thing at once.** The
proposal doc lays out 5 slices:

1. Engine core (`WorkflowEngine` class, 3 migrations, one seeded template
   `rct_staging`, flag-gated, dormant — no callers yet)
2. Shadow-run on RCT treatment visits (log-only parity check against what
   doctors already type into `current_stage`)
3. Surface it read-only on the patient Treatment tab
4. Cutover decision (this one needs my explicit sign-off before any
   behaviour changes — it's a product call, not a coding one)
5. (Optional) a second template, e.g. Implant staging

Stop at the end of whichever slice you build and tell me clearly: what's
done, what's not migrated/tested yet (I run all `php artisan` commands
myself — you can't type into my terminal), and what the next slice would
be. Update your memory and `docs/phase-5/` before stopping so the next
chat (which will be a completely fresh session with no memory of this one
beyond what you save) can pick up correctly.

**Standing rules for this project** (from CLAUDE.md, repeating the
important ones so you don't have to search for them):
- Read existing files before changing them.
- Migration + model + service/controller + routes + view together for any
  real feature — but slice 1 here is deliberately dormant scaffolding, so
  "together" just means the migrations + engine class + seeded template in
  one slice, no UI yet.
- Never delete files without asking first.
- Never run `migrate:fresh` or `rollback` without asking first.
- Additive migrations only — nothing existing changes shape.
- Every behaviour change stays behind the `workflow.engine` flag
  (default off) until I explicitly ask for a cutover.
- I'm a solo builder — explain what you're doing in plain language.
- If a task looks like it'll produce a large output, flag that up front
  and ask whether to proceed or split it further, rather than risking a
  cut-off mid-way.

**One more open thread from Phase 5, not part of the Workflow Engine but
worth knowing about:** `config/features.php` already declares a
`marketing.via_guard` flag ("Marketing sends pass through the
Communication Guard") that nothing implements yet. The WhatsApp marketing
fix done in the prior session only made WhatsApp marketing posts fail
honestly instead of lying about publishing — it did NOT route marketing
sends through the Guard. That's a separate, smaller loose end from the
same blueprint phase; mention it to me if it comes up, but it's not part
of the Workflow Engine slices above unless I ask for it.

Start with whichever slice is next given what memory says is already
built. If nothing's built yet, start with Slice 1.
