<?php

namespace App\Console\Commands;

use App\Models\Patient;
use App\Models\Wallet;
use App\Services\Patient\PatientMergeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * patients:merge-smoketest
 *
 * End-to-end runtime check for the patient merge, with ZERO residue: it creates
 * two dummy patients (+ a note child and a wallet balance on the loser), runs a
 * real merge, asserts the outcomes, then throws a sentinel to roll the whole
 * transaction back — nothing persists. Safe to run against the live dev DB.
 */
class PatientMergeSmokeTest extends Command
{
    protected $signature = 'patients:merge-smoketest';

    protected $description = 'Create dummy patients, merge them, assert results, then roll everything back (no residue).';

    public function handle(PatientMergeService $svc): int
    {
        $sentinel = new \RuntimeException('__SMOKETEST_ROLLBACK__');

        try {
            DB::transaction(function () use ($svc, $sentinel) {
                $master = Patient::create([
                    'name' => 'ZZ Smoketest Master', 'first_name' => 'ZZ', 'last_name' => 'Master',
                    'phone' => '9990000001', 'branch_id' => 1,
                    'city' => 'MasterCity', 'allergies' => ['Penicillin'],
                ]);
                $loser = Patient::create([
                    'name' => 'ZZ Smoketest Loser', 'first_name' => 'ZZ', 'last_name' => 'Loser',
                    'phone' => '9990000002', 'branch_id' => 1,
                    'city' => 'LoserCity', 'allergies' => ['Latex'],
                ]);

                // A blind patient_id child on the loser.
                DB::table('patient_notes')->insert([
                    'patient_id' => $loser->id, 'note' => 'ZZ smoketest note',
                    'note_type' => 'general', 'created_at' => now(), 'updated_at' => now(),
                ]);

                // Wallet balance on the loser; master starts empty.
                $lw = Wallet::forPatient($loser->id);
                $lw->balance_permanent = 500;
                $lw->balance_total = 500;
                $lw->save();
                $masterStart = (float) Wallet::forPatient($master->id)->balance_total;

                // Relationship-delegate scenario: give both a relationship and put
                // a today_actions row (one of the 4 tables added to the delegate
                // engine) on the loser's relationship, to prove the cascade moves it.
                $rel = fn (string $n) => \App\Models\Relationship::create([
                    'name' => $n, 'source' => 'phone_call', 'status' => 'active',
                    'score' => 0, 'relationship_since' => now()->toDateString(),
                ]);
                $masterRel = $rel('ZZ Master Rel');
                $loserRel  = $rel('ZZ Loser Rel');
                $master->relationship_id = $masterRel->id; $master->saveQuietly();
                $loser->relationship_id  = $loserRel->id;  $loser->saveQuietly();
                DB::table('today_actions')->insert([
                    'category' => 'recall', 'priority' => 'medium',
                    'patient_id' => $loser->id, 'relationship_id' => $loserRel->id,
                    'patient_name' => 'ZZ Loser', 'created_at' => now(), 'updated_at' => now(),
                ]);

                // Reconciliation: choose the loser's city; allergies always union.
                $record = $svc->merge($master, $loser, ['city' => 'LoserCity'], null, 'smoketest');

                $master->refresh();
                $loser->refresh();
                $masterAllergies = (array) $master->allergies;
                $noteOwner   = (int) DB::table('patient_notes')->where('note', 'ZZ smoketest note')->value('patient_id');
                $masterTotal = (float) Wallet::forPatient($master->id)->balance_total;
                $loserWallet = Wallet::where('patient_id', $loser->id)->exists();
                $todayAction = DB::table('today_actions')->where('patient_name', 'ZZ Loser')->first();
                $loserRelFresh = \App\Models\Relationship::withTrashed()->find($loserRel->id);

                // Slice 4: the archived loser's profile URL should redirect to the master.
                $showResp = app(\App\Http\Controllers\PatientController::class)->show($loser);
                $redirectsToMaster = $showResp instanceof \Illuminate\Http\RedirectResponse
                    && str_contains($showResp->getTargetUrl(), '/patients/'.$master->id);

                // Slice 5: the merge is written onto the Patient Journey Timeline.
                $timelineLogged = \App\Models\Activity::where('event', 'patient.merged')
                    ->where('relationship_id', $masterRel->id)
                    ->where('subject_id', $master->id)
                    ->exists();

                $checks = [
                    'loser soft-deleted (archived)'         => $loser->trashed(),
                    'loser.merged_into_id = master'         => (int) $loser->merged_into_id === (int) $master->id,
                    'loser.retired kept on record'          => $record->retired_patient_id === $loser->patient_id,
                    'note child re-parented to master'      => $noteOwner === (int) $master->id,
                    'wallet summed onto master (0+500)'     => abs($masterTotal - ($masterStart + 500)) < 0.01,
                    'loser wallet removed'                  => $loserWallet === false,
                    'wallet_transfer logged (=500)'         => is_array($record->wallet_transfer)
                                                                && (float) ($record->wallet_transfer['total'] ?? 0) === 500.0,
                    'PatientMerge recorded for master'      => $record->exists
                                                                && (int) $record->surviving_patient_id === (int) $master->id,
                    'relationship delegate fired'           => ! is_null($record->relationship_merge_id),
                    'today_actions re-parented (engine ext)'=> $todayAction
                                                                && (int) $todayAction->relationship_id === (int) $masterRel->id,
                    'loser relationship soft-deleted'       => $loserRelFresh && $loserRelFresh->trashed(),
                    'field choice applied (city→loser)'     => $master->city === 'LoserCity',
                    'allergies unioned (both kept)'         => in_array('Penicillin', $masterAllergies, true)
                                                                && in_array('Latex', $masterAllergies, true),
                    'archived loser URL redirects to master'=> $redirectsToMaster,
                    'merge appears on Journey Timeline'     => $timelineLogged,
                    'reversal snapshot captured (master)'   => is_array($record->reversal ?? null)
                                                                && (($record->reversal['master_before']['city'] ?? null) === 'MasterCity'),
                ];

                $this->newLine();
                $allPass = true;
                foreach ($checks as $label => $ok) {
                    $this->line(($ok ? '  <info>PASS</info>' : '  <error>FAIL</error>')." — {$label}");
                    $allPass = $allPass && $ok;
                }
                $this->newLine();
                $this->line($allPass
                    ? '<info>ALL CHECKS PASSED</info>'
                    : '<error>SOME CHECKS FAILED — review above</error>');

                throw $sentinel; // discard everything — no residue
            });
        } catch (\Throwable $e) {
            if ($e === $sentinel) {
                $this->info('Rolled back — no test data persisted.');
                return self::SUCCESS;
            }
            $this->error('Smoke test errored (rolled back): '.$e->getMessage());
            $this->line($e->getFile().':'.$e->getLine());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
