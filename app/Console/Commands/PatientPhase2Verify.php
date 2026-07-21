<?php

namespace App\Console\Commands;

use App\Http\Controllers\Relationship\LeadPipelineController;
use App\Models\Lead;
use App\Models\Patient;
use App\Models\Relationship;
use App\Models\User;
use App\Services\PatientService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * patients:phase2-verify — runtime verification of the two Phase 2 refactors
 * that weren't browser-tested: Lead→Patient conversion and the import row mint.
 * Runs the REAL lead-conversion controller path and an import-shaped register()
 * call, asserts behaviour, then rolls everything back (zero residue).
 */
class PatientPhase2Verify extends Command
{
    protected $signature = 'patients:phase2-verify';

    protected $description = 'Runtime-verify Lead→Patient conversion and import register() path (rolls back).';

    public function handle(PatientService $patients): int
    {
        $actor = User::query()->whereNotNull('branch_id')->first() ?? User::query()->first();
        if (! $actor) {
            $this->error('No user available.');
            return self::FAILURE;
        }
        Auth::login($actor);

        $sentinel = new \RuntimeException('__ROLLBACK__');
        try {
            DB::transaction(function () use ($patients, $actor, $sentinel) {
                $checks = [];

                // ── 1. Lead → Patient conversion (real controller path) ──────────
                $rel = Relationship::create([
                    'name' => 'ZZ Lead Rel', 'source' => 'phone_call', 'status' => 'active',
                    'score' => 0, 'relationship_since' => now()->toDateString(),
                ]);
                $lead = Lead::create([
                    'name' => 'ZZ Convert Lead', 'phone' => '9990000201', 'stage' => 'new',
                    'referred_by' => 'Dr Referrer', 'treatment' => 'Root canal',
                    'gender' => 'male', 'occupation' => 'Teacher', 'location' => 'Kothrud',
                ]);
                // relationship_id is NOT mass-assignable on Lead (production sets it
                // via RelationshipEngine::linkLead()->saveQuietly()); set it directly.
                $lead->relationship_id = $rel->id;
                $lead->save();

                $resp = app(LeadPipelineController::class)->convertToPatient(new Request(), $lead->id);
                $data = $resp->getData(true);
                $patient = ! empty($data['patient_id']) ? Patient::find($data['patient_id']) : null;
                $lead->refresh();

                $checks['lead: conversion returns success']      = ($data['success'] ?? false) === true;
                $checks['lead: patient created']                 = (bool) $patient;
                $checks['lead: name mapped']                     = $patient && $patient->name === 'ZZ Convert Lead';
                $checks['lead: phone mapped']                    = $patient && $patient->phone === '9990000201';
                $checks['lead: referred_by mapped (new field)']  = $patient && $patient->referred_by === 'Dr Referrer';
                $checks['lead: chief_complaint = treatment']     = $patient && $patient->chief_complaint === 'Root canal';
                $checks['lead: TDC assigned']                    = $patient && ! empty($patient->patient_id);
                $checks['lead: stage → converted']               = $lead->stage === 'converted';
                $checks['lead: relationship link reused']        = $patient && (int) $patient->relationship_id === (int) $rel->id;

                // Idempotency: converting again reuses the same patient.
                $resp2 = app(LeadPipelineController::class)->convertToPatient(new Request(), $lead->id);
                $checks['lead: re-convert reuses same patient']  = ($resp2->getData(true)['patient_id'] ?? null) === $patient?->id;

                // ── 2. Import row via register() (source patient_id preserved) ───
                $importRow = [
                    'patient_id' => 'EXT-IMP-777', 'name' => 'ZZ Import Row',
                    'first_name' => 'ZZ', 'last_name' => 'Import', 'phone' => '9990000202',
                    'state' => 'Goa', 'branch_id' => $actor->branch_id, 'created_by' => $actor->id,
                ];
                $imp = $patients->register($importRow, $actor);

                $checks['import: source patient_id preserved']   = $imp->patient_id === 'EXT-IMP-777';
                $checks['import: state mapped']                  = $imp->state === 'Goa';
                $checks['import: name mapped']                   = $imp->name === 'ZZ Import Row';

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
            $this->error('Verification errored (rolled back): '.$e->getMessage());
            $this->line($e->getFile().':'.$e->getLine());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
