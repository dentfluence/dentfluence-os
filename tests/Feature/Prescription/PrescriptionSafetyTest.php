<?php

namespace Tests\Feature\Prescription;

use App\Models\Patient;
use App\Models\PatientAllergy;
use App\Models\Prescription\RxDrug;
use App\Services\Prescription\PrescriptionAlertService;
use App\Services\Prescription\PrescriptionRiskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Prescription safety — the checks that had no test at all.
 *
 * Two services are covered. PrescriptionAlertService grades a composed
 * prescription; PrescriptionRiskService grades candidate drugs before one is
 * picked. The first case below is the bug that shipped: a combination product
 * and its single-molecule counterpart never matched, so 650mg of paracetamol
 * twice over raised nothing.
 */
class PrescriptionSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function patient(array $attributes = []): Patient
    {
        return Patient::create(array_merge([
            'name'      => 'Rx Test Patient',
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ], $attributes));
    }

    private function drug(string $brand, string $molecules, array $extra = []): RxDrug
    {
        return RxDrug::create(array_merge([
            'brand_name'               => $brand,
            'duplicate_molecule_group' => $molecules,
            'composition'              => $molecules,
            'dosage_form'              => 'Tablet',
            'is_active'                => true,
        ], $extra));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Duplicate molecule — the shipped bug
    // ─────────────────────────────────────────────────────────────────────────

    public function test_combination_and_single_molecule_products_raise_a_duplicate(): void
    {
        $patient = $this->patient();
        $combo   = $this->drug('Zerodol P', 'aceclofenac,paracetamol');
        $single  = $this->drug('Dolo 650', 'paracetamol');

        $alerts = app(PrescriptionAlertService::class)->check($patient, [
            ['drug_id' => $combo->id,  'drug_name' => 'Zerodol P'],
            ['drug_id' => $single->id, 'drug_name' => 'Dolo 650'],
        ]);

        $duplicates = array_values(array_filter($alerts, fn ($a) => $a['type'] === 'duplicate'));

        $this->assertCount(1, $duplicates, 'Paracetamol appears in both products and must be flagged once.');
        $this->assertSame('DUP_PARACETAMOL', $duplicates[0]['code']);
        $this->assertStringContainsString('paracetamol', $duplicates[0]['message']);
    }

    public function test_two_combinations_sharing_one_molecule_raise_a_duplicate(): void
    {
        $patient = $this->patient();
        $a = $this->drug('Combiflam', 'ibuprofen,paracetamol');
        $b = $this->drug('Zerodol P', 'aceclofenac,paracetamol');

        $alerts = app(PrescriptionAlertService::class)->check($patient, [
            ['drug_id' => $a->id, 'drug_name' => 'Combiflam'],
            ['drug_id' => $b->id, 'drug_name' => 'Zerodol P'],
        ]);

        $codes = array_column(array_filter($alerts, fn ($x) => $x['type'] === 'duplicate'), 'code');
        $this->assertContains('DUP_PARACETAMOL', $codes);
    }

    public function test_unrelated_molecules_do_not_raise_a_duplicate(): void
    {
        $patient = $this->patient();
        $a = $this->drug('Novamox 500', 'amoxicillin');
        $b = $this->drug('Dolo 650', 'paracetamol');

        $alerts = app(PrescriptionAlertService::class)->check($patient, [
            ['drug_id' => $a->id, 'drug_name' => 'Novamox 500'],
            ['drug_id' => $b->id, 'drug_name' => 'Dolo 650'],
        ]);

        $this->assertEmpty(array_filter($alerts, fn ($x) => $x['type'] === 'duplicate'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Point-of-selection grading
    // ─────────────────────────────────────────────────────────────────────────

    public function test_structured_allergy_record_contraindicates_the_molecule(): void
    {
        $patient = $this->patient();
        PatientAllergy::create([
            'patient_id'  => $patient->id,
            'substance'   => 'Penicillin',
            'category'    => 'medication',
            'criticality' => 'high',
            'reaction'    => 'Rash',
        ]);

        DB::table('rx_allergy_rules')->insert([
            'allergy_keyword' => 'penicillin',
            'blocks_molecule' => 'amoxicillin',
            'blocks_class'    => 'penicillin',
            'severity'        => 'critical',
            'alert_message'   => 'Recorded penicillin allergy.',
            'is_active'       => 1,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $amox = $this->drug('Novamox 500', 'amoxicillin', ['antibiotic_class' => 'penicillin']);
        $para = $this->drug('Dolo 650', 'paracetamol');

        $graded = app(PrescriptionRiskService::class)
            ->assess($patient, RxDrug::whereIn('id', [$amox->id, $para->id])->get());

        $this->assertSame('contraindicated', $graded[$amox->id]['risk'],
            'The allergy lives in patient_allergies, which the checker previously ignored.');
        $this->assertSame('ok', $graded[$para->id]['risk']);
        $this->assertNotEmpty($graded[$amox->id]['reasons']);
    }

    public function test_current_medication_raises_an_interaction(): void
    {
        $patient = $this->patient(['current_medications' => 'Warfarin 5mg daily']);

        DB::table('rx_drug_interaction_rules')->insert([
            'drug_a_molecule' => 'warfarin',
            'drug_a_class'    => null,
            'drug_b_molecule' => null,
            'drug_b_class'    => 'NSAID',
            'severity'        => 'critical',
            'alert_message'   => 'Warfarin with an NSAID sharply raises bleeding risk.',
            'is_active'       => 1,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $nsaid = $this->drug('Brufen 400', 'ibuprofen', ['composition' => 'ibuprofen NSAID']);

        $graded = app(PrescriptionRiskService::class)
            ->assess($patient, RxDrug::whereIn('id', [$nsaid->id])->get());

        $this->assertSame('contraindicated', $graded[$nsaid->id]['risk'],
            'Nothing previously compared a candidate drug against the patient\'s existing medication.');
    }

    public function test_pregnancy_category_d_is_contraindicated_when_pregnant(): void
    {
        $patient = $this->patient(['medical_conditions' => ['Pregnancy — 2nd trimester']]);

        $unsafe = $this->drug('Ketorol DT', 'ketorolac', ['pregnancy_category' => 'D']);
        $safe   = $this->drug('Dolo 650',   'paracetamol', ['pregnancy_category' => 'B']);

        $graded = app(PrescriptionRiskService::class)
            ->assess($patient, RxDrug::whereIn('id', [$unsafe->id, $safe->id])->get());

        $this->assertSame('contraindicated', $graded[$unsafe->id]['risk']);
        $this->assertSame('ok', $graded[$safe->id]['risk']);
    }

    public function test_paediatric_avoid_flag_grades_by_age(): void
    {
        $child = $this->patient(['date_of_birth' => now()->subYears(7)->toDateString()]);
        $drug  = $this->drug('Ketorol DT', 'ketorolac', ['pediatric_safety' => 'avoid']);

        $graded = app(PrescriptionRiskService::class)
            ->assess($child, RxDrug::whereIn('id', [$drug->id])->get());

        $this->assertSame('contraindicated', $graded[$drug->id]['risk']);
    }

    public function test_a_patient_with_no_recorded_history_grades_everything_ok(): void
    {
        $patient = $this->patient();
        $drug    = $this->drug('Dolo 650', 'paracetamol');

        $graded = app(PrescriptionRiskService::class)
            ->assess($patient, RxDrug::whereIn('id', [$drug->id])->get());

        $this->assertSame('ok', $graded[$drug->id]['risk'],
            'A clean grade means nothing was recorded — it is not a clearance.');
    }
}
