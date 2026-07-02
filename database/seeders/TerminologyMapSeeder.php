<?php

namespace Database\Seeders;

use App\Models\TerminologyMap;
use Illuminate\Database\Seeder;

/**
 * Seeds the terminology_maps table with the standard codes Dentfluence already
 * uses: gender, FDI tooth numbering, and a starter set of dental ICD-10 codes.
 * Idempotent (updateOrCreate) — safe to run repeatedly. More codes are just more rows.
 *
 * Run:  php artisan db:seed --class=Database\\Seeders\\TerminologyMapSeeder
 */
class TerminologyMapSeeder extends Seeder
{
    public function run(): void
    {
        // ── Gender → FHIR administrative-gender ──
        foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other', 'unknown' => 'Unknown'] as $code => $display) {
            $this->put('gender', $code, $display, 'http://hl7.org/fhir/administrative-gender', $code, $display);
        }

        // ── FDI tooth numbering → ISO 3950 ──
        // Permanent teeth: quadrants 1-4, positions 1-8.
        $quadLabel = [1 => 'Upper right', 2 => 'Upper left', 3 => 'Lower left', 4 => 'Lower right'];
        foreach ($quadLabel as $q => $label) {
            for ($pos = 1; $pos <= 8; $pos++) {
                $fdi = "{$q}{$pos}";
                $this->put('tooth', $fdi, "FDI {$fdi}", 'urn:iso:std:iso:3950', $fdi, "{$label} tooth {$pos}");
            }
        }

        // ── Common dental diagnoses → ICD-10 ──
        $icd = [
            'K02.9' => 'Dental caries, unspecified',
            'K04.0' => 'Pulpitis',
            'K04.7' => 'Periapical abscess without sinus',
            'K05.1' => 'Chronic gingivitis',
            'K05.3' => 'Chronic periodontitis',
            'K08.1' => 'Complete loss of teeth',
            'K00.6' => 'Disturbances in tooth eruption',
        ];
        foreach ($icd as $code => $display) {
            $this->put('condition', $code, $display, 'http://hl7.org/fhir/sid/icd-10', $code, $display);
        }
    }

    private function put(string $domain, string $localCode, string $localTerm, string $system, string $code, string $display): void
    {
        TerminologyMap::updateOrCreate(
            ['domain' => $domain, 'local_code' => $localCode],
            [
                'local_term'       => $localTerm,
                'standard_system'  => $system,
                'standard_code'    => $code,
                'standard_display' => $display,
                'is_active'        => true,
            ]
        );
    }
}
