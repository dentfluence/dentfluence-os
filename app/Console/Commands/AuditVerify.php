<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\BillingAuditLog;
use App\Models\Finance\FinanceAuditLog;
use App\Models\Prescription\PrescriptionAuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * audit:verify  (Phase A — tamper-evidence)
 * -----------------------------------------
 * Verifies the hash chain on every tamper-evident audit table. With --backfill
 * it first computes hashes for historical rows that predate the chain (created
 * before the HashChained trait was switched on), writing them in id order so the
 * chain becomes continuous and verifiable.
 *
 *   php artisan audit:verify --backfill   # chain legacy rows, then verify
 *   php artisan audit:verify              # verify only (no writes)
 *
 * A FAIL with a first-bad id means a row at/after that id was altered or a row
 * was deleted — investigate that table.
 */
class AuditVerify extends Command
{
    protected $signature = 'audit:verify {--backfill : Compute hashes for legacy rows before verifying}';

    protected $description = 'Verify (and optionally backfill) the hash chains on the audit tables.';

    /** Tamper-evident models to check. */
    private array $models = [
        AuditLog::class,
        BillingAuditLog::class,
        PrescriptionAuditLog::class,
        FinanceAuditLog::class,
    ];

    public function handle(): int
    {
        $rows = [];
        $allOk = true;

        foreach ($this->models as $class) {
            $table = (new $class)->getTable();

            if ($this->option('backfill')) {
                $written = $this->backfill($class, $table);
                $this->line("  {$table}: backfilled {$written} row(s).");
            }

            $result = $class::verifyChain();
            $allOk = $allOk && $result['ok'];

            $rows[] = [
                $table,
                $result['ok'] ? 'OK' : 'TAMPERED',
                $result['checked'],
                $result['first_bad_id'] ?? '—',
                // Rows still carrying the pre-HMAC (unkeyed) hash. These are
                // genuine and verify correctly, but they're only tamper-evident
                // against app-layer edits, not against direct DB writes.
                $result['legacy_rows'] ?? 0,
            ];
        }

        $this->newLine();
        $this->table(['Table', 'Status', 'Rows checked', 'First bad id', 'Legacy (unkeyed) rows'], $rows);

        if ($allOk) {
            $this->info('All audit chains verified intact.');

            $legacy = array_sum(array_column($rows, 4));
            if ($legacy > 0) {
                $this->warn("  {$legacy} row(s) still use the legacy unkeyed hash (written before the HMAC change).");
                $this->line('  They verify correctly. New rows are keyed. Set AUDIT_HASH_KEY in .env if you have not.');
            }

            return self::SUCCESS;
        }

        $this->error('One or more audit chains FAILED verification — investigate the flagged rows.');
        return self::FAILURE;
    }

    /**
     * Recompute the chain over ALL rows in id order and write prev_hash/hash via
     * the query builder (bypassing the append-only model guard). Idempotent — it
     * simply rewrites the correct chain. Returns the number of rows written.
     *
     * ⚠ READ THIS BEFORE RUNNING --backfill.
     *
     * This rewrites history's hashes to match whatever the rows CURRENTLY say.
     * It is exactly what an attacker who had edited a row would do, so it
     * permanently destroys the ability to detect any tampering that happened
     * before it runs. Never use it to "make audit:verify go green".
     *
     * There is one legitimate use: RE-ANCHORING after a diagnosed, benign code
     * defect that made the stored hashes structurally unverifiable — because in
     * that case there was no integrity guarantee left to destroy.
     *
     * That is precisely what happened here (2026-07-04 → 2026-07-14):
     * chainCanonical() hashed the compact JSON that Eloquent produced, but MySQL
     * re-formats and re-orders keys inside a JSON column, so the string read back
     * never matched the string hashed. Every audit row carrying a non-empty
     * old_values/new_values payload has failed verification since the first model
     * audit entry was written. chainCanonical() now hashes a canonical (sorted,
     * re-encoded) form that survives the round-trip — see the comment there.
     *
     * So: run --backfill ONCE, record the date and reason (docs/), and treat the
     * chain as authoritative only from that point forward.
     */
    private function backfill(string $class, string $table): int
    {
        $prev = null;
        $count = 0;

        foreach (DB::table($table)->orderBy('id')->cursor() as $row) {
            $attributes = (array) $row;
            $hash = $class::chainComputeHash($prev, $class::chainCanonical($attributes));

            DB::table($table)->where('id', $row->id)->update([
                'prev_hash' => $prev,
                'hash'      => $hash,
            ]);

            $prev = $hash;
            $count++;
        }

        return $count;
    }
}
