<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PatientService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * patients:register-smoketest
 *
 * Verifies the canonical PatientService::register() contract after the
 * "one mint point" refactor, with ZERO residue (rolls back):
 *   - normal registration auto-assigns a TDC,
 *   - a supplied patient_id (bulk import / migration) is preserved,
 *   - the fields the lead-conversion + import paths rely on (state, referred_by)
 *     are mapped.
 */
class PatientRegisterSmokeTest extends Command
{
    protected $signature = 'patients:register-smoketest';

    protected $description = 'Verify PatientService::register() (auto-TDC, source-ID passthrough, field mapping); rolls back.';

    public function handle(PatientService $patients): int
    {
        $actor = User::query()->whereNotNull('branch_id')->first() ?? User::query()->first();
        if (! $actor) {
            $this->error('No user available to act as registrar.');
            return self::FAILURE;
        }

        $sentinel = new \RuntimeException('__ROLLBACK__');
        try {
            DB::transaction(function () use ($patients, $actor, $sentinel) {
                $normal = $patients->register(
                    ['first_name' => 'ZZ', 'last_name' => 'Normal', 'phone' => '9990000010'], $actor
                );
                $preserved = $patients->register(
                    ['first_name' => 'ZZ', 'last_name' => 'Import', 'phone' => '9990000011', 'patient_id' => 'EXT-TEST-9999'], $actor
                );
                $fields = $patients->register(
                    ['first_name' => 'ZZ', 'last_name' => 'Fields', 'phone' => '9990000012',
                     'state' => 'Maharashtra', 'referred_by' => 'Dr Referrer'], $actor
                );

                $checks = [
                    'normal registration auto-assigns a TDC' => ! empty($normal->patient_id),
                    'supplied patient_id preserved (import)' => $preserved->patient_id === 'EXT-TEST-9999',
                    'state mapped'                           => $fields->state === 'Maharashtra',
                    'referred_by mapped'                     => $fields->referred_by === 'Dr Referrer',
                    'branch/creator from actor'              => (int) $normal->branch_id === (int) $actor->branch_id
                                                                && (int) $normal->created_by === (int) $actor->id,
                ];

                $this->newLine();
                $allPass = true;
                foreach ($checks as $label => $ok) {
                    $this->line(($ok ? '  <info>PASS</info>' : '  <error>FAIL</error>')." — {$label}");
                    $allPass = $allPass && $ok;
                }
                $this->newLine();
                $this->line($allPass ? '<info>ALL CHECKS PASSED</info>' : '<error>SOME CHECKS FAILED</error>');

                throw $sentinel;
            });
        } catch (\Throwable $e) {
            if ($e === $sentinel) {
                $this->info('Rolled back — no test data persisted.');
                return self::SUCCESS;
            }
            $this->error('Smoke test errored (rolled back): '.$e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
