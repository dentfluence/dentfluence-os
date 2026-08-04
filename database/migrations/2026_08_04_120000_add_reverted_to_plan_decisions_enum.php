<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F2 — record acceptance reversal as a decision, not an erasure.
 *
 * Canonical Treatment Lifecycle V1 §7 requires that reversing an acceptance is
 * itself a recorded decision. The decision ledger is append-only, so the
 * reversal needs a value of its own; without it, a revert had to erase the
 * acceptance mirror while leaving the ledger head saying "accepted" — the plan
 * then read as neither accepted nor decision-pending.
 *
 * ADDITIVE ONLY. This widens the accepted set of values on
 * plan_decisions.decision from four to five. It rewrites no row, drops no
 * column, and changes no existing value. Every row written before this
 * migration remains valid, and code that only knows the original four values
 * continues to work unchanged.
 */
return new class extends Migration
{
    private const WITH_REVERTED = "'accepted','partially_accepted','deferred','rejected','reverted'";
    private const ORIGINAL      = "'accepted','partially_accepted','deferred','rejected'";

    public function up(): void
    {
        $this->setEnum(self::WITH_REVERTED);
    }

    public function down(): void
    {
        // Refuse to narrow the enum while rows depend on the wider set —
        // silently discarding recorded patient decisions is never acceptable.
        $existing = DB::table('plan_decisions')->where('decision', 'reverted')->count();

        if ($existing > 0) {
            throw new RuntimeException(
                'Cannot roll back: ' . $existing . ' recorded acceptance reversal(s) would become invalid. '
                . 'The decision ledger is append-only; resolve these rows deliberately before narrowing the enum.'
            );
        }

        $this->setEnum(self::ORIGINAL);
    }

    /**
     * MySQL is the only driver that models this as a native ENUM. On any other
     * driver the column is already a permissive string type, so there is
     * nothing to widen and the new value works without a schema change.
     */
    private function setEnum(string $values): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE plan_decisions MODIFY decision ENUM({$values}) NOT NULL"
        );
    }
};
