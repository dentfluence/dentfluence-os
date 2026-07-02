<?php

namespace App\Console\Commands;

use App\Services\Relationship\RelationshipBackfillService;
use Illuminate\Console\Command;

/**
 * relationship:backfill — Phase 1 · Sprint 2.
 *
 * Backfills relationship_id across existing leads & patients and queues
 * potential duplicates for review.
 *
 *   php artisan relationship:backfill                # DRY RUN (read-only report)
 *   php artisan relationship:backfill --apply        # write links (asks to confirm)
 *   php artisan relationship:backfill --apply --force # write links, no prompt
 *   php artisan relationship:backfill --dedup-only    # only (re)build the review queue
 *
 * Dry-run is the default and safe. --apply is additive + idempotent and NEVER
 * merges anyone automatically — duplicates are queued for human review.
 */
class RelationshipBackfill extends Command
{
    protected $signature = 'relationship:backfill
        {--apply : Write links (default is a read-only dry run)}
        {--dedup-only : Only (re)build the dedup review queue}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Backfill relationship_id across leads & patients; queue potential duplicates for review. Dry-run by default.';

    public function handle(RelationshipBackfillService $svc): int
    {
        if ($this->option('dedup-only')) {
            if (! $this->confirmWrite('This writes only to the dedup_candidates review queue (no merges).')) {
                $this->warn('Aborted — no changes made.');
                return self::FAILURE;
            }
            $n = $svc->queueDedupCandidates();
            $this->info("Dedup review queue updated: {$n} new candidate pair(s).");
            return self::SUCCESS;
        }

        if (! $this->option('apply')) {
            $r = $svc->analyze();
            $this->line('<info>DRY RUN</info> — nothing was changed.');
            $this->table(
                ['Entity', 'Unlinked', 'Would match existing', 'Would create new'],
                [
                    ['Leads', $r['leads']['unlinked'], $r['leads']['would_match'], $r['leads']['would_create']],
                    ['Patients', $r['patients']['unlinked'], $r['patients']['would_match'], $r['patients']['would_create']],
                ]
            );
            $this->line("Opportunities: <comment>{$r['opportunities']['unlinked']}</comment> unlinked, <comment>{$r['opportunities']['linkable']}</comment> linkable via their patient.");
            $this->line("Potential duplicate groups (shared phone/email): <comment>{$r['potential_duplicate_groups']}</comment>");
            $this->newLine();
            $this->line('Run with <comment>--apply</comment> to write links. Duplicates are queued for review, never auto-merged.');
            return self::SUCCESS;
        }

        if (! $this->confirmWrite('This will WRITE relationship links to leads & patients (additive + idempotent).')) {
            $this->warn('Aborted — no changes made.');
            return self::FAILURE;
        }

        $r = $svc->apply();
        $this->info('Backfill applied.');
        $this->table(
            ['Entity', 'Linked', 'Failed/Skipped'],
            [
                ['Leads', $r['leads']['linked'], $r['leads']['failed']],
                ['Patients', $r['patients']['linked'], $r['patients']['failed']],
                ['Opportunities', $r['opportunities']['linked'], $r['opportunities']['skipped']],
            ]
        );
        $this->line("Dedup candidate pairs queued for review: <comment>{$r['dedup_candidates_queued']}</comment>");
        $this->newLine();
        $this->line('Review duplicates in the dedup_candidates table before any merge (MergeService::merge is manual + reversible).');
        return self::SUCCESS;
    }

    private function confirmWrite(string $message): bool
    {
        $this->warn($message);
        return (bool) ($this->option('force') || $this->confirm('Continue?', false));
    }
}
