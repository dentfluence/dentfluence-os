# Decision Log (foundation)

The Decision Log makes every automation decision **explainable** — "why wasn't this patient contacted?". It is owned by the Rules Engine and is distinct from the Activity ledger (which records what happened to the *patient*; the Decision Log records what the *brain decided and why*).

## Phase 0 scope

**Infrastructure only.** Phase 0 provides:

- **Schema** — `relationship_rule_logs` extended with nullable columns: `inputs (json)`, `conditions (json)`, `result`, `decision`, `requesting_engine`, `user_id`.
- **Model** — `App\Models\RelationshipRuleLog`.
- **Writer** — `App\Services\Relationship\DecisionLogRecorder::record(...)` (never throws).

**RulesEngine is intentionally NOT modified.** "Do not migrate rules yet." Phase 2 will have the Rules Engine call `DecisionLogRecorder::record()` on every evaluation.

## Record shape (for Phase 2 reference)

```php
$recorder->record(
    ruleName:         'recall.six_month',
    result:           'matched',                 // matched | not_matched
    decision:         'suppressed:frequency',    // what was requested / why not
    requestingEngine: 'rules',                   // rules | automation | workflow
    relationshipId:   $relationshipId,
    inputs:           [...],                      // the event/data seen
    conditions:       [...],                      // each condition + outcome
    userId:           $userId,                    // if a human was involved
);
```

The columns are all nullable and additive; existing rows and existing writers are unaffected.
