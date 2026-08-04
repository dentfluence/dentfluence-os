<?php

namespace App\Services\Patient;

use App\Domain\Events\DomainEventBus;
use App\Domain\Events\Patient\PatientMerged;
use App\Domain\Events\Patient\PatientMergeUndone;
use App\Models\Patient;
use App\Models\PatientMerge;
use App\Models\Relationship;
use App\Models\RelationshipMerge;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Relationship\ActivityEngine;
use App\Services\Relationship\MergeService as RelationshipMergeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PatientMergeService — Phase 1, slice 2.
 *
 * Atomically merges a DUPLICATE (loser) patient into a SURVIVING (master) one:
 *   1. re-parents all patient_id children + money ledgers (manifest-driven),
 *   2. applies the special-entity rules (wallet, membership, ABHA, tag/family
 *      pivots) that a blind column move would corrupt,
 *   3. delegates the relationship_id cascade to Relationship\MergeService
 *      (reuse, not rebuild) — its RelationshipMerge is linked from ours,
 *   4. archives the loser (soft-delete + merged_into redirect + retired-ID alias),
 *   5. records a reversible PatientMerge manifest.
 *
 * The whole operation runs in ONE transaction with both patient rows locked:
 * any failure rolls everything back — no partial, no silent merge. Coverage of
 * every table is proven by `patients:merge-coverage` against PatientMergeManifest.
 *
 * SAFETY-NET UNDO (Final Design §1): merge() also records everything undo()
 * needs to reverse itself — but undo is deliberately bounded, not a general
 * reversal engine. It is refused outright (never partial, never best-effort)
 * unless BOTH hold: (1) within config('patients.merge_undo_window_minutes')
 * of the merge, and (2) zero activity has touched the surviving patient since.
 * A merge older than the window, or one the master has since transacted
 * against, is not undoable through this service — see docs/patients-module-*
 * and the Duplicate Merge Final Design for why an unconditional undo across
 * 40+ tables was rejected (post-merge activity cannot be cleanly unwound).
 */
class PatientMergeService
{
    /** Demographic fields the reconciliation step is allowed to overwrite on the master. */
    private const RECONCILABLE_FIELDS = [
        'title', 'first_name', 'middle_name', 'last_name', 'name', 'gender',
        'dob', 'date_of_birth', 'age_years', 'dob_unknown',
        'email', 'phone', 'alternate_phone',
        'address', 'area', 'city', 'state', 'pincode', 'occupation', 'source', 'medical_alert',
    ];

    /** Safety-critical arrays that are UNIONED (never lose an allergy on merge). */
    private const UNION_FIELDS = ['allergies', 'medical_conditions', 'dental_conditions'];

    public function __construct(
        private readonly RelationshipMergeService $relationshipMerge,
        private readonly ActivityEngine $activity,
        private readonly DomainEventBus $bus,
    ) {
    }

    /**
     * Read-only preview: how many rows would move, per table. Powers the CLI
     * --dry-run and (slice 3) the wizard's "records to be moved" screen.
     *
     * @return array{children:array,money:array,special:array,relationship:int,total:int}
     */
    public function preview(Patient $loser): array
    {
        $count = function (string $table) use ($loser): int {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'patient_id')) {
                return 0;
            }
            return (int) DB::table($table)->where('patient_id', $loser->id)->count();
        };

        $children = [];
        foreach (PatientMergeManifest::CHILD_TABLES as $t) {
            if ($n = $count($t)) $children[$t] = $n;
        }
        $money = [];
        foreach (PatientMergeManifest::MONEY_TABLES as $t) {
            if ($n = $count($t)) $money[$t] = $n;
        }
        $special = [];
        foreach (PatientMergeManifest::SPECIAL_TABLES as $t) {
            if ($n = $count($t)) $special[$t] = $n;
        }

        $relationship = 0;
        if ($loser->relationship_id) {
            $relationship = (int) DB::table('activities')
                ->where('relationship_id', $loser->relationship_id)->count();
        }

        $total = array_sum($children) + array_sum($money) + array_sum($special);

        return compact('children', 'money', 'special', 'relationship', 'total');
    }

    /**
     * Execute the merge. Returns the audit record.
     *
     * @param  array<string,mixed>  $fieldChoices  winning value per conflicting field (default: master keeps its own)
     * @throws \InvalidArgumentException|\RuntimeException on unsafe input (same record, already merged, ABHA clash)
     */
    public function merge(
        Patient $master,
        Patient $loser,
        array $fieldChoices = [],
        ?int $userId = null,
        string $reason = 'manual',
    ): PatientMerge {
        if ($master->id === $loser->id) {
            throw new \InvalidArgumentException('Cannot merge a patient into itself.');
        }
        if ($master->isMerged() || $loser->isMerged()) {
            throw new \InvalidArgumentException('One of these records was already merged. Refresh and try again.');
        }

        $record = DB::transaction(function () use ($master, $loser, $fieldChoices, $userId, $reason) {
            // Lock both rows for the duration of the merge.
            $master = Patient::whereKey($master->id)->lockForUpdate()->firstOrFail();
            $loser  = Patient::whereKey($loser->id)->lockForUpdate()->firstOrFail();

            // Safety gate BEFORE any write: never merge two verified health IDs.
            $this->assertNoIdentityClash($master, $loser);

            // Safety gate: never silently drop a guardianship by collapsing a
            // guardian and their ward into one record (Patients Phase 3, Slice 1).
            $this->assertNoGuardianWardCollapse($master, $loser);

            $snapshot         = $loser->getAttributes();      // raw (encrypted) values — no PHI decrypted into the log
            $retiredPatientId = $loser->patient_id;
            $masterBefore     = $master->getAttributes();     // pre-merge master state, for a future un-merge

            // 1. Demographic reconciliation + safety-array union.
            $this->reconcileFields($master, $loser, $fieldChoices);

            // 2. Blind re-parent: children + money ledgers.
            $reassignments = [];
            foreach (array_merge(PatientMergeManifest::CHILD_TABLES, PatientMergeManifest::MONEY_TABLES) as $table) {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'patient_id')) {
                    continue;
                }
                $ids = DB::table($table)->where('patient_id', $loser->id)->pluck('id')->all();
                if ($ids) {
                    DB::table($table)->whereIn('id', $ids)->update(['patient_id' => $master->id]);
                    $reassignments[$table] = $ids;
                }
            }

            // 3. Special-entity rules (each returns what it changed, for un-merge readiness).
            $walletTransfer = $this->mergeWallet($master, $loser);
            $membershipRev  = $this->mergeMemberships($master, $loser);
            $identifierRev  = $this->moveIdentifiers($master, $loser);
            $tagRev         = $this->mergeTagPivot($master, $loser);
            $linkRev        = $this->mergeFamilyLinks($master, $loser);

            // 4. Delegate the relationship_id cascade (reuse existing engine).
            $relationshipMergeId = $this->mergeRelationship($master, $loser, $userId);

            // 5. Archive the loser (soft-delete + redirect pointer + retired ID).
            $loser->merged_into_id = $master->id;
            $loser->merged_at      = now();
            $loser->merged_by      = $userId;
            $loser->saveQuietly();
            $loser->delete(); // SoftDeletes

            // 6. Record the reversible manifest.
            $record = PatientMerge::create([
                'surviving_patient_id'  => $master->id,
                'merged_patient_id'     => $loser->id,
                'relationship_merge_id' => $relationshipMergeId,
                'reason'                => $reason,
                'field_choices'         => $fieldChoices,
                'reassignments'         => $reassignments,
                'wallet_transfer'       => $walletTransfer,
                'retired_patient_id'    => $retiredPatientId,
                'snapshot'              => $snapshot,
                'reversal'              => array_merge(
                    ['master_before' => $masterBefore],
                    $membershipRev,
                    $identifierRev,
                    $tagRev,
                    $linkRev,
                ),
                'merged_by'             => $userId,
            ]);

            // 7. Journey Timeline entry — canonical ActivityEngine feed (no new tab).
            $this->activity->log(
                $master,
                'patient.merged',
                $userId ? User::find($userId) : null,
                ['merged_patient_id' => $loser->id, 'retired_patient_id' => $retiredPatientId, 'merge_record_id' => $record->id],
                $master->relationship_id,
                'Merged record '.($retiredPatientId ?: '#'.$loser->id)." ({$loser->name}) into this patient.",
            );

            return $record;
        });

        // Publish AFTER commit — subscribers (and the future un-merge) see a consistent state.
        $this->bus->publish(new PatientMerged(
            survivingPatientId: $master->id,
            mergedPatientId: $loser->id,
            mergeRecordId: $record->id,
            relationshipId: Patient::whereKey($master->id)->value('relationship_id'),
        ));

        return $record;
    }

    // ── Special rules ──────────────────────────────────────────────────────────

    /** Block the merge if both records carry a verified identifier of the same type (e.g. two ABHAs). */
    private function assertNoIdentityClash(Patient $master, Patient $loser): void
    {
        if (! Schema::hasTable('patient_identifiers')) {
            return;
        }
        $verified = fn (int $pid) => DB::table('patient_identifiers')
            ->where('patient_id', $pid)->where('status', 'verified')
            ->pluck('identifier_type')->all();

        $clash = array_intersect($verified($master->id), $verified($loser->id));
        if ($clash) {
            throw new \RuntimeException(
                'Both records have a verified '.implode(', ', array_unique($clash))
                .' identity. Resolve the health ID before merging — one patient can hold only one.'
            );
        }
    }

    /**
     * Block the merge if a direct guardianship link exists between the two
     * records. Merging a guardian and their ward into one person would collapse
     * that link into a self-link and silently remove the guardianship — which
     * merge must never do. The link must be resolved explicitly before merging.
     */
    private function assertNoGuardianWardCollapse(Patient $master, Patient $loser): void
    {
        if (! Schema::hasTable('patient_links') || ! Schema::hasColumn('patient_links', 'is_guardian')) {
            return;
        }

        $guardianLink = $this->scopeLinkPair(
            DB::table('patient_links')->where('is_guardian', true),
            $master->id,
            $loser->id
        )->exists();

        if ($guardianLink) {
            throw new \RuntimeException(
                'These two records are linked as guardian and ward. Remove that guardian link '
                .'before merging — a merge must never silently drop a guardianship.'
            );
        }
    }

    /**
     * Scope a patient_links query to the unordered pair {a, b} — a link in
     * EITHER direction. Uses explicit nested closures on purpose: the
     * associative-array form of `orWhere([...])` joins its keys with OR, not
     * AND, which would silently over-match. This is the single canonical way
     * this service targets a link between two patients.
     */
    private function scopeLinkPair($query, int $a, int $b)
    {
        return $query->where(function ($q) use ($a, $b) {
            $q->where(function ($w) use ($a, $b) {
                $w->where('patient_id', $a)->where('linked_patient_id', $b);
            })->orWhere(function ($w) use ($a, $b) {
                $w->where('patient_id', $b)->where('linked_patient_id', $a);
            });
        });
    }

    private function reconcileFields(Patient $master, Patient $loser, array $fieldChoices): void
    {
        foreach ($fieldChoices as $field => $value) {
            if (in_array($field, self::RECONCILABLE_FIELDS, true)) {
                $master->{$field} = $value;
            }
        }
        foreach (self::UNION_FIELDS as $field) {
            $merged = array_values(array_unique(array_merge(
                (array) ($master->{$field} ?? []),
                (array) ($loser->{$field} ?? []),
            )));
            $master->{$field} = $merged;
        }
        $master->save();
    }

    /** Sum the loser's wallet into the master's, re-point its ledger, delete the empty loser wallet. */
    private function mergeWallet(Patient $master, Patient $loser): ?array
    {
        if (! Schema::hasTable('wallets')) {
            return null;
        }
        $loserWallet = Wallet::where('patient_id', $loser->id)->first();
        if (! $loserWallet) {
            return null;
        }

        $masterWallet = Wallet::forPatientLocked($master->id);

        // wallet_transactions.patient_id already moved by the blind money step;
        // re-point their wallet_id to the surviving wallet too.
        DB::table('wallet_transactions')
            ->where('wallet_id', $loserWallet->id)
            ->update(['wallet_id' => $masterWallet->id]);

        $transfer = [
            'from_wallet_id' => $loserWallet->id,
            'to_wallet_id'   => $masterWallet->id,
            'promotional'    => (float) $loserWallet->balance_promotional,
            'permanent'      => (float) $loserWallet->balance_permanent,
            'total'          => (float) $loserWallet->balance_total,
        ];

        $masterWallet->balance_promotional += $loserWallet->balance_promotional;
        $masterWallet->balance_permanent   += $loserWallet->balance_permanent;
        $masterWallet->balance_total        = $masterWallet->balance_promotional + $masterWallet->balance_permanent;
        $masterWallet->save();

        $loserWallet->delete();

        return $transfer;
    }

    /**
     * Move memberships to the master; if more than one ends up active, keep the
     * latest-expiry and expire the rest. Returns the ids moved (for un-merge)
     * and the ids expired (for un-merge).
     */
    private function mergeMemberships(Patient $master, Patient $loser): array
    {
        $table = 'finance_patient_memberships';
        if (! Schema::hasTable($table)) {
            return ['memberships_moved' => [], 'memberships_expired' => []];
        }
        $movedIds = DB::table($table)->where('patient_id', $loser->id)->pluck('id')->all();
        if ($movedIds) {
            DB::table($table)->whereIn('id', $movedIds)->update(['patient_id' => $master->id]);
        }

        $actives = DB::table($table)
            ->where('patient_id', $master->id)
            ->where('status', 'active')
            ->orderByDesc('end_date')
            ->get();

        $expireIds = [];
        if ($actives->count() > 1) {
            $expireIds = $actives->slice(1)->pluck('id')->all(); // all but the latest-ending
            DB::table($table)->whereIn('id', $expireIds)->update(['status' => 'expired']);
        }

        return ['memberships_moved' => $movedIds, 'memberships_expired' => $expireIds];
    }

    /**
     * Move the loser's identifiers to the master; demote to non-primary if the
     * master already has a primary. Returns the ids moved and the ids demoted
     * (for un-merge — a demotion is not otherwise inferable after the fact).
     */
    private function moveIdentifiers(Patient $master, Patient $loser): array
    {
        if (! Schema::hasTable('patient_identifiers')) {
            return ['identifiers_moved' => [], 'identifiers_demoted' => []];
        }
        $ids = DB::table('patient_identifiers')->where('patient_id', $loser->id)->pluck('id')->all();
        if (! $ids) {
            return ['identifiers_moved' => [], 'identifiers_demoted' => []];
        }
        DB::table('patient_identifiers')->whereIn('id', $ids)->update(['patient_id' => $master->id]);

        $masterHasPrimary = DB::table('patient_identifiers')
            ->where('patient_id', $master->id)
            ->whereNotIn('id', $ids)
            ->where('is_primary', true)
            ->exists();

        $demoted = [];
        if ($masterHasPrimary) {
            $demoted = DB::table('patient_identifiers')
                ->whereIn('id', $ids)->where('is_primary', true)->pluck('id')->all();
            if ($demoted) {
                DB::table('patient_identifiers')->whereIn('id', $demoted)->update(['is_primary' => false]);
            }
        }

        return ['identifiers_moved' => $ids, 'identifiers_demoted' => $demoted];
    }

    /**
     * Tag pivot: drop rows that would collide with the master's tags, then move
     * the rest. Returns both the removed rows (to reinsert on un-merge) and the
     * moved row ids (to re-point back to the loser on un-merge).
     */
    private function mergeTagPivot(Patient $master, Patient $loser): array
    {
        if (! Schema::hasTable('patient_tag')) {
            return ['patient_tag_deleted' => [], 'patient_tag_moved' => []];
        }
        $masterTagIds = DB::table('patient_tag')->where('patient_id', $master->id)->pluck('tag_id')->all();
        $deleted = [];
        if ($masterTagIds) {
            $deleted = DB::table('patient_tag')
                ->where('patient_id', $loser->id)->whereIn('tag_id', $masterTagIds)
                ->get()->map(fn ($r) => (array) $r)->all();
            DB::table('patient_tag')
                ->where('patient_id', $loser->id)->whereIn('tag_id', $masterTagIds)
                ->delete();
        }
        $movedIds = DB::table('patient_tag')->where('patient_id', $loser->id)->pluck('id')->all();
        if ($movedIds) {
            DB::table('patient_tag')->whereIn('id', $movedIds)->update(['patient_id' => $master->id]);
        }

        return ['patient_tag_deleted' => $deleted, 'patient_tag_moved' => $movedIds];
    }

    /**
     * Family links: re-point both sides to the master and reconcile collisions
     * using the approved precedence rules (Patients Phase 3, Slice 1):
     *   - a duplicate pair (master and loser both link to the same counterpart,
     *     in EITHER direction) is merged onto the master's row —
     *       is_guardian       = master.is_guardian OR loser.is_guardian  (never dropped)
     *       relationship_type = the more specific value (a real type beats 'other')
     *       notes             = master's, else the loser's
     *   - direct master↔loser links become self-links and are removed (a guardian
     *     collapse is blocked up-front by assertNoGuardianWardCollapse).
     * Checking both directions keeps the re-point from violating the ordered-pair
     * unique index. Returns removed + reconciled + moved rows for un-merge readiness.
     */
    private function mergeFamilyLinks(Patient $master, Patient $loser): array
    {
        if (! Schema::hasTable('patient_links')) {
            return ['patient_links_deleted' => [], 'patient_links_reconciled' => [], 'patient_links_moved' => []];
        }

        $hasType     = Schema::hasColumn('patient_links', 'relationship_type');
        $hasGuardian = Schema::hasColumn('patient_links', 'is_guardian');
        $hasNotes    = Schema::hasColumn('patient_links', 'notes');

        $deleted    = [];
        $reconciled = [];
        $moved      = [];

        // Direct link(s) between the two records would become self-links — remove them.
        $direct = fn () => $this->scopeLinkPair(DB::table('patient_links'), $master->id, $loser->id);
        $deleted = array_merge($deleted, $direct()->get()->map(fn ($r) => (array) $r)->all());
        $direct()->delete();

        // Re-point / reconcile every remaining loser link on both sides.
        $this->reparentLinkSide($master, $loser, 'patient_id', 'linked_patient_id',
            $hasType, $hasGuardian, $hasNotes, $deleted, $reconciled, $moved);
        $this->reparentLinkSide($master, $loser, 'linked_patient_id', 'patient_id',
            $hasType, $hasGuardian, $hasNotes, $deleted, $reconciled, $moved);

        // Belt-and-braces: no self-links survive.
        $selfLinks = fn () => DB::table('patient_links')->whereColumn('patient_id', 'linked_patient_id');
        $deleted = array_merge($deleted, $selfLinks()->get()->map(fn ($r) => (array) $r)->all());
        $selfLinks()->delete();

        return ['patient_links_deleted' => $deleted, 'patient_links_reconciled' => $reconciled, 'patient_links_moved' => $moved];
    }

    /**
     * Move the loser's links on one side to the master. If the master already
     * links to the same counterpart (in EITHER direction), reconcile onto that
     * existing row using the precedence rules and drop the loser's duplicate;
     * otherwise re-point the loser's row to the master.
     *
     * @param  array<int,array>  $deleted     accumulates removed rows (by-ref)
     * @param  array<int,array>  $reconciled  accumulates {id,before,after} (by-ref)
     * @param  array<int,array>  $moved       accumulates {id,column} for un-collided re-points (by-ref)
     */
    private function reparentLinkSide(
        Patient $master,
        Patient $loser,
        string $moveCol,
        string $otherCol,
        bool $hasType,
        bool $hasGuardian,
        bool $hasNotes,
        array &$deleted,
        array &$reconciled,
        array &$moved,
    ): void {
        $loserRows = DB::table('patient_links')->where($moveCol, $loser->id)->get();

        foreach ($loserRows as $row) {
            $counterpart = $row->{$otherCol};

            // Any existing master↔counterpart link, in either direction.
            $existing = $this->scopeLinkPair(DB::table('patient_links'), $master->id, $counterpart)->first();

            if (! $existing) {
                DB::table('patient_links')->where('id', $row->id)->update([$moveCol => $master->id]);
                $moved[] = ['id' => $row->id, 'column' => $moveCol];
                continue;
            }

            // Reconcile the loser's duplicate onto the master's existing row.
            $update = [];
            if ($hasGuardian && (int) ($row->is_guardian ?? 0) === 1 && (int) ($existing->is_guardian ?? 0) === 0) {
                $update['is_guardian'] = true;
            }
            if ($hasType) {
                $keptType  = $existing->relationship_type ?? null;
                $loserType = $row->relationship_type ?? null;
                if (in_array($keptType, [null, 'other'], true) && ! in_array($loserType, [null, 'other'], true)) {
                    $update['relationship_type'] = $loserType;
                }
            }
            if ($hasNotes && empty($existing->notes) && ! empty($row->notes ?? null)) {
                $update['notes'] = $row->notes;
            }

            if ($update) {
                DB::table('patient_links')->where('id', $existing->id)->update($update);
                $reconciled[] = [
                    'id'     => $existing->id,
                    'before' => (array) $existing,
                    'after'  => array_merge((array) $existing, $update),
                ];
            }

            $deleted[] = (array) $row;
            DB::table('patient_links')->where('id', $row->id)->delete();
        }
    }

    /**
     * Delegate the relationship_id cascade to the existing engine. Returns the
     * RelationshipMerge id (or null when there's nothing to delegate).
     */
    private function mergeRelationship(Patient $master, Patient $loser, ?int $userId): ?int
    {
        $masterRelId = $master->relationship_id;
        $loserRelId  = $loser->relationship_id;

        if ($loserRelId && $masterRelId && $loserRelId !== $masterRelId) {
            $masterRel = Relationship::find($masterRelId);
            $loserRel  = Relationship::find($loserRelId);
            if ($masterRel && $loserRel) {
                return $this->relationshipMerge->merge($masterRel, $loserRel, $userId, 'patient_merge')->id;
            }
            return null;
        }

        // Master has no relationship but the loser does — the master adopts it
        // (all its relationship-keyed history is already correct).
        if ($loserRelId && ! $masterRelId) {
            $master->relationship_id = $loserRelId;
            $master->saveQuietly();
        }

        return null;
    }

    // ── Safety-net undo (Final Design §1) ───────────────────────────────────────

    /**
     * Whether $record can be undone right now, and why not if it can't. Powers
     * the profile-header "Undo" affordance — never throws.
     *
     * @return array{allowed:bool, reason:?string, minutes_left:int}
     */
    public function undoStatus(PatientMerge $record): array
    {
        if ($record->undone_at) {
            return ['allowed' => false, 'reason' => 'already_undone', 'minutes_left' => 0];
        }

        $master = Patient::withTrashed()->find($record->surviving_patient_id);
        if (! $master) {
            return ['allowed' => false, 'reason' => 'master_not_found', 'minutes_left' => 0];
        }

        $windowMinutes = (int) config('patients.merge_undo_window_minutes', 15);
        $minutesLeft   = $windowMinutes - $record->created_at->diffInMinutes(now());

        if ($minutesLeft <= 0) {
            return ['allowed' => false, 'reason' => 'window_expired', 'minutes_left' => 0];
        }
        if ($this->hasActivitySince($master, $record)) {
            return ['allowed' => false, 'reason' => 'activity_since_merge', 'minutes_left' => (int) $minutesLeft];
        }

        return ['allowed' => true, 'reason' => null, 'minutes_left' => (int) $minutesLeft];
    }

    /**
     * Throwing gate shared by undoStatus() and undo() itself. Deliberately
     * bounded — NOT a general reversal engine (Final Design §1): refuses
     * outright, with no partial/best-effort path, unless the merge is both
     * within its window AND the surviving patient has had zero activity since.
     */
    private function assertUndoable(PatientMerge $record, Patient $master): void
    {
        if ($record->undone_at) {
            throw new \RuntimeException('This merge was already undone.');
        }

        $windowMinutes = (int) config('patients.merge_undo_window_minutes', 15);
        if ($record->created_at->diffInMinutes(now()) > $windowMinutes) {
            throw new \RuntimeException(
                "The {$windowMinutes}-minute undo window for this merge has passed. Automatic undo is no "
                .'longer available — contact support for manual recovery using the retained merge record.'
            );
        }

        if ($this->hasActivitySince($master, $record)) {
            throw new \RuntimeException(
                'This patient has new activity recorded since the merge. Automatic undo is refused to avoid '
                .'corrupting that activity — contact support for manual recovery using the retained merge record.'
            );
        }
    }

    /**
     * True if any row belonging to $master in a manifest table is newer than
     * the merge itself — i.e. the surviving patient was used after the merge.
     * Deliberately conservative: a table with neither created_at nor updated_at
     * cannot be checked and is skipped (documented limitation, not a silent
     * assumption of safety elsewhere). Known gap: for patient_links this checks
     * only the patient_id side, not linked_patient_id — see Final Design risks.
     */
    private function hasActivitySince(Patient $master, PatientMerge $record): bool
    {
        $cutoff = $record->created_at;

        $tables = array_merge(
            PatientMergeManifest::CHILD_TABLES,
            PatientMergeManifest::MONEY_TABLES,
            PatientMergeManifest::SPECIAL_TABLES,
        );

        foreach ($tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'patient_id')) {
                continue;
            }

            $hasCreated = Schema::hasColumn($table, 'created_at');
            $hasUpdated = Schema::hasColumn($table, 'updated_at');
            if (! $hasCreated && ! $hasUpdated) {
                continue;
            }

            $exists = DB::table($table)
                ->where('patient_id', $master->id)
                ->where(function ($q) use ($cutoff, $hasCreated, $hasUpdated) {
                    if ($hasCreated) $q->orWhere('created_at', '>', $cutoff);
                    if ($hasUpdated) $q->orWhere('updated_at', '>', $cutoff);
                })
                ->exists();

            if ($exists) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reverse a merge. Refuses outright (via assertUndoable, re-checked inside
     * the lock) rather than performing any partial restoration. Restores the
     * loser patient, every re-parented row, and every special-entity change the
     * merge made, using only the data captured by merge() itself.
     *
     * @throws \RuntimeException if the merge is no longer undoable
     */
    public function undo(PatientMerge $record, ?int $userId = null): PatientMerge
    {
        $master = Patient::withTrashed()->findOrFail($record->surviving_patient_id);
        $this->assertUndoable($record, $master);

        DB::transaction(function () use ($record, $userId) {
            // Re-fetch and lock both rows — the check above is a fast-fail outside
            // the lock; this is the authoritative gate, evaluated with both rows
            // locked so a concurrent merge/undo can't race past it.
            $master = Patient::whereKey($record->surviving_patient_id)->lockForUpdate()->firstOrFail();
            $loser  = Patient::withTrashed()->whereKey($record->merged_patient_id)->lockForUpdate()->firstOrFail();
            $this->assertUndoable($record, $master);

            $reversal      = (array) $record->reversal;
            $reassignments = (array) $record->reassignments;

            // 1. Restore the master's pre-merge demographic/union field values.
            if ($masterBefore = ($reversal['master_before'] ?? null)) {
                $master->forceFill($masterBefore);
                $master->saveQuietly();
            }

            // 2. Move every re-parented child + money row back to the loser.
            foreach ($reassignments as $table => $ids) {
                if (! $ids || ! Schema::hasTable($table) || ! Schema::hasColumn($table, 'patient_id')) {
                    continue;
                }
                DB::table($table)->whereIn('id', $ids)->update(['patient_id' => $loser->id]);
            }

            // 3. Special-entity rules, reversed.
            $this->restoreWallet($master, $loser, $record->wallet_transfer, $reassignments);
            $this->restoreMemberships($loser, $reversal);
            $this->restoreIdentifiers($loser, $reversal);
            $this->restoreTagPivot($loser, $reversal);
            $this->restoreFamilyLinks($loser, $reversal);

            // 4. Reverse the delegated relationship-tier cascade, if one occurred.
            if ($record->relationship_merge_id) {
                $relMerge = RelationshipMerge::find($record->relationship_merge_id);
                if ($relMerge) {
                    $this->relationshipMerge->undo($relMerge);
                }
            }

            // 5. Un-archive the loser.
            $loser->merged_into_id = null;
            $loser->merged_at      = null;
            $loser->merged_by      = null;
            $loser->saveQuietly();
            $loser->restore();

            // 6. Mark the merge record undone (reuses the existing audit row —
            // no new table, no new column; the actor is recorded inside the
            // same reversal JSON that already carries the rest of the audit detail).
            $record->undone_at = now();
            $record->reversal  = array_merge($reversal, ['undone_by' => $userId, 'undone_at_recorded' => now()->toDateTimeString()]);
            $record->save();

            // 7. Journey Timeline entry — same feed the original merge used.
            $this->activity->log(
                $master,
                'patient.merge_undone',
                $userId ? User::find($userId) : null,
                ['merged_patient_id' => $loser->id, 'merge_record_id' => $record->id],
                $master->relationship_id,
                'Undid merge — restored '.($record->retired_patient_id ?: '#'.$loser->id)." ({$loser->name}) as a separate record.",
            );
        });

        // Publish AFTER commit, mirroring merge()'s own event-timing discipline.
        $this->bus->publish(new PatientMergeUndone(
            survivingPatientId: $record->surviving_patient_id,
            mergedPatientId: $record->merged_patient_id,
            mergeRecordId: $record->id,
            relationshipId: Patient::whereKey($record->surviving_patient_id)->value('relationship_id'),
        ));

        return $record->refresh();
    }

    private function restoreWallet(Patient $master, Patient $loser, ?array $walletTransfer, array $reassignments): void
    {
        if (! $walletTransfer || ! Schema::hasTable('wallets')) {
            return;
        }

        $masterWallet = Wallet::forPatientLocked($master->id);
        $masterWallet->balance_promotional -= $walletTransfer['promotional'];
        $masterWallet->balance_permanent   -= $walletTransfer['permanent'];
        $masterWallet->balance_total        = $masterWallet->balance_promotional + $masterWallet->balance_permanent;
        $masterWallet->save();

        $loserWallet = Wallet::create([
            'patient_id'          => $loser->id,
            'balance_promotional' => $walletTransfer['promotional'],
            'balance_permanent'   => $walletTransfer['permanent'],
            'balance_total'       => $walletTransfer['promotional'] + $walletTransfer['permanent'],
        ]);

        $txnIds = $reassignments['wallet_transactions'] ?? [];
        if ($txnIds) {
            DB::table('wallet_transactions')->whereIn('id', $txnIds)->update(['wallet_id' => $loserWallet->id]);
        }
    }

    /**
     * Move the loser's memberships back and restore whichever ones the merge
     * auto-expired (which may include an originally-master-owned membership,
     * not only moved ones — both operations are applied independently and are
     * each safe to run regardless of the other).
     */
    private function restoreMemberships(Patient $loser, array $reversal): void
    {
        $table = 'finance_patient_memberships';
        if (! Schema::hasTable($table)) {
            return;
        }
        $movedIds = (array) ($reversal['memberships_moved'] ?? []);
        if ($movedIds) {
            DB::table($table)->whereIn('id', $movedIds)->update(['patient_id' => $loser->id]);
        }
        $expiredIds = (array) ($reversal['memberships_expired'] ?? []);
        if ($expiredIds) {
            DB::table($table)->whereIn('id', $expiredIds)->update(['status' => 'active']);
        }
    }

    private function restoreIdentifiers(Patient $loser, array $reversal): void
    {
        if (! Schema::hasTable('patient_identifiers')) {
            return;
        }
        $movedIds = (array) ($reversal['identifiers_moved'] ?? []);
        if ($movedIds) {
            DB::table('patient_identifiers')->whereIn('id', $movedIds)->update(['patient_id' => $loser->id]);
        }
        $demotedIds = (array) ($reversal['identifiers_demoted'] ?? []);
        if ($demotedIds) {
            DB::table('patient_identifiers')->whereIn('id', $demotedIds)->update(['is_primary' => true]);
        }
    }

    private function restoreTagPivot(Patient $loser, array $reversal): void
    {
        if (! Schema::hasTable('patient_tag')) {
            return;
        }
        $movedIds = (array) ($reversal['patient_tag_moved'] ?? []);
        if ($movedIds) {
            DB::table('patient_tag')->whereIn('id', $movedIds)->update(['patient_id' => $loser->id]);
        }
        foreach ((array) ($reversal['patient_tag_deleted'] ?? []) as $row) {
            $insert = $row;
            unset($insert['id']);
            DB::table('patient_tag')->insert($insert);
        }
    }

    private function restoreFamilyLinks(Patient $loser, array $reversal): void
    {
        if (! Schema::hasTable('patient_links')) {
            return;
        }
        foreach ((array) ($reversal['patient_links_reconciled'] ?? []) as $r) {
            $before = (array) $r['before'];
            unset($before['id']);
            DB::table('patient_links')->where('id', $r['id'])->update($before);
        }
        foreach ((array) ($reversal['patient_links_moved'] ?? []) as $m) {
            DB::table('patient_links')->where('id', $m['id'])->update([$m['column'] => $loser->id]);
        }
        foreach ((array) ($reversal['patient_links_deleted'] ?? []) as $row) {
            $insert = $row;
            unset($insert['id']);
            DB::table('patient_links')->insert($insert);
        }
    }
}
