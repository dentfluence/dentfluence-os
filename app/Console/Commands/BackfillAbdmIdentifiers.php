<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Models\Patient;
use App\Models\PatientIdentifier;
use App\Models\PractitionerIdentifier;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * BackfillAbdmIdentifiers — ABDM Phase 1 · Wave 1
 *
 * Mirrors the identifiers that already exist on each record into the new
 * polymorphic identifier tables, so the "bundle of identifiers" model is
 * populated for all existing data (dual-write for the back-catalogue):
 *
 *   patients.patient_id     → patient_identifiers (type=internal)
 *   patients.abha_number    → patient_identifiers (type=abha_number)  [if present]
 *   users.id                → practitioner_identifiers (type=internal)
 *   hr_staff_profiles.license_number → practitioner_identifiers (type=council_reg) [if present]
 *
 * Also seeds the ABDM feature flags into app_settings as OFF (for visibility).
 *
 * SAFE: idempotent (firstOrCreate), read-mostly, writes only new rows. Run as
 * many times as you like. Nothing existing is modified.
 *
 * Usage:
 *   php artisan abdm:backfill-identifiers
 *   php artisan abdm:backfill-identifiers --dry-run
 */
class BackfillAbdmIdentifiers extends Command
{
    protected $signature = 'abdm:backfill-identifiers {--dry-run : Report what would change without writing}';

    protected $description = 'Mirror existing patient/practitioner identifiers into the ABDM identifier tables (idempotent).';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $this->info($dry ? 'DRY RUN — no rows will be written.' : 'Backfilling ABDM identifiers...');

        $created = ['patient_internal' => 0, 'patient_abha' => 0, 'prac_internal' => 0, 'prac_council' => 0];

        /* ── Patients ── */
        Patient::query()->select('id', 'patient_id', 'abha_number')->chunkById(500, function ($patients) use (&$created, $dry) {
            foreach ($patients as $p) {
                if (! empty($p->patient_id)) {
                    $created['patient_internal'] += $this->ensure($dry, PatientIdentifier::class, [
                        'patient_id'      => $p->id,
                        'identifier_type' => PatientIdentifier::TYPE_INTERNAL,
                    ], [
                        'value'      => $p->patient_id,
                        'is_primary' => true,
                        'status'     => 'active',
                        'source'     => 'import',
                    ]);
                }

                if (! empty($p->abha_number)) {
                    $created['patient_abha'] += $this->ensure($dry, PatientIdentifier::class, [
                        'patient_id'      => $p->id,
                        'identifier_type' => PatientIdentifier::TYPE_ABHA_NUMBER,
                    ], [
                        'value'      => $p->abha_number,
                        'system_uri' => 'https://healthid.ndhm.gov.in',
                        'status'     => 'active',
                        'source'     => 'import',
                    ]);
                }
            }
        });

        /* ── Practitioners (users + their hr profile) ── */
        User::query()->with('hrProfile:id,user_id,license_number')->chunkById(500, function ($users) use (&$created, $dry) {
            foreach ($users as $u) {
                $created['prac_internal'] += $this->ensure($dry, PractitionerIdentifier::class, [
                    'user_id'         => $u->id,
                    'identifier_type' => PractitionerIdentifier::TYPE_INTERNAL,
                ], [
                    'value'      => (string) $u->id,
                    'is_primary' => true,
                    'status'     => 'active',
                    'source'     => 'import',
                ]);

                $license = $u->hrProfile->license_number ?? null;
                if (! empty($license)) {
                    $created['prac_council'] += $this->ensure($dry, PractitionerIdentifier::class, [
                        'user_id'         => $u->id,
                        'identifier_type' => PractitionerIdentifier::TYPE_COUNCIL_REG,
                    ], [
                        'value'  => $license,
                        'status' => 'active',
                        'source' => 'import',
                    ]);
                }
            }
        });

        /* ── Seed ABDM feature flags (OFF) for visibility in app_settings ── */
        if (! $dry) {
            foreach ([
                'abdm_enabled'         => '0',
                'fhir_enabled'         => '0',
                'consent_required'     => '1',
                'abha_linking_enabled' => '0',
            ] as $key => $val) {
                if (AppSetting::where('key', $key)->doesntExist()) {
                    AppSetting::set($key, $val, 'feature_flags');
                }
            }
        }

        $this->table(
            ['What', 'Rows ' . ($dry ? 'to create' : 'created')],
            [
                ['Patient internal ids', $created['patient_internal']],
                ['Patient ABHA numbers', $created['patient_abha']],
                ['Practitioner internal ids', $created['prac_internal']],
                ['Practitioner council regs', $created['prac_council']],
            ]
        );

        $this->info($dry ? 'Dry run complete.' : 'Backfill complete.');
        return self::SUCCESS;
    }

    /**
     * firstOrCreate-style helper. Returns 1 if a row would be / was created, else 0.
     */
    private function ensure(bool $dry, string $model, array $match, array $extra): int
    {
        $exists = $model::where($match)->exists();
        if ($exists) return 0;
        if (! $dry) {
            $model::create(array_merge($match, $extra));
        }
        return 1;
    }
}
