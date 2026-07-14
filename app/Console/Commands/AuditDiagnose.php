<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * AuditDiagnose — figure out WHY a hash chain fails verification.
 *
 * `audit:verify` only tells you the first bad id. This tells you which specific
 * invariant broke on that row, which distinguishes the three very different
 * causes:
 *
 *   1. prev_hash mismatch      → a row was DELETED or inserted out of band
 *                                (chain gap). Not a content forgery.
 *   2. hash matches neither    → the row's CONTENT no longer hashes to its
 *      keyed nor legacy          stored hash — either genuine tampering, or the
 *                                canonical set changed (e.g. a migration added a
 *                                column) after the row was written.
 *   3. hash matches legacy but → nothing wrong; that's just a pre-HMAC row.
 *      not keyed
 *
 * Usage:
 *   php artisan audit:diagnose
 *   php artisan audit:diagnose --table=audit_logs --around=133
 */
class AuditDiagnose extends Command
{
    protected $signature = 'audit:diagnose
        {--table= : Only inspect this table}
        {--around= : Row id to inspect closely}
        {--context=3 : Rows of context either side}';

    protected $description = 'Explain exactly why an audit hash chain fails verification';

    /** Same set AuditVerify uses. */
    private array $models = [
        \App\Models\AuditLog::class,
        \App\Models\BillingAuditLog::class,
        \App\Models\PrescriptionAuditLog::class,
        \App\Models\Finance\FinanceAuditLog::class,
    ];

    public function handle(): int
    {
        foreach ($this->models as $class) {
            if (! class_exists($class)) {
                continue;
            }

            $table = (new $class)->getTable();

            if ($this->option('table') && $this->option('table') !== $table) {
                continue;
            }

            $this->newLine();
            $this->line("  <fg=cyan;options=bold>{$table}</>");

            $this->diagnose($class, $table);
        }

        $this->newLine();

        return self::SUCCESS;
    }

    private function diagnose(string $class, string $table): void
    {
        $prev     = null;
        $prevId   = null;
        $checked  = 0;

        foreach ($class::query()->orderBy('id')->cursor() as $row) {
            $canonical = $class::chainCanonical($row->getAttributes());
            $stored    = (string) $row->hash;

            $keyed  = $class::chainComputeHash($prev, $canonical);
            $legacy = $class::chainLegacyHash($prev, $canonical);

            $hashOk = hash_equals($keyed, $stored) || hash_equals($legacy, $stored);
            $prevOk = (string) $row->prev_hash === (string) $prev;

            if ($hashOk && $prevOk) {
                $prev   = $row->hash;
                $prevId = $row->id;
                $checked++;
                continue;
            }

            // ── Found the break. Explain it. ────────────────────────────────
            $this->error("  ✗ First bad row: id {$row->id}  (after {$checked} good rows, previous id " . ($prevId ?? 'none') . ")");
            $this->newLine();

            $idGap = $prevId !== null ? ($row->id - $prevId - 1) : 0;

            $this->line('    prev_hash matches previous row : ' . ($prevOk ? '<fg=green>yes</>' : '<fg=red>NO</>'));
            $this->line('    hash matches keyed (HMAC)      : ' . (hash_equals($keyed, $stored)  ? '<fg=green>yes</>' : '<fg=red>no</>'));
            $this->line('    hash matches legacy (sha256)   : ' . (hash_equals($legacy, $stored) ? '<fg=green>yes</>' : '<fg=red>no</>'));
            $this->line("    id gap before this row         : {$idGap}");
            $this->newLine();

            if (! $prevOk && $hashOk) {
                $this->warn('    → CHAIN GAP, not content tampering.');
                $this->line("      This row's content still hashes correctly, but its prev_hash points at a");
                $this->line('      row that is no longer present. Rows were deleted (or inserted out of band,');
                $this->line('      e.g. a seeder / raw DB::table insert / a restored partial backup).');
                if ($idGap > 0) {
                    $this->line("      The {$idGap} missing id(s) before it support this.");
                }
            } elseif (! $hashOk && $prevOk) {
                $this->warn('    → CONTENT does not hash to the stored value.');
                $this->line('      Either the row was edited, OR the hashed field set changed after it was');
                $this->line('      written (a migration adding/removing a column changes chainCanonical()).');
                $this->line('      Compare the columns on this table against what existed when the row was made.');
            } else {
                $this->warn('    → BOTH the link and the content fail. Most likely the chain was rebuilt or');
                $this->line('      partially restored at this point.');
            }

            $this->newLine();
            $this->line('    Stored hash : ' . substr($stored, 0, 24) . '…');
            $this->line('    Expected    : ' . substr($keyed, 0, 24) . '… (keyed)');
            $this->line('                  ' . substr($legacy, 0, 24) . '… (legacy)');
            $this->newLine();
            $this->line('    Row attributes (the exact set that gets hashed):');
            foreach ($canonical as $k => $v) {
                $val = is_scalar($v) || $v === null ? (string) $v : '[' . gettype($v) . ']';
                $this->line("      {$k} = " . mb_strimwidth($val, 0, 60, '…'));
            }

            return;
        }

        $this->info("  ✓ chain intact ({$checked} rows).");
    }
}
