<?php

namespace App\Services\Smoke;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * SmokeRun — shared context for one `dentfluence:smoke` execution.
 *
 * TEST INFRASTRUCTURE ONLY. Holds the unique Run ID, the execution mode,
 * the check ledger (with failure classification) and the registry of every
 * record the run created — so commit-mode cleanup can delete ONLY what this
 * exact run made, never anything else. Contains zero business logic: every
 * outcome it records was produced by the real production services.
 *
 * Modes
 * -----
 *  rollback (default)  All journeys run inside one DB transaction that is
 *                      always rolled back (same convention as
 *                      patients:register-smoketest). Zero residue — safe to
 *                      run on production after every deployment.
 *  commit              Records really persist (true end-to-end durability),
 *                      uniquely prefixed with the Run ID, then cleaned up
 *                      one-by-one from the registry. Anything that cannot be
 *                      safely removed is reported as retained, never bulk-
 *                      deleted.
 *
 * Failure classification (per CEO directive)
 * ------------------------------------------
 *  CRITICAL   wrong/duplicated/leaked persisted data, invariant violation
 *  WORKFLOW   an action fails, wrong status, missing event, broken lazy tab
 *  TECHNICAL  500s, exceptions, probe/auth failures
 *  COSMETIC   wording/layout — reported, but does NOT fail the run
 */
class SmokeRun
{
    public const MODE_ROLLBACK = 'rollback';
    public const MODE_COMMIT   = 'commit';

    public const CRITICAL  = 'CRITICAL';
    public const WORKFLOW  = 'WORKFLOW';
    public const TECHNICAL = 'TECHNICAL';
    public const COSMETIC  = 'COSMETIC';

    public readonly string $runId;
    public readonly Carbon $startedAt;

    /** @var array<int,array{journey:string,name:string,pass:bool,class:string,note:?string}> */
    public array $checks = [];

    /** @var array<int,array{model:class-string<Model>,id:int|string,label:string}> */
    public array $records = [];

    /** @var array<int,\Closure> extra cleanup steps (run before record deletion, commit mode only) */
    public array $cleanupSteps = [];

    /** @var array<int,string> labels of records left behind after a commit-mode run */
    public array $retained = [];

    public int $cleaned = 0;

    public function __construct(public readonly string $mode)
    {
        $this->startedAt = now();
        $this->runId     = 'SMOKE_' . $this->startedAt->format('Ymd_His');
    }

    /** The unique marker embedded in every entity this run creates. */
    public function marker(): string
    {
        return $this->runId;
    }

    /**
     * A clearly-fake, run-unique 10-digit phone number.
     * '99' + minute+second of run start + zero-padded sequence.
     */
    public function phone(int $seq): string
    {
        return '99' . $this->startedAt->format('is') . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /** Record one check outcome. Returns $pass so callers can chain decisions. */
    public function check(string $journey, string $name, bool $pass, string $class = self::WORKFLOW, ?string $note = null): bool
    {
        $this->checks[] = [
            'journey' => $journey,
            'name'    => $name,
            'pass'    => $pass,
            'class'   => $class,
            'note'    => $note,
        ];

        return $pass;
    }

    /** Register a created record so commit-mode cleanup can remove exactly it. */
    public function track(Model $model, string $label): void
    {
        $this->records[] = ['model' => $model::class, 'id' => $model->getKey(), 'label' => $label];
    }

    /** Register an extra cleanup closure (runs before record deletion, newest first). */
    public function onCleanup(\Closure $step): void
    {
        $this->cleanupSteps[] = $step;
    }

    // ── Report aggregations ──────────────────────────────────────────────────

    /** @return array<string,array{pass:int,total:int}> keyed by journey name */
    public function journeyCounts(): array
    {
        $out = [];
        foreach ($this->checks as $c) {
            $out[$c['journey']] ??= ['pass' => 0, 'total' => 0];
            $out[$c['journey']]['total']++;
            if ($c['pass']) {
                $out[$c['journey']]['pass']++;
            }
        }

        return $out;
    }

    /** @return array<int,array{journey:string,name:string,pass:bool,class:string,note:?string}> */
    public function failures(?string $class = null): array
    {
        return array_values(array_filter(
            $this->checks,
            fn ($c) => ! $c['pass'] && ($class === null || $c['class'] === $class)
        ));
    }

    /** Failed checks that indicate duplicate/double-counted records. */
    public function duplicateFailures(): int
    {
        return count(array_filter(
            $this->failures(),
            fn ($c) => (bool) preg_match('/duplicat|exactly on|double/i', $c['name'])
        ));
    }

    /** COSMETIC failures never fail the run; everything else does. */
    public function overallPass(): bool
    {
        foreach ($this->failures() as $f) {
            if ($f['class'] !== self::COSMETIC) {
                return false;
            }
        }

        return true;
    }
}
