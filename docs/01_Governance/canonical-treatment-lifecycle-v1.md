# Dentfluence — Canonical Treatment Lifecycle V1

**Status:** ARCHITECTURAL CONTRACT · FROZEN
**Authority:** CEO Directive #008, 2026-08-04
**Supersedes:** every workflow assumption in prior audits, plans and slice documents.

---

## 0. How to use this document

This document defines **how Dentfluence works**. It is not a description of the current codebase.

Three rules govern its use:

1. **This document wins.** Where implementation differs, the implementation is wrong — not this document. Divergences are listed in §14 as observations only.
2. **Every module must declare itself against this contract** before it is built, changed or frozen. A module that cannot state its Purpose, Owner, Input, Output, Canonical Truth and Must-Never-Own in these terms is not ready to be built.
3. **One owner per stage. One writer per fact.** Every rule in this document reduces to that sentence. When a design question arises, the answer is whichever option preserves it.

The lifecycle exists because a dental practice is a chain of **promises and facts**. A treatment plan is a promise. A treatment visit is a fact. An invoice is a financial consequence of a fact. Most clinic software corrupts itself by letting a promise masquerade as a fact, or a financial event rewrite a clinical one. Dentfluence's architecture exists to make that impossible.

---

## 1. The canonical lifecycle

```
Patient
  ↓
Consultation
  ↓
Diagnosis
  ↓
Treatment Plan            ← a promise (estimate, options)
  ↓
Presentation
  ↓
Opportunity Pipeline      ← commercial follow-up of the promise
  ↓
Patient Decision
  ↓
Accepted Treatment Plan   ← authorization to treat
  ↓
Treatment Visit
  ↓
Clinical Execution        ← a fact (what was actually done)
  ↓
Billing                   ← financial consequence of the fact
  ↓
Payment
  ↓
Completion
  ↓
Recall
```

**The three irreversible boundaries.** Crossing them in the wrong direction is an architecture violation, not a bug:

| Boundary | Rule |
|---|---|
| Promise → Fact | Nothing downstream of Clinical Execution may write anything upstream of it. |
| Fact → Money | Money is derived from facts. Facts are never derived from money. |
| Commerce ⇄ Clinical | The Opportunity Pipeline observes the clinical chain. It never participates in it. |

---

## 2. Consultation

**Purpose.** To capture why the patient came, what was found, and what it means clinically. It is the origin of every clinical record and the only legitimate source of diagnosis.

**Owner.** Consultation.

**Input.** A registered Patient; presenting complaint; clinical examination; investigations (radiographs, charts, photographs).

**Output.** A recorded consultation containing one or more **diagnoses**, each attributable to a tooth, region, or the patient as a whole.

**Canonical Truth owned here.**
- Chief complaint and history
- Clinical examination findings
- Charting observations captured at examination
- **Diagnosis** — the clinical interpretation
- Attending clinician and date of consultation

**Must never own.**
- Treatment options or pricing — those belong to Treatment Plan.
- Work performed — a consultation observes; it does not treat.
- Any commercial state (interest, follow-up, likelihood of conversion).
- Billing of any kind.

**Contract.** Diagnosis is written once, by Consultation, and is read-only everywhere downstream. A Treatment Plan may cite a diagnosis; it may never create or amend one. Where a clinician revises their opinion, that is a **new or amended consultation record**, not an edit made from a downstream module.

---

## 3. Diagnosis

Diagnosis is not a separate module. It is the **output artefact of Consultation** and is named separately in the lifecycle because it is the hand-off point between observation and proposal.

**Owner.** Consultation.

**Contract.** Diagnosis is the *only* legitimate justification for a Treatment Plan. A plan proposed without a diagnosis is commercially and clinically ungrounded; the architecture treats diagnosis as the required input to plan creation, not an optional attachment.

---

## 4. Treatment Plan

**Purpose.** To communicate to the patient what can be done, in what ways, at what cost — and to record what the patient decided. It is a **communication document and an estimate**. It is the clinic's promise, not its record of work.

**Owner.** Treatment Plan.

**Input.** One or more diagnoses from Consultation; treatment and pricing definitions from the Treatments knowledge base.

**Output.** One or more costed, presentable treatment options; and — after the patient responds — a recorded patient decision that either does or does not authorize treatment.

**Canonical Truth owned here.**
- The proposed treatments, teeth, quantities and sequence
- Estimated pricing at the time of proposal
- Multiple options (best / acceptable / alternative) as parallel proposals
- **Presentation fact** — that this plan was shown to the patient, and when (written once, never rewritten)
- **Patient decision record** — accept / partially accept / defer / reject, append-only, with the item-level breakdown of a partial acceptance
- Plan lifecycle state (§12)

**Must never own.**
- **Billing.** A plan is not an invoice and is never the source of one. It carries estimates, not receivables.
- **Clinical execution.** A plan never records that work was done. "Planned" and "performed" are different universes.
- **A financial ledger.** No payments, balances, dues or revenue recognition.
- **Diagnosis.** It consumes diagnosis; it never authors it.
- **Commercial follow-up state.** Chasing the patient is the Opportunity Pipeline's job.

**Contract.**
- A plan is **authorization, not execution**. Its accepted state grants permission for Treatment Visits to occur; it never asserts that they have.
- A plan may propose six teeth and have two treated. The plan does not shrink, complete, or self-adjust when that happens. It remains the promise it always was.
- **Presentation is a clinical communication fact**, immutable once recorded. Re-showing a plan does not create a new first presentation.
- **The decision record is append-only.** A patient who defers and later accepts has two decisions in their history, both permanently true at their moment. History is never rewritten, never deleted, never collapsed.

---

## 5. Presentation

Presentation is not a separate module. It is a **transition owned by Treatment Plan** and is named in the lifecycle because it is the trigger that starts commercial follow-up.

**Owner.** Treatment Plan.

**Contract.** Presentation means *the clinic has shown this plan to the patient*. It is stamped once, by the act of the clinic presenting or sending it. On presentation, and only then, an Opportunity comes into existence. Presentation is never inferred from downstream commercial state; the Opportunity is the consequence of presentation, never the evidence for it.

---

## 6. Opportunity Pipeline

**Purpose.** To ensure that a presented estimate is followed up until the patient decides. It exists so that no quoted treatment is silently forgotten — the single largest source of lost revenue in a dental practice.

**Owner.** Opportunity Pipeline.

**Input.** The fact that a Treatment Plan was presented; subsequently, the patient's decision.

**Output.** Follow-up activity, chase scheduling, and commercial reporting (quoted value, conversion rate, pipeline stage).

**Canonical Truth owned here.**
- Pipeline stage and its history
- Follow-up scheduling and ownership (who chases, when)
- Commercial notes and outreach activity
- Conversion metrics derived from the above

**Must never own.**
- **Treatment.** It never authorizes, records or modifies clinical work.
- **Billing.** It never creates, alters or reconciles an invoice.
- **The patient's decision.** The decision belongs to Treatment Plan; the pipeline *reflects* it.
- **Presentation truth.** It is downstream of presentation and can never be its source.

**Contract.**
- The pipeline is a **mirror, not a master**. Every stage it holds is the shadow of a fact owned elsewhere: presented (Plan), decided (Plan), treatment started (Visit).
- Exactly **one Opportunity per presented Treatment Plan**, for the plan's whole life.
- The pipeline may be reconstructed at any time from the facts owned upstream. If it ever cannot be, it has been allowed to own something it must not.
- **Acceptance is commitment, not conversion.** The patient agreeing is not the same event as treatment beginning, and the pipeline must distinguish them.

---

## 7. Patient Decision

Patient Decision is not a separate module. It is a **fact owned by Treatment Plan**, named in the lifecycle because it is the gate between commerce and clinic.

**Owner.** Treatment Plan.

**Contract.**
- Four decisions exist: **accept, partially accept, defer, reject.** A partial acceptance carries an item-level breakdown, because "yes to the crown, not yet to the implant" is a single decision about several treatments.
- The decision is recorded wherever it is made — chairside, by phone, or by the patient on a link the clinic sent. **The channel is not the truth; the decision is.** Every channel writes to the same record through the same door.
- **Acceptance authorizes treatment. Nothing else does.** A Treatment Visit that cannot trace itself to an accepted plan is unauthorized clinical work.
- Reversing an acceptance is itself a recorded decision, never an erasure of the original.

---

## 8. Treatment Visit

**Purpose.** To record what was actually done to the patient. This is the clinical record — the fact against which everything downstream is justified.

**Owner.** Treatment Visit.

**Input.** An accepted Treatment Plan (authorization); the attending clinician; the patient's attendance.

**Output.** A permanent record of clinical work performed, per tooth, per treatment, per visit — and the billable basis derived from it.

**Canonical Truth owned here.**
- **What was actually performed** — the work outcome, per planned item and per tooth
- **When** it was performed
- **By whom** it was performed
- **On which teeth** it was performed
- Clinical notes, findings and materials used during execution
- **Clinical progress** — derived from the above and from nowhere else

**Must never own.**
- **Diagnosis.** A visit executes against a diagnosis; it does not author one. New findings during treatment are a new clinical observation, routed to Consultation's authority.
- **Pricing.** It records what was done, not what it costs.
- **The plan.** It never edits, completes or closes the Treatment Plan. It reports facts; the plan's lifecycle is the plan's own.
- **Payment or receivables.**
- **Commercial stage.**

**Contract.**
- **A Treatment Visit is authorized only by an accepted Treatment Plan.** No acceptance, no visit.
- **Partial execution is normal and expected.** A plan of six teeth treated two at a time is the ordinary case, not an exception. The visit records two; the other four remain planned and untouched.
- **Clinical progress has exactly one derivation.** It is computed from recorded work outcomes. No status column, billing state, or commercial stage may ever be read as clinical progress, and no second derivation may exist.
- **Only performed work becomes billable.** The visit defines the billable set. Nothing else may.
- Correcting a visit record corrects clinical history and must be traceable; clinical facts are amended with a trail, never silently replaced.

---

## 9. Clinical Execution

Clinical Execution is not a separate module. It is **what a Treatment Visit records** and is named in the lifecycle to mark the exact point at which a promise becomes a fact.

**Owner.** Treatment Visit.

**Contract.** This is the boundary of §1. Everything before it is proposal; everything after it is consequence. No module downstream of this point may write anything upstream of it.

---

## 10. Billing

**Purpose.** To convert performed clinical work into a financial claim on the patient.

**Owner.** Billing.

**Input.** Work actually performed, as recorded by Treatment Visit; pricing as agreed on the accepted Treatment Plan.

**Output.** An invoice covering that performed work, and the receivable it creates.

**Canonical Truth owned here.**
- Invoices and invoice lines
- Amounts, taxes, discounts, coupons and adjustments
- Which performed work has been invoiced, and which has not
- Receivable status (raised, outstanding, settled, cancelled)

**Must never own.**
- **Clinical truth.** Billing may not decide, imply or amend what was done to the patient.
- **Clinical progress or plan completion.** Whether treatment is finished is a clinical determination, never a financial one. A fully-invoiced plan is not thereby a completed one.
- **The decision to treat.**
- **Commercial pipeline stage.**

**Contract.**
- **Billing originates from Treatment Visits.** Performed work is the only legitimate basis for an invoice in the normal workflow.
- **Treatment Plans are never billed as part of the normal workflow.** A plan is an estimate. Any mechanism that bills a plan directly is, by definition, outside the canonical flow and must be treated as administrative.
- **Every billable item traces to a performed item.** An invoice line that cannot name the clinical work it bills is unsupported.
- **Performed work is invoiced exactly once**, and the system must be able to state, at any moment, which performed work is billed and which is not.
- **Invoice cancellation is an administrative correction, not a workflow step.** Its legitimate causes are wrong patient, wrong amount, wrong treatment selected, or a billing error. Because it is a correction, it must return the system to the state that preceded the error: the work it billed becomes un-invoiced and billable again, and any financial consequence billing itself caused is undone. Cancellation must never alter the clinical record — the work was still performed.

---

## 11. Payment

**Purpose.** To record money received against a receivable.

**Owner.** Payment.

**Input.** An invoice; a payment tendered by or on behalf of the patient.

**Output.** A settled or partly settled receivable; a receipt; a financial transaction record.

**Canonical Truth owned here.**
- Payments received, their mode, date and amount
- Receipts issued
- Refunds, wallet credits and their financial consequences
- Settlement status of a receivable

**Must never own.**
- **Clinical truth.** An unpaid treatment was still performed. A paid one is not thereby clinically complete.
- **The invoice's contents.** Payment settles a claim; it does not define it.
- **Treatment Plan lifecycle.**
- **Commercial pipeline stage.**

**Contract.** Payment is the terminal financial event and writes nothing outside its own domain. **Non-payment is a financial state, never a clinical one** — a patient who owes money has still received their treatment, and every clinical record must continue to say so.

---

## 12. Completion

**Purpose.** To state that the treatment the patient accepted has been delivered.

**Owner.** Treatment Plan (the state), determined by Treatment Visit (the facts).

**Contract.**
- Completion is a **clinical determination**. It is true when the accepted work has been performed — as recorded by Treatment Visits — and at no other time.
- **Billing may not complete a plan.** Full invoicing is a financial coincidence, not clinical delivery.
- **Completion is scoped to what the patient accepted.** A plan of six teeth where the patient accepted two is complete when those two are delivered. Deferred and rejected items do not hold a plan open.
- A completed plan is a closed promise. It stops authorizing new Treatment Visits.

---

## 13. Recall

**Purpose.** To bring the patient back at the right time — the mechanism by which a practice compounds rather than churns.

**Owner.** Recall.

**Input.** Completion of accepted treatment; or the passage of time since the patient's last clinical contact.

**Output.** A scheduled future contact, and the follow-up activity that pursues it.

**Canonical Truth owned here.**
- When the patient is next due
- Why they are due (post-treatment review, periodic recall, treatment-specific follow-up)
- Recall contact attempts and their outcomes

**Must never own.**
- **Clinical truth** — it reads the clinical record, never writes it.
- **The Opportunity Pipeline's estimates.** Recall pursues *attendance*; Opportunity pursues *decisions on quoted work*. They are different pursuits and must not be merged.
- **Billing or payment.**

**Contract.** Recall is triggered by clinical completion and by elapsed time since real clinical contact. It must derive "last contact" from actual clinical events; a recall engine reading a field nobody maintains will chase the wrong patients and miss the right ones.

---

## 14. Ownership matrix

| Fact | Sole owner | May be read by |
|---|---|---|
| Patient identity | Patient | all |
| Diagnosis | Consultation | Treatment Plan, Treatment Visit |
| Proposed treatment & estimate | Treatment Plan | Opportunity, Treatment Visit, Billing |
| Presentation fact | Treatment Plan | Opportunity, reporting |
| Patient decision | Treatment Plan | Opportunity, Treatment Visit, reporting |
| Pipeline stage & follow-up | Opportunity | reporting |
| Work performed | Treatment Visit | Billing, Recall, reporting |
| Clinical progress | Treatment Visit (derived) | Treatment Plan, reporting |
| Plan lifecycle state | Treatment Plan | all |
| Invoice & receivable | Billing | Payment, reporting |
| Payment & receipt | Payment | reporting |
| Recall due date | Recall | reporting |

**Reading is free. Writing is exclusive.** Any module may read any fact. Only its owner may write it. A module that needs a fact changed asks its owner; it never reaches across the boundary.

---

## 15. Canonical state machine — Treatment Plan

```
        ┌─────────┐
        │  DRAFT  │  plan composed, patient has not seen it
        └────┬────┘
             │  present  ······································ Treatment Plan
             ▼
      ┌────────────┐
      │ PRESENTED  │  patient has seen it; Opportunity now exists
      └─────┬──────┘
            │  (automatic) ···································· Treatment Plan
            ▼
   ┌──────────────────┐
   │ DECISION PENDING │  awaiting the patient's answer
   └────┬───────┬─────┘
        │       │  reject / defer ····························· Treatment Plan
        │       └──────────────► DECLINED / DEFERRED ──► CLOSED
        │  accept / partially accept ························· Treatment Plan
        ▼
   ┌──────────┐
   │ ACCEPTED │  treatment is now authorized
   └────┬─────┘
        │  first Treatment Visit records performed work ······ Treatment Visit (fact)
        ▼                                                       Treatment Plan (state)
┌──────────────────┐
│ TREATMENT STARTED│
└────────┬─────────┘
         │  all accepted work performed ······················ Treatment Visit (fact)
         ▼                                                      Treatment Plan (state)
┌────────────────────┐
│ TREATMENT COMPLETE │
└─────────┬──────────┘
          │  close ·········································· Treatment Plan
          ▼
      ┌────────┐
      │ CLOSED │  no further visits authorized
      └────────┘
```

### Transition ownership

| # | Transition | Trigger | State written by | Fact established by |
|---|---|---|---|---|
| 1 | Draft → Presented | Clinic shows or sends the plan | Treatment Plan | Treatment Plan |
| 2 | Presented → Decision Pending | Automatic on presentation | Treatment Plan | Treatment Plan |
| 3 | Decision Pending → Accepted | Patient accepts (fully or partly) | Treatment Plan | Treatment Plan |
| 4 | Decision Pending → Deferred | Patient asks for time | Treatment Plan | Treatment Plan |
| 5 | Decision Pending → Declined | Patient rejects | Treatment Plan | Treatment Plan |
| 6 | Accepted → Treatment Started | First performed work recorded | Treatment Plan | **Treatment Visit** |
| 7 | Treatment Started → Treatment Complete | All accepted work performed | Treatment Plan | **Treatment Visit** |
| 8 | Any → Closed | Terminal | Treatment Plan | Treatment Plan |
| 9 | Accepted → Decision Pending | Acceptance reversed | Treatment Plan | Treatment Plan |

**The distinction in transitions 6 and 7 is the heart of this architecture.** Treatment Visit establishes the *fact* — work was performed. Treatment Plan writes the *state* — the plan has started, the plan is complete. The visit never writes the plan's state directly; the plan reacts to facts the visit owns. This is what keeps a single owner for plan lifecycle while keeping a single owner for clinical truth.

**Billing appears nowhere in this state machine.** No invoice, cancellation or payment causes any transition above. That absence is the contract.

### Prohibited transitions

- Draft → Accepted. A patient cannot accept what they were never shown.
- Presented → Treatment Started. Nothing is authorized without a decision.
- Declined/Deferred → Treatment Started. Unaccepted work is never performed.
- Closed → anything. A closed plan is terminal; new work needs a new plan.
- Any transition caused by an invoice, a cancellation, or a payment.
- Any transition that erases a prior decision from the record.

---

## 16. Module interaction diagram

```
                          ┌───────────────┐
                          │    PATIENT    │
                          └───────┬───────┘
                                  │
                          ┌───────▼────────┐
                          │  CONSULTATION  │
                          │  owns: diagnosis│
                          └───────┬────────┘
                                  │ diagnosis
                          ┌───────▼────────────────┐
        pricing ─────────►│    TREATMENT PLAN      │
     (Treatments KB)      │ owns: estimate,        │
                          │ presentation, decision,│
                          │ plan lifecycle         │
                          └───┬───────────────┬────┘
                              │               │
              presented fact  │               │ acceptance = authorization
                              ▼               ▼
                  ┌────────────────────┐  ┌──────────────────────┐
                  │ OPPORTUNITY        │  │  TREATMENT VISIT     │
                  │ PIPELINE           │  │  owns: work performed│
                  │ owns: stage,       │  │  when, by whom,      │
                  │ follow-up          │  │  which teeth,        │
                  │                    │  │  clinical progress   │
                  └────────┬───────────┘  └──────────┬───────────┘
                           │                         │
             decision fact ┘                         │ performed work
             (read from Plan)                        ▼
                                              ┌──────────────┐
                                              │   BILLING    │
                                              │ owns: invoice│
                                              │ receivable   │
                                              └──────┬───────┘
                                                     │ receivable
                                              ┌──────▼───────┐
                                              │   PAYMENT    │
                                              │ owns: money  │
                                              │ received     │
                                              └──────────────┘

                          ┌────────────────┐
                          │   COMPLETION   │◄──── clinical facts (Treatment Visit)
                          │ state on Plan  │
                          └───────┬────────┘
                                  │ completion
                          ┌───────▼────────┐
                          │     RECALL     │
                          │ owns: next due │
                          └───────┬────────┘
                                  │
                                  └────────────► back to CONSULTATION
```

### Dependency register

| From | To | Nature | Direction |
|---|---|---|---|
| Consultation | Treatment Plan | diagnosis justifies proposal | read-only downstream |
| Treatments KB | Treatment Plan | pricing definitions | read-only |
| Treatment Plan | Opportunity | presentation creates it | fact → mirror |
| Treatment Plan | Opportunity | decision updates it | fact → mirror |
| Treatment Plan | Treatment Visit | acceptance authorizes | authorization |
| Treatment Visit | Treatment Plan | performed work drives lifecycle state | fact → state |
| Treatment Visit | Billing | performed work is the billable set | fact → money |
| Treatment Plan | Billing | agreed pricing | read-only |
| Billing | Payment | receivable | money → money |
| Treatment Visit | Completion | delivery of accepted work | fact → state |
| Completion | Recall | triggers next due date | state → schedule |
| Recall | Consultation | returns the patient | cycle |

**Every arrow flows forward.** The only backward arrow in the entire architecture is Recall → Consultation, which begins a new cycle rather than editing an old one. Any proposed dependency that points backwards is an architecture violation.

---

## 17. Noted divergences in the current implementation

Recorded as **observations only**, per Directive #008. No redesign, no remedy, no priority is proposed here.

| # | Canonical rule | Observed in implementation |
|---|---|---|
| D1 | Billing may not complete a plan (§10, §12) | Plan completion is written by a billing event when all items are invoiced. |
| D2 | Plan lifecycle state has one writer (§15) | A second writer sets plan completion directly from a visit form control. |
| D3 | Billing originates from Treatment Visits (§10) | A parallel path bills a Treatment Plan directly. |
| D4 | Every billable item traces to performed work (§10) | The plan-billing path derives billable items from planned teeth rather than performed work. |
| D5 | Cancellation restores the pre-error state (§10) | Work marked invoiced on the visit path is not returned to billable when its invoice is cancelled, and no linkage records which invoice billed it. |
| D6 | Presentation is a clinical fact stamped by the clinic (§5) | Presentation can also be stamped by an unauthenticated fetch of a patient link. |
| D7 | The patient's decision is recorded wherever made (§7) | One patient-facing decline path updates commercial state without writing the decision record. |
| D8 | Acceptance is commitment, not conversion (§6) | No event marks treatment start on the pipeline. |
| D9 | Opportunity is a mirror, never a master (§6) | Reversing an acceptance leaves the pipeline in its committed state. |
| D10 | Clinical progress has exactly one derivation (§8) | Several reporting surfaces read plan or item status columns as though they were clinical progress. |
| D11 | Recall derives from real clinical contact (§13) | Recall reads a last-contact field that has no maintaining writer. |

---

## 18. The contract in one page

1. **One owner per stage. One writer per fact.** Everything else follows from this.
2. **A Treatment Plan is a promise.** It communicates, estimates, offers options, and records the patient's answer. It is never billing, never clinical execution, never a ledger.
3. **An Opportunity is commercial follow-up of a promise.** It begins at presentation and never touches treatment or money.
4. **Acceptance is the only authorization to treat.**
5. **A Treatment Visit is a fact.** What was done, when, by whom, on which teeth. It is the sole source of clinical truth and clinical progress.
6. **Only performed work is billable.** Billing originates from visits; plans are not billed in the normal workflow.
7. **Money never rewrites clinical truth.** Not invoicing, not cancellation, not payment, not non-payment.
8. **Invoice cancellation is an administrative correction** and must restore the state that preceded the error — financially, never clinically.
9. **Completion is clinical**, scoped to what the patient accepted.
10. **History is never erased.** Decisions, presentations and clinical facts accumulate; they are never rewritten to make the present tidier.

---

**This document is the permanent architectural reference for Dentfluence. Implementation resumes only in conformance with it. Any module, slice or fix that cannot be stated in the vocabulary of this contract is not ready to be built.**
