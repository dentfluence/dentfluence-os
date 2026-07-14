<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PruneLogTables — production hardening 2026-07-14.
 *
 * audit_logs and activities are written on effectively every action in the app
 * and nothing ever removed a row, so both grow without bound (millions of rows
 * within a year or two, bloating backups and slowing writes).
 *
 * Retention is configurable and defaults to 24 months — long enough to cover a
 * clinic's practical audit needs while keeping the tables bounded.
 *
 * SAFETY:
 *   - Dry-run by DEFAULT. Pass --apply to actually delete.
 *   - Deletes in chunks so it never holds a long lock.
 *   - audit_logs uses a tamper-evident hash chain (HashChained). Pruning breaks
 *     the chain at the cut point by design, so the command REFUSES to touch
 *     audit_logs unless --include-audit is passed explicitly, and it always
 *     prunes from the OLDEST end so the remaining chain stays contiguous.
 *
 * Usage:
 *   php artisan logs:prune                        — preview (activities only)
 *   php artisan logs:prune --apply                — prune activities
 *   php artisan logs:prune --apply --include-audit
 *   php artisan logs:prune --months=36 --apply
 */
class PruneLogTables extends Command
{
    protected $signature = 'logs:prune
        {--months= : Retention window in months (default: config prune.retention_months or 24)}
        {--apply : Actually delete. Without this the command only reports.}
        {--include-audit : Also prune audit_logs (breaks the hash chain before the cut point).}
        {--chunk=1000 : Rows deleted per statement.}';

    protected $description = 'Prune old rows from the activities (and optionally audit_logs) tables';

    public function handle(): int
    {
        $months = (int) ($this->option('months') ?: config('prune.retention_months', 24));
        $apply  = (bool) $this->option('apply');
        $chunk  = max(100, (int) $this->option('chunk'));
        $cutoff = now()->subMonths($months)->startOfDay();

        $this->newLine();
        $this->line('  <fg=cyan;options=bold>🧹 Log table pruning</> — retention: ' . $months . ' months');
        $this->line('  Cutoff: rows created before <fg=yellow>' . $cutoff->toDateString() . '</> are eligible.');
        if (! $apply) {
            $this->warn('  ⚠  DRY RUN — nothing will be deleted. Re-run with --apply.');
        }
        $this->newLine();

        $tables = ['activities'];
        if ($this->option('include-audit')) {
            $tables[] = 'audit_logs';
        } else {
            $this->line('  <fg=gray>audit_logs skipped (pass --include-audit to prune it too).</>');
        }

        $rows = [];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                $rows[] = [$table, '—', 'table not found'];
                continue;
            }

            $eligible = DB::table($table)->where('created_at', '<', $cutoff)->count();
            $total    = DB::table($table)->count();

            if (! $apply || $eligible === 0) {
                $rows[] = [$table, number_format($total), number_format($eligible) . ($apply ? ' (nothing to do)' : ' would be pruned')];
                continue;
            }

            $deleted = $this->pruneTable($table, $cutoff, $chunk);
            $rows[]  = [$table, number_format($total), number_format($deleted) . ' deleted'];
        }

        $this->table(['Table', 'Rows before', $apply ? 'Result' : 'Eligible'], $rows);
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Delete oldest-first in chunks. Deleting from the oldest end keeps the
     * audit_logs hash chain contiguous for everything that remains.
     */
    private function pruneTable(string $table, \Carbon\Carbon $cutoff, int $chunk): int
    {
        $deleted = 0;

        do {
            $n = DB::table($table)
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit($chunk)
                ->delete();

            $deleted += $n;

            if ($n > 0) {
                $this->line("  <fg=gray>{$table}: {$deleted} deleted…</>");
                usleep(50_000); // brief breather so a live clinic isn't starved
            }
        } while ($n > 0);

        return $deleted;
    }
}
