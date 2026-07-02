<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * patients:encrypt-phi  (Phase A — PHI encryption backfill)
 * ---------------------------------------------------------
 * Encrypts EXISTING patient rows in place. New writes are already encrypted by
 * the model casts (app/Casts/Encrypted + EncryptedArray); this handles rows that
 * existed before those casts were switched on.
 *
 * How it stays safe:
 *  - Reads raw column values via the query builder (bypassing the model casts).
 *  - Skips any value that is already encrypted (tries to decrypt it first), so
 *    the command is IDEMPOTENT — safe to run more than once.
 *  - Encrypts text and JSON columns identically (the JSON columns hold a JSON
 *    string which the EncryptedArray cast json_decodes after decrypting).
 *
 * RUN ORDER:  php artisan migrate   (widen columns)  →  php artisan patients:encrypt-phi
 *
 *   php artisan patients:encrypt-phi --dry-run   # report only, change nothing
 *   php artisan patients:encrypt-phi             # encrypt for real
 */
class EncryptPatientPhi extends Command
{
    protected $signature = 'patients:encrypt-phi {--dry-run : Report what would change without writing}';

    protected $description = 'Encrypt existing PHI in place across patients, identifiers, finance/HR bank details and consultation notes (Phase A backfill).';

    /**
     * Map of table => columns to encrypt (text + JSON treated the same here).
     * Mirrors the model casts added in Phase A. Keep these two in sync.
     */
    private array $map = [
        'patients' => [
            'address', 'chief_complaint', 'medical_alert', 'current_medications',
            'alternate_phone', 'emergency_contact_number', 'abha_number', 'abha_address',
            'medical_conditions', 'dental_conditions', 'allergies',
        ],
        'patient_identifiers' => ['value'],
        'finance_bank_accounts' => ['account_number', 'ifsc_code', 'upi_id'],
        'hr_staff_profiles' => ['account_number', 'ifsc_code'],
        'consultations' => [
            // free-text narrative (Phase A Step 2)
            'hopi_auto', 'hopi_final', 'findings_summary_auto', 'findings_summary_final',
            'complaint_notes', 'diagnosis_notes', 'primary_diagnosis', 'secondary_diagnosis',
            'provisional_diagnosis', 'differential_diagnosis', 'advice', 'examination_notes',
            'prescription_notes', 'treatment_done', 'treatment_plan_note', 'follow_up_note',
            'raw_note', 'additional_findings', 'finishing_notes', 'risk_assessment',
            'procedure_performed', 'emergency_treatment_rendered',
            // structured clinical JSON (Phase A Part 3) — raw JSON string is
            // encrypted as-is; EncryptedArray json_decodes after decrypting.
            'clinical_data', 'chart_data', 'radio_data', 'dbm_checklist',
            'investigations', 'investigation_details', 'specialty_findings',
            'accepted_specialties', 'treatment_plan_best', 'treatment_plan_acceptable',
            'tx_emergency', 'tx_protective', 'tx_transformative', 'tx_teeth',
            'prescriptions', 'instructions',
        ],
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $this->info($dry ? 'DRY RUN — nothing will be written.' : 'Encrypting existing PHI at rest...');

        $report = [];
        foreach ($this->map as $table => $columns) {
            if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
                $report[] = [$table, 'table missing — skipped'];
                continue;
            }
            $report[] = [$table, (string) $this->encryptTable($table, $columns, $dry)];
        }

        $this->newLine();
        $this->table(['Table', 'Field values encrypted'], $report);

        $this->info($dry
            ? 'Dry run complete. Re-run without --dry-run to apply.'
            : 'Done. Open a few patient records to confirm fields read back correctly.');

        return self::SUCCESS;
    }

    /**
     * Encrypt the given columns for every row of a table. Returns the number of
     * individual field-values that were (or would be) encrypted.
     */
    private function encryptTable(string $table, array $columns, bool $dry): int
    {
        $count = 0;

        DB::table($table)->orderBy('id')->chunkById(200, function ($rows) use ($table, $columns, $dry, &$count) {
            foreach ($rows as $row) {
                $updates = [];

                foreach ($columns as $col) {
                    $raw = $row->{$col} ?? null;

                    if ($raw === null || $raw === '') {
                        continue;
                    }

                    if ($this->isEncrypted($raw)) {
                        continue; // already done — idempotent
                    }

                    $updates[$col] = Crypt::encryptString((string) $raw);
                    $count++;
                }

                if ($updates && ! $dry) {
                    DB::table($table)->where('id', $row->id)->update($updates);
                }
            }
        });

        return $count;
    }

    /** True if the value is already a Laravel-encrypted string. */
    private function isEncrypted(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        try {
            Crypt::decryptString($value);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
