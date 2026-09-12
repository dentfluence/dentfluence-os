<?php

namespace Tests\Feature\ClinicalLibrary;

use App\Models\WatermarkSetting;
use App\Services\ClinicalLibrary\WatermarkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use Tests\Feature\ClinicalLibrary\Concerns\BuildsClinicalFiles;
use Tests\TestCase;

/**
 * What may be burned into a clinical photograph.
 *
 * A watermark is not a caption. It is written into the pixels of a file whose
 * entire purpose is to leave the clinic — marketing, teaching, case discussion,
 * a consent conversation with the next patient. Once it is in the image it
 * cannot be withdrawn, edited, or covered by a later consent decision, and the
 * clinic does not hold the only copy any more.
 *
 * So the rule, ruled on 12 Sep 2026: the stamp carries the CLINIC NAME and the
 * TREATMENT. The patient's name is not a switch, not a setting, and not a
 * feature request — it is a DPDP exposure the consent workflow cannot undo.
 * Three tests below defend that from three directions, because a rule enforced
 * in only one place is a rule with a way around it.
 */
class WatermarkRulesTest extends TestCase
{
    use RefreshDatabase;
    use BuildsClinicalFiles;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    /** buildWatermarkText() is private on purpose; this is the only caller that reaches in. */
    private function stampFor($file, array $overrides = []): string
    {
        $service = app(WatermarkService::class);

        $config = (new ReflectionMethod($service, 'buildConfig'))->invoke($service, $file, $overrides);

        return (new ReflectionMethod($service, 'buildWatermarkText'))->invoke($service, $file, $config);
    }

    // ── What the stamp says ───────────────────────────────────────────────────

    public function test_the_stamp_carries_the_clinic_name_and_the_treatment(): void
    {
        $file = $this->makeFile($this->makePatient(), ['procedure' => 'Root Canal']);

        $text = $this->stampFor($file, ['clinic_name' => 'Tulip Dental']);

        $this->assertSame('Tulip Dental  |  Root Canal', $text);
    }

    /**
     * Doctor name, stage, tooth and date are real switches and all default OFF.
     * A stamp that spans the whole frame stops being a credit line and starts
     * being damage to the photograph it is protecting.
     */
    public function test_everything_except_the_clinic_and_the_treatment_is_off_by_default(): void
    {
        $file = $this->makeFile($this->makePatient(), [
            'procedure'    => 'Implant',
            'stage'        => 'after',
            'tooth_number' => '26',
        ]);

        $text = $this->stampFor($file, ['clinic_name' => 'Tulip Dental', 'doctor_name' => 'Firke']);

        $this->assertSame('Tulip Dental  |  Implant', $text);
        $this->assertStringNotContainsString('After', $text);
        $this->assertStringNotContainsString('26', $text);
        $this->assertStringNotContainsString('Firke', $text);
    }

    public function test_the_optional_switches_work_when_a_clinic_deliberately_turns_them_on(): void
    {
        $file = $this->makeFile($this->makePatient(), [
            'procedure'    => 'Implant',
            'stage'        => 'after',
            'tooth_number' => '26',
        ]);

        $text = $this->stampFor($file, [
            'clinic_name'       => 'Tulip Dental',
            'doctor_name'       => 'Firke',
            'show_doctor_name'  => true,
            'show_stage'        => true,
            'show_tooth_number' => true,
        ]);

        $this->assertStringContainsString('Dr. Firke', $text);
        $this->assertStringContainsString('After', $text);
        $this->assertStringContainsString('Tooth 26', $text);
    }

    // ── The patient's name, defended three ways ───────────────────────────────

    /** 1. No override, however it is spelled, can put the name into the stamp. */
    public function test_no_runtime_override_can_put_a_patient_name_into_the_stamp(): void
    {
        $patient = $this->makePatient(['name' => 'Zzyzx Testpatient']);
        $file    = $this->makeFile($patient, ['procedure' => 'Implant']);
        $file->setRelation('patient', $patient);

        $text = $this->stampFor($file, [
            'clinic_name'        => 'Tulip Dental',
            'show_patient_name'  => true,
            'patient_name'       => 'Zzyzx Testpatient',
            'show_doctor_name'   => true,
            'show_stage'         => true,
            'show_tooth_number'  => true,
            'show_date'          => true,
        ]);

        $this->assertStringNotContainsString('Zzyzx', $text);
        $this->assertStringNotContainsString('Testpatient', $text);
    }

    /**
     * 2. The old settings form could post wm_patient_name. A saved file may
     * still carry it, so it is discarded on the way in rather than trusted.
     */
    public function test_a_saved_setting_asking_for_the_patient_name_is_discarded(): void
    {
        WatermarkSetting::save([
            'wm_enabled'      => true,
            'wm_clinic_name'  => true,
            'wm_treatment'    => true,
            'wm_patient_name' => true,
        ]);

        $patient = $this->makePatient(['name' => 'Zzyzx Testpatient']);
        $file    = $this->makeFile($patient, ['procedure' => 'Implant']);
        $file->setRelation('patient', $patient);

        $text = $this->stampFor($file, ['clinic_name' => 'Tulip Dental']);

        $this->assertSame('Tulip Dental  |  Implant', $text);
        $this->assertStringNotContainsString('Zzyzx', $text);
    }

    /**
     * 3. The structural guard. The two tests above prove the current code does
     * not read the patient; this one makes it hard to add a branch that does.
     * It is a blunt instrument and that is the point — the cost of adding a
     * patient-name line should include reading why it may not be added.
     */
    public function test_the_text_builder_contains_no_path_to_the_patient_at_all(): void
    {
        $method = new ReflectionMethod(WatermarkService::class, 'buildWatermarkText');
        $source = implode("\n", array_slice(
            file($method->getFileName()),
            $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1
        ));

        $this->assertStringNotContainsString(
            'patient',
            strtolower($source),
            'buildWatermarkText() must not reference the patient in any form — a name burned into '
            . 'an exported image cannot be withdrawn later, and these files exist to be shared'
        );
    }

    // ── The switch that actually switches ─────────────────────────────────────

    /**
     * The watermark was off for weeks without anyone knowing, because the
     * settings screen wrote keys the service never read. "Off" must now mean
     * off, cheaply: config is resolved before the image is ever opened, so a
     * disabled watermark costs no decode, no write and no second copy.
     */
    public function test_a_disabled_watermark_produces_no_second_copy(): void
    {
        WatermarkSetting::save(['wm_enabled' => false]);

        $patient = $this->makePatient();
        $file    = $this->makeFile($patient);
        Storage::disk('local')->put($file->path, 'not-really-a-jpeg');

        $result = app(WatermarkService::class)->generate($file);

        $this->assertNull($result);
        $this->assertCount(1, Storage::disk('local')->allFiles(), 'the original, and nothing beside it');
    }

    /** Nothing can be stamped onto a file GD cannot open. */
    public function test_a_file_no_renderer_can_open_is_never_stamped(): void
    {
        $file = $this->makeFile($this->makePatient(), [
            'file_type'         => 'stl',
            'original_filename' => 'upper-arch.stl',
            'mime_type'         => 'application/octet-stream',
            'path'              => 'patients/x/upper-arch.stl',
        ]);
        Storage::disk('local')->put($file->path, 'solid');

        $this->assertNull(app(WatermarkService::class)->generate($file));
    }

    // ── Position ──────────────────────────────────────────────────────────────

    /**
     * A watermark across the middle of a clinical photograph destroys the thing
     * it exists to protect. Anything unrecognised therefore falls back to a
     * corner — centre is only ever reached by someone choosing it.
     */
    public function test_an_unrecognised_position_falls_back_to_a_corner_never_the_middle(): void
    {
        $service  = app(WatermarkService::class);
        $normalise = new ReflectionMethod($service, 'normalisePosition');

        $this->assertSame('bottom-right', $normalise->invoke($service, 'nonsense'));
        $this->assertSame('bottom-right', $normalise->invoke($service, null));
        $this->assertSame('bottom-right', $normalise->invoke($service, ''));
        $this->assertSame('top-left', $normalise->invoke($service, 'Top Left'), 'the settings screen posts human labels');
        $this->assertSame('center', $normalise->invoke($service, 'center'), 'centre is reachable — only deliberately');
    }

    // ── Settings actually reaching the service ────────────────────────────────

    /**
     * The P3 bug in one assertion. The settings screen posts wm_* keys; the
     * service reads plain ones. For as long as those two vocabularies never
     * met, every toggle on that page was a picture of a setting.
     */
    public function test_the_settings_screens_key_names_reach_the_service(): void
    {
        WatermarkSetting::save([
            'wm_enabled'     => true,
            'wm_clinic_name' => true,
            'wm_treatment'   => false,
            'wm_position'    => 'Top Left',
            'wm_font_size'   => 30,
        ]);

        $file   = $this->makeFile($this->makePatient(), ['procedure' => 'Implant']);
        $config = (new ReflectionMethod(WatermarkService::class, 'buildConfig'))
            ->invoke(app(WatermarkService::class), $file, ['clinic_name' => 'Tulip Dental']);

        $this->assertTrue($config['enabled']);
        $this->assertTrue($config['show_clinic_name']);
        $this->assertFalse($config['show_treatment'], 'a toggle turned OFF must turn something off');
        $this->assertSame('top-left', $config['position']);
        $this->assertSame(30, (int) $config['font_size']);

        $this->assertSame('Tulip Dental', $this->stampFor($file, ['clinic_name' => 'Tulip Dental']));
    }

    /** The slider posts 10–100; the config speaks 0–1. Both must arrive as the same thing. */
    public function test_opacity_is_understood_whether_it_arrives_as_a_fraction_or_a_percentage(): void
    {
        $file   = $this->makeFile($this->makePatient());
        $method = new ReflectionMethod(WatermarkService::class, 'buildConfig');

        WatermarkSetting::save(['wm_opacity' => 70]);
        $this->assertEqualsWithDelta(0.70, $method->invoke(app(WatermarkService::class), $file, [])['opacity'], 0.001);

        $this->assertEqualsWithDelta(0.35, $method->invoke(app(WatermarkService::class), $file, ['opacity' => 0.35])['opacity'], 0.001);
    }
}
