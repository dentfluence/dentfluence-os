<?php

namespace App\Console\Commands;

use App\Services\Relationship\JourneyService;
use Illuminate\Console\Command;

/**
 * relationship:sync-journeys — Phase 1 · Sprint 3 (Workstream C).
 *
 * Reconciles relationship journeys to the legacy pipeline state (leads.stage,
 * treatment_opportunities.status) — IN SHADOW. Nothing reads journeys as
 * authoritative yet; this just measures/populates them.
 *
 *   php artisan relationship:sync-journeys          # DRY RUN (read-only report)
 *   php artisan relationship:sync-journeys --apply  # write shadow journeys (idempotent)
 *   php artisan relationship:sync-journeys --apply --force
 */
class SyncRelationshipJourneys extends Command
{
    protected $signature = 'relationship:sync-journeys {--apply} {--force}';
    protected $description = 'Shadow-sync relationship journeys to legacy lead stages & opportunity statuses. Dry-run by default.';

    public function handle(JourneyService $svc): int
    {
        if (! $this->option('apply')) {
            $r = $svc->analyze();
            $this->line('<info>DRY RUN</info> — nothing was changed.');
            $this->renderTable('Would create', 'Would reconcile (diverged)', 'In sync', 'Skipped', [
                ['Lead journeys', $r['leads']['create'], $r['leads']['reconcile'], $r['leads']['in_sync'], $r['leads']['skipped']],
                ['Opportunity journeys', $r['opportunities']['create'], $r['opportunities']['reconcile'], $r['opportunities']['in_sync'], $r['opportunities']['skipped']],
            ]);
            $this->newLine();
            $this->line('Run with <comment>--apply</comment> to write shadow journeys. Reads still use the legacy stage/status until the Phase 4 cutover.');
            return self::SUCCESS;
        }

        $this->warn('This writes SHADOW relationship journeys (additive, idempotent — reads are unaffected).');
        if (! ($this->option('force') || $this->confirm('Continue?', false))) {
            $this->warn('Aborted — no changes made.');
            return self::FAILURE;
        }

        $r = $svc->applyAll();
        $this->info('Shadow journey sync applied.');
        $this->renderTable('Created', 'Reconciled', 'In sync', 'Skipped', [
            ['Lead journeys', $r['leads']['created'], $r['leads']['reconciled'], $r['leads']['in_sync'], $r['leads']['skipped']],
            ['Opportunity journeys', $r['opportunities']['created'], $r['opportunities']['reconciled'], $r['opportunities']['in_sync'], $r['opportunities']['skipped']],
        ]);
        return self::SUCCESS;
    }

    private function renderTable(string $c1, string $c2, string $c3, string $c4, array $rows): void
    {
        $this->table(['Journey type', $c1, $c2, $c3, $c4], $rows);
    }
}
