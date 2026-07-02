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
            ];
        }

        $this->newLine();
        $this->table(['Table', 'Status', 'Rows checked', 'First bad id'], $rows);

        if ($allOk) {
            $this->info('All audit chains verified intact.');
            return self::SUCCESS;
        }

        $this->error('One or more audit chains FAILED verification — investigate the flagged rows.');
        return self::FAILURE;
    }

    /**
     * Recompute the chain over ALL rows in id order and write prev_hash/hash via
     * the query builder (bypassing the append-only model guard). Idempotent — it
     * simply rewrites the correct chain. Returns the number of rows written.
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
