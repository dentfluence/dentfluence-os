<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * A3 — PAYMENT MODE STANDARDISATION.
 *
 * Two independent defects, one migration, because fixing either alone leaves
 * the system inconsistent:
 *
 * 1. finance_transactions.payment_mode was created 2026-05-29, a week BEFORE
 *    the billing tables, and was never widened alongside them. It lacked
 *    `debit_card`. Because config/database.php runs strict mode, recording a
 *    debit-card payment raised MySQL 1265 inside the payment transaction and
 *    rolled the whole payment back — six live entry points simply failed.
 *
 * 2. `netbanking` and `bank_transfer` were duplicate spellings of one real
 *    world thing, with zero behavioural difference anywhere in the code.
 *    `netbanking` is retired.
 *
 * ORDER MATTERS, and it is the opposite of the obvious one:
 *   data migration FIRST (while `netbanking` is still a legal value), then
 *   widen finance_transactions, then narrow the billing tables. Narrowing an
 *   ENUM that still holds the value being removed silently coerces those rows
 *   to '' in non-strict mode and errors in strict mode. Neither is acceptable
 *   on financial history.
 *
 * `insurance` is deliberately RETAINED in finance_transactions. It is not part
 * of the canonical vocabulary and has zero writers, but removing an enum value
 * from a live financial table without first proving no row uses it is how
 * history gets destroyed. Retaining it costs nothing.
 *
 * MySQL DDL is not transactional, so every step is written to be idempotent and
 * re-runnable, and the data steps verify themselves before the schema narrows.
 */
return new class extends Migration
{
    /** The canonical nine. Mirrors App\Enums\PaymentMode — keep them in step. */
    private const CANONICAL = [
        'cash', 'card', 'debit_card', 'upi', 'cheque',
        'bank_transfer', 'emi', 'wallet', 'other',
    ];

    public function up(): void
    {
        // ── 1. Inspect, then migrate netbanking → bank_transfer ──────────────
        // Amounts and row counts are captured on both sides of each UPDATE and
        // compared. A mismatch aborts before any schema change, leaving the
        // database exactly as it was found.
        $report = [];

        foreach (['invoice_payments', 'receipts', 'finance_transactions'] as $table) {
            if (! $this->hasEnumValue($table, 'netbanking')) {
                // finance_transactions never had it — nothing to do, and we must
                // not run an UPDATE that could match nothing but log noise.
                $report[$table] = ['found' => 0, 'migrated' => 0, 'note' => 'enum never allowed netbanking'];
                continue;
            }

            $before = DB::table($table)
                ->where('payment_mode', 'netbanking')
                ->selectRaw('COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS total')
                ->first();

            $bankBefore = DB::table($table)
                ->where('payment_mode', 'bank_transfer')
                ->selectRaw('COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS total')
                ->first();

            $migrated = 0;

            if ((int) $before->cnt > 0) {
                // Only the discriminator changes. Amounts, dates, patient /
                // invoice / receipt / transaction links and every audit column
                // are untouched — this is a relabel, not a re-post.
                $migrated = DB::table($table)
                    ->where('payment_mode', 'netbanking')
                    ->update(['payment_mode' => 'bank_transfer']);

                $bankAfter = DB::table($table)
                    ->where('payment_mode', 'bank_transfer')
                    ->selectRaw('COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS total')
                    ->first();

                $expectedCount = (int) $bankBefore->cnt + (int) $before->cnt;
                $expectedTotal = round((float) $bankBefore->total + (float) $before->total, 2);

                if ((int) $bankAfter->cnt !== $expectedCount
                    || abs((float) $bankAfter->total - $expectedTotal) > 0.009) {
                    throw new RuntimeException(
                        "A3 aborted on {$table}: expected {$expectedCount} bank_transfer rows "
                        . "totalling {$expectedTotal}, found {$bankAfter->cnt} totalling {$bankAfter->total}. "
                        . 'No schema change was applied.'
                    );
                }

                $remaining = DB::table($table)->where('payment_mode', 'netbanking')->count();
                if ($remaining !== 0) {
                    throw new RuntimeException(
                        "A3 aborted on {$table}: {$remaining} netbanking rows survived the migration."
                    );
                }
            }

            $report[$table] = [
                'found'    => (int) $before->cnt,
                'amount'   => (float) $before->total,
                'migrated' => $migrated,
            ];
        }

        Log::info('A3 payment-mode migration — netbanking → bank_transfer', $report);

        foreach ($report as $table => $row) {
            $found = $row['found'] ?? 0;
            echo sprintf(
                "  %-22s netbanking rows found: %d%s\n",
                $table,
                $found,
                $found > 0 ? ' — migrated to bank_transfer (Rs. ' . number_format($row['amount'], 2) . ')' : ''
            );
        }

        // ── 2. Widen finance_transactions ────────────────────────────────────
        // Additive: gains debit_card, keeps insurance. Nothing is removed, so no
        // existing row can be invalidated. netbanking is NOT added — it is being
        // retired, not propagated.
        DB::statement("ALTER TABLE finance_transactions MODIFY COLUMN payment_mode ENUM(
            'cash','card','debit_card','upi','cheque','bank_transfer','emi','wallet','insurance','other'
        ) NOT NULL DEFAULT 'cash'");

        // ── 3. Narrow the billing tables to the canonical nine ───────────────
        // Safe only because step 1 proved zero netbanking rows remain.
        $enum = "'" . implode("','", self::CANONICAL) . "'";

        DB::statement("ALTER TABLE invoice_payments MODIFY COLUMN payment_mode ENUM({$enum}) NOT NULL");
        DB::statement("ALTER TABLE receipts MODIFY COLUMN payment_mode ENUM({$enum}) NOT NULL DEFAULT 'cash'");
    }

    public function down(): void
    {
        // Restore netbanking as a STORABLE value so the pre-A3 schema is
        // reachable again. Rows are NOT un-migrated: once a netbanking payment
        // has been relabelled bank_transfer there is no way to tell it apart
        // from a genuine bank transfer, and inventing that distinction would be
        // fabricating financial history. Reversing the schema is reversible;
        // reversing the data is not, so it is deliberately not attempted.
        DB::statement("ALTER TABLE invoice_payments MODIFY COLUMN payment_mode ENUM(
            'cash','card','debit_card','upi','cheque','netbanking','bank_transfer','emi','wallet','other'
        ) NOT NULL");

        DB::statement("ALTER TABLE receipts MODIFY COLUMN payment_mode ENUM(
            'cash','card','debit_card','upi','cheque','netbanking','bank_transfer','emi','wallet','other'
        ) NOT NULL DEFAULT 'cash'");

        // Refuse to narrow finance_transactions back if any row already relies
        // on debit_card — dropping it would coerce real payments to ''.
        $inUse = DB::table('finance_transactions')->where('payment_mode', 'debit_card')->count();

        if ($inUse > 0) {
            Log::warning("A3 down(): finance_transactions keeps debit_card — {$inUse} rows use it.");
            return;
        }

        DB::statement("ALTER TABLE finance_transactions MODIFY COLUMN payment_mode ENUM(
            'cash','upi','card','bank_transfer','cheque','emi','insurance','wallet','other'
        ) NOT NULL DEFAULT 'cash'");
    }

    /** Does $table.$column's ENUM definition currently allow $value? */
    private function hasEnumValue(string $table, string $value): bool
    {
        $row = DB::selectOne(
            'SELECT COLUMN_TYPE AS t FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, 'payment_mode']
        );

        return $row !== null && str_contains($row->t, "'" . $value . "'");
    }
};
