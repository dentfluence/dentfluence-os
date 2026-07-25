<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Smoke\Journeys\AppointmentsSmokeJourney;
use App\Services\Smoke\Journeys\InventorySmokeJourney;
use App\Services\Smoke\Journeys\PatientsSmokeJourney;
use App\Services\Smoke\SmokeRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * dentfluence:smoke — the one-command post-deployment smoke suite for the
 * three FROZEN modules: Patients V1.0, Appointments V1.0, Inventory V1.0.
 *
 * Answers, after every deployment: "Are the core clinic workflows still
 * alive, and is the persisted data correct?" A green HTTP response is not
 * enough — every action is verified in the database (persistence, related
 * records, lifecycle events, exact counts, no duplicates).
 *
 * Modes
 * -----
 *  default (rollback)  Everything runs inside one DB transaction that is
 *                      ALWAYS rolled back (the patients:register-smoketest
 *                      convention). Zero residue — safe on production.
 *  --commit            Records really persist (proves end-to-end commit
 *                      durability), uniquely prefixed SMOKE_<timestamp>_,
 *                      then cleaned up individually from the run registry.
 *                      Anything uncleanable is reported as retained.
 *
 * Safety rails (both modes, forced in-process for this run only):
 *  - WhatsApp disabled + dry-run, mail captured in memory, queue sync
 *    (nothing leaks to real queue workers), automation engine off.
 *  - Only SMOKE_<runid>_ records are ever created; cleanup deletes ONLY
 *    ids tracked by this same run — never broad deletes.
 *  - No patient merge, no financial transactions in commit mode.
 *
 * Exit code: 0 = OVERALL PASS, 1 = OVERALL FAIL (CI/deploy can gate on it).
 */
class DentfluenceSmoke extends Command
{
    protected $signature = 'dentfluence:smoke
        {--module=all : Which journey to run: patients|appointments|inventory|all}
        {--commit : Persist smoke records (default: single transaction, always rolled back)}
        {--keep : With --commit, keep the created records (skip cleanup)}
        {--force : With --commit, skip the confirmation prompt (for CI)}';

    protected $description = 'Run the Dentfluence smoke suite for the frozen Patients, Appointments and Inventory modules.';

    public function handle(): int
    {
        $mode = $this->option('commit') ? SmokeRun::MODE_COMMIT : SmokeRun::MODE_ROLLBACK;
        $run  = new SmokeRun($mode);

        $module = strtolower((string) $this->option('module'));
        if (! in_array($module, ['all', 'patients', 'appointments', 'inventory'], true)) {
            $this->error("Unknown --module '{$module}'. Use patients|appointments|inventory|all.");
            return self::FAILURE;
        }

        $actor = $this->resolveActor();
        if (! $actor) {
            $this->error('No active user with a branch found to act as the smoke actor.');
            return self::FAILURE;
        }

        $this->applySafetyRails($actor);

        $env = app()->environment();
        $db  = DB::connection()->getDatabaseName();

        $this->info('DENTFLUENCE SMOKE TEST');
        $this->info('======================');
        $this->line("Run ID:      {$run->runId}");
        $this->line("Environment: {$env} · DB: {$db}");
        $this->line("Mode:        {$mode}" . ($mode === SmokeRun::MODE_ROLLBACK ? ' (zero residue)' : ''));
        $this->line("Actor:       #{$actor->id} {$actor->name} (branch {$actor->branch_id})");
        $this->line("Module(s):   {$module}");
        $this->newLine();

        if ($mode === SmokeRun::MODE_COMMIT && ! $this->option('force')) {
            $warn = "Commit mode writes real {$run->runId}_ records to [{$db}] and cleans them up afterwards. Continue?";
            if (! $this->confirm($warn)) {
                $this->line('Aborted.');
                return self::FAILURE;
            }
        }

        if ($mode === SmokeRun::MODE_ROLLBACK) {
            $sentinel = new \RuntimeException('__SMOKE_ROLLBACK__');
            try {
                DB::transaction(function () use ($run, $actor, $module, $sentinel) {
                    $this->runJourneys($run, $actor, $module);
                    throw $sentinel; // always roll back — zero residue
                });
            } catch (\Throwable $e) {
                if ($e !== $sentinel) {
                    $run->check('Runner', 'Smoke run aborted by exception (rolled back)', false, SmokeRun::TECHNICAL, $e->getMessage());
                }
            }
        } else {
            $this->runJourneys($run, $actor, $module);
            if ($this->option('keep')) {
                $run->retained = array_map(fn ($r) => $r['label'], $run->records);
            } else {
                $this->cleanup($run);
            }
        }

        return $this->report($run, $env, $db) ? self::SUCCESS : self::FAILURE;
    }

    // ── Journey orchestration ────────────────────────────────────────────────

    private function runJourneys(SmokeRun $run, User $actor, string $module): void
    {
        $adult = null;

        if (in_array($module, ['all', 'patients'], true)) {
            $adult = $this->guarded($run, 'Patients', fn () => app(PatientsSmokeJourney::class)->run($run, $actor));
        }
        if (in_array($module, ['all', 'appointments'], true)) {
            $this->guarded($run, 'Appointments', fn () => app(AppointmentsSmokeJourney::class)->run($run, $actor, $adult));
        }
        if (in_array($module, ['all', 'inventory'], true)) {
            $this->guarded($run, 'Inventory', fn () => app(InventorySmokeJourney::class)->run($run, $actor));
        }
    }

    /** Run one journey; an uncaught exception fails that journey, not the runner. */
    private function guarded(SmokeRun $run, string $journey, \Closure $fn): mixed
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            $run->check($journey, 'Journey aborted by exception', false, SmokeRun::TECHNICAL, $e::class . ': ' . $e->getMessage());
            return null;
        }
    }

    // ── Actor + safety ───────────────────────────────────────────────────────

    private function resolveActor(): ?User
    {
        return User::where('is_active', true)
                ->where(function ($q) {
                    $q->where('role', 'admin')
                      ->orWhereHas('roleModel', fn ($r) => $r->where('slug', 'admin'));
                })
                ->whereNotNull('branch_id')
                ->first()
            ?? User::where('is_active', true)->whereNotNull('branch_id')->first()
            ?? User::whereNotNull('branch_id')->first();
    }

    /**
     * In-process config overrides for THIS run only. Nothing is written to
     * .env or the config cache; the overrides die with the process.
     */
    private function applySafetyRails(User $actor): void
    {
        config([
            'whatsapp.enabled'           => false, // no real WhatsApp sends
            'whatsapp.dry_run'           => true,
            'mail.default'               => 'array', // mail captured in memory
            'queue.default'              => 'sync',  // nothing leaks to real queue workers
            'session.driver'             => 'array', // probes never write session rows
            'features.automation.engine' => false,   // no rule-driven comms from smoke events
        ]);

        // Authenticate the actor for the in-process HTTP probes (memoized
        // session guard) and for any auth()-dependent audit stamping.
        Auth::login($actor);
    }

    // ── Commit-mode cleanup ──────────────────────────────────────────────────

    /**
     * Delete ONLY what this run created: journey-registered cleanup steps
     * first, then every tracked record in reverse creation order. Each step is
     * isolated — a failure retains that record (reported), never cascades, and
     * no broad delete statements are ever issued.
     */
    private function cleanup(SmokeRun $run): void
    {
        foreach (array_reverse($run->cleanupSteps) as $step) {
            try {
                $step();
            } catch (\Throwable $e) {
                $run->retained[] = 'cleanup step failed: ' . $e->getMessage();
            }
        }

        foreach (array_reverse($run->records) as $record) {
            try {
                /** @var \Illuminate\Database\Eloquent\Model|null $model */
                $model = $record['model']::withoutGlobalScopes()
                    ->when(
                        method_exists($record['model'], 'bootSoftDeletes')
                            && in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($record['model']), true),
                        fn ($q) => $q->withTrashed()
                    )
                    ->find($record['id']);

                if (! $model) {
                    $run->cleaned++; // already gone (e.g. deleted during the journey)
                    continue;
                }

                in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($model), true)
                    ? $model->forceDelete()
                    : $model->delete();

                $run->cleaned++;
            } catch (\Throwable $e) {
                $run->retained[] = $record['label'] . ' (cleanup failed: ' . $e->getMessage() . ')';
            }
        }
    }

    // ── Report ───────────────────────────────────────────────────────────────

    private function report(SmokeRun $run, string $env, string $db): bool
    {
        $finished = now();

        $this->newLine();
        $this->info('DENTFLUENCE SMOKE TEST — RESULT');
        $this->info('===============================');
        $this->line("Run ID:      {$run->runId}");
        $this->line("Environment: {$env} · DB: {$db} · Mode: {$run->mode}");
        $this->line('Started:     ' . $run->startedAt->toDateTimeString());
        $this->line('Finished:    ' . $finished->toDateTimeString() . ' (' . $run->startedAt->diffInSeconds($finished) . 's)');
        $this->newLine();

        foreach ($run->journeyCounts() as $journey => $c) {
            $pass = $c['pass'] === $c['total'];
            $this->line(sprintf(
                '%-14s %s  %d/%d',
                $journey,
                $pass ? '<info>PASS</info>' : '<error>FAIL</error>',
                $c['pass'],
                $c['total']
            ));
        }

        $this->newLine();
        $this->line('Data integrity failures: ' . count($run->failures(SmokeRun::CRITICAL)));
        $this->line('Unexpected duplicates:   ' . $run->duplicateFailures());
        $this->line('Workflow failures:       ' . count($run->failures(SmokeRun::WORKFLOW)));
        $this->line('Technical failures:      ' . count($run->failures(SmokeRun::TECHNICAL)));
        $this->line('Cosmetic issues:         ' . count($run->failures(SmokeRun::COSMETIC)) . ' (never fail the run)');
        $this->newLine();

        $this->line('Records created:  ' . count($run->records)
            . ($run->mode === SmokeRun::MODE_ROLLBACK ? ' (all rolled back)' : ''));
        if ($run->mode === SmokeRun::MODE_COMMIT) {
            $this->line('Records cleaned:  ' . $run->cleaned);
            $this->line('Records retained: ' . count($run->retained));
            foreach ($run->retained as $label) {
                $this->line("  · retained → {$label}  [{$run->runId}]");
            }
        }

        $failures = $run->failures();
        if ($failures !== []) {
            $this->newLine();
            $this->error('Failed checks:');
            foreach ($failures as $f) {
                $this->line("  [{$f['class']}] {$f['journey']} — {$f['name']}"
                    . ($f['note'] ? "  ({$f['note']})" : ''));
            }
        }

        $pass = $run->overallPass();
        $this->newLine();
        $this->line($pass ? '<info>OVERALL: PASS</info>' : '<error>OVERALL: FAIL</error>');

        return $pass;
    }
}
