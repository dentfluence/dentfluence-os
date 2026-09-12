<?php

namespace Tests\Feature\ClinicalLibrary;

use App\Http\Controllers\ClinicalFileController;
use App\Http\Controllers\ConsultationController;
use App\Http\Controllers\LabController;
use App\Jobs\GenerateWatermark;
use App\Models\ClinicalFile;
use App\Models\LabCase;
use App\Services\ClinicalLibrary\ClinicalFileUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tests\Feature\ClinicalLibrary\Concerns\BuildsClinicalFiles;
use Tests\TestCase;

/**
 * The vault door — what may enter clinical_files, and on whose terms.
 *
 * Every bug this class pins down shipped silently. On 12 Sep 2026 a Canon RAW
 * (.CR2) uploaded cleanly, was stored, was labelled a photo because its MIME
 * starts with image/, and then could never be displayed or watermarked by
 * anything — a permanently broken tile with no error anywhere. Nothing in the
 * app objected, because nothing in the app had an opinion about formats: four
 * upload endpoints carried three different rules and the web one had none at
 * all.
 *
 * The allowlist and the per-format caps are now a single constant read through
 * one service, so the interesting question is no longer "is the list right"
 * but "does every door still walk through it". These tests ask that.
 */
class ClinicalFileVaultTest extends TestCase
{
    use RefreshDatabase;
    use BuildsClinicalFiles;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->actingAs($this->uploader());
    }

    private function service(): ClinicalFileUploadService
    {
        return app(ClinicalFileUploadService::class);
    }

    // ── One vault, three doors ────────────────────────────────────────────────

    /**
     * The module's whole purpose in one assertion: a photo taken at the chair,
     * a file dropped into the library, and a shade photo attached to a lab case
     * are the same kind of thing and belong in the same table. They used not to
     * be — consultation uploads went to clinical_media and lab attachments to
     * lab_case_attachments, so neither was ever visible in the library that
     * claimed to hold every clinical file.
     */
    public function test_all_three_doors_write_into_the_one_vault(): void
    {
        $patient = $this->makePatient();

        $this->service()->store(
            UploadedFile::fake()->image('from-patient-tab.jpg'),
            ['patient_id' => $patient->id]
        );

        $this->service()->store(
            UploadedFile::fake()->image('from-library.jpg'),
            ['patient_id' => $patient->id, 'source_type' => 'App\\Models\\Consultation', 'source_id' => 1]
        );

        $this->service()->store(
            UploadedFile::fake()->image('from-lab-case.jpg'),
            ['patient_id' => $patient->id, 'source_type' => LabCase::class, 'source_id' => 1, 'stage' => 'during']
        );

        $files = ClinicalFile::where('patient_id', $patient->id)->get();

        $this->assertCount(3, $files, 'three uploads, three rows, one table');
        $this->assertSame(['local'], $files->pluck('disk')->unique()->values()->all(), 'clinical media never sits on a public disk');
        $this->assertSame(
            [$patient->id],
            $files->pluck('patient_id')->unique()->values()->all(),
            'every file is anchored to a patient — that anchor is what the vault is'
        );
    }

    /**
     * The structural half of the same rule. A door that stops calling the
     * service keeps working and quietly re-opens every format hole this module
     * closed, so the wiring itself is asserted rather than assumed.
     */
    public function test_every_upload_door_still_goes_through_the_one_service(): void
    {
        $doors = [
            ClinicalFileController::class => 'the patient Documents tab',
            ConsultationController::class => 'photos captured during a consultation',
            LabController::class          => 'lab case attachments',
        ];

        foreach ($doors as $controller => $what) {
            $source = file_get_contents((new \ReflectionClass($controller))->getFileName());

            $this->assertStringContainsString(
                'ClinicalFileUploadService',
                $source,
                "{$what} must upload through ClinicalFileUploadService, not on its own terms"
            );
        }
    }

    // ── The allowlist ─────────────────────────────────────────────────────────

    /** The .CR2 of 12 Sep, refused at the one door instead of stored forever. */
    public function test_a_disallowed_extension_is_refused_by_the_service_itself(): void
    {
        $patient = $this->makePatient();

        try {
            $this->service()->store(
                UploadedFile::fake()->create('IMG_9636.CR2', 2048, 'image/x-canon-cr2'),
                ['patient_id' => $patient->id]
            );
            $this->fail('a .CR2 must never be accepted — it was, on 12 Sep 2026, and could never be shown again');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('rejected .cr2', $e->getMessage());
        }

        $this->assertSame(0, ClinicalFile::count(), 'a refused upload leaves no row behind');
        $this->assertEmpty(Storage::disk('local')->allFiles(), 'and no bytes on disk either');
    }

    /**
     * Controllers validate too, but the service is the last door and the only
     * one a new caller cannot forget. Both ends are asserted deliberately: this
     * module has already shipped three writer/reader mismatches where one end
     * was correct and the other was never checked.
     */
    public function test_the_shared_validation_rule_advertises_exactly_the_allowed_formats(): void
    {
        $rule = ClinicalFileUploadService::validationRule();
        $list = collect($rule)->first(fn ($r) => is_string($r) && str_starts_with($r, 'extensions:'));

        $this->assertNotNull(
            $list,
            'the allowlist is matched on the extension the upload carries — `mimes:` sniffs content instead, '
            . 'guesses .bin for every STL and DICOM, and rejects them however long the allowlist is'
        );

        foreach (['jpg', 'jpeg', 'png', 'avif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'stl', 'dcm'] as $allowed) {
            $this->assertStringContainsString($allowed, $list);
        }

        foreach (['cr2', 'nef', 'tiff', 'heic', 'heif'] as $refused) {
            $this->assertStringNotContainsString($refused, $list, ".{$refused} is not a decision anyone has made");
        }
    }

    // ── Per-format caps ───────────────────────────────────────────────────────

    /**
     * One blanket cap could never be right for both: 50 MB let a 50 MB JPEG in,
     * which no clinical photo needs, while still being too small for the CBCT a
     * lab case genuinely carries.
     */
    public function test_each_format_carries_its_own_cap(): void
    {
        $this->assertSame(15360, ClinicalFileUploadService::maxKbFor('jpg'),  '15 MB is generous for a clinical photo');
        $this->assertSame(15360, ClinicalFileUploadService::maxKbFor('PNG'),  'the extension is matched case-insensitively');
        $this->assertSame(25600, ClinicalFileUploadService::maxKbFor('pdf'));
        $this->assertSame(61440, ClinicalFileUploadService::maxKbFor('stl'));
        $this->assertSame(102400, ClinicalFileUploadService::maxKbFor('dcm'));
        $this->assertSame(15360, ClinicalFileUploadService::maxKbFor('unheard-of'), 'an unknown format gets the tightest cap, not the loosest');
    }

    /** An endpoint may be stricter than the format allows. Never looser. */
    public function test_an_endpoint_ceiling_can_tighten_a_cap_but_never_loosen_one(): void
    {
        $this->assertSame(20480, ClinicalFileUploadService::maxKbFor('dcm', 20480), 'the mobile documents endpoint caps everything at 20 MB');
        $this->assertSame(15360, ClinicalFileUploadService::maxKbFor('jpg', 51200), 'asking for 50 MB does not get you 50 MB');
    }

    /**
     * The cap that matters is the per-format one, and it has to bite BELOW the
     * blanket outer limit — otherwise a 20 MB JPEG sails through on the
     * strength of the 100 MB DICOM allowance sharing the same rule.
     */
    public function test_a_file_within_the_outer_limit_but_over_its_own_format_cap_is_rejected(): void
    {
        $validator = Validator::make(
            ['file' => UploadedFile::fake()->create('huge-smile.jpg', 20480, 'image/jpeg')],
            ['file' => ClinicalFileUploadService::validationRule()]
        );

        $this->assertTrue($validator->fails(), '20 MB is under the 100 MB outer max and over the 15 MB JPEG cap');
        $this->assertStringContainsString('.jpg file may be at most 15 MB', $validator->errors()->first('file'));
    }

    public function test_a_file_inside_its_own_cap_passes(): void
    {
        $validator = Validator::make(
            ['file' => UploadedFile::fake()->create('scan.stl', 40960, 'application/octet-stream')],
            ['file' => ClinicalFileUploadService::validationRule()]
        );

        $this->assertFalse($validator->fails(), '40 MB is well inside the 60 MB STL cap');
    }

    /**
     * The formats P1 added, arriving the way they really arrive.
     *
     * A genuine .stl and a genuine .dcm are both sniffed as
     * application/octet-stream, so a content-based rule guesses `.bin` for them
     * and refuses them no matter what the allowlist says. That is what was
     * happening: the constant listed stl and dcm, the validation rule threw
     * them out, and the only way to notice was to attach one to a lab case.
     */
    public function test_the_3d_and_imaging_formats_survive_validation_as_they_actually_arrive(): void
    {
        foreach (['upper-arch.stl', 'cbct-volume.dcm'] as $filename) {
            $validator = Validator::make(
                ['file' => UploadedFile::fake()->create($filename, 2048, 'application/octet-stream')],
                ['file' => ClinicalFileUploadService::validationRule()]
            );

            $this->assertFalse(
                $validator->fails(),
                "{$filename} is on the allowlist and must pass the rule that enforces it — got: "
                . $validator->errors()->first('file')
            );
        }
    }

    /**
     * uploaded_by is NOT NULL in the schema because every clinical file has
     * someone answerable for it. Auth::id() is null in a console command, a
     * queued job or an import — and without a guard that surfaced as a raw
     * SQLSTATE 1364 from inside Eloquent, which names nothing the caller can
     * act on. lab:migrate-attachments walks straight into this on any legacy
     * row whose uploader was never recorded.
     */
    public function test_a_file_with_no_named_uploader_is_refused_with_a_usable_message(): void
    {
        $patient = $this->makePatient();
        auth()->logout();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/requires uploaded_by/');

        $this->service()->store(UploadedFile::fake()->image('anonymous.jpg'), ['patient_id' => $patient->id]);
    }

    // ── What a file is, versus what can show it ───────────────────────────────

    /**
     * The distinction the CR2 taught us. A .dcm and a .stl both commonly arrive
     * as application/octet-stream, and some servers announce a .dcm as image/*
     * — which is exactly the route by which a file nothing can render ends up
     * labelled a photo and left as a broken tile forever. The extension is the
     * only honest signal for these two, so it has to be read FIRST.
     */
    public function test_stl_and_dicom_are_typed_by_extension_not_by_a_misreported_mime(): void
    {
        $patient = $this->makePatient();
        Queue::fake();

        $stl = $this->service()->store(
            UploadedFile::fake()->create('upper-arch.stl', 512, 'application/octet-stream'),
            ['patient_id' => $patient->id]
        );

        $dicom = $this->service()->store(
            UploadedFile::fake()->create('cbct-volume.dcm', 512, 'image/dicom'),
            ['patient_id' => $patient->id]
        );

        $this->assertSame('stl', $stl->file_type);
        $this->assertSame('cbct', $dicom->file_type, 'an image/* MIME on a .dcm must not make it a photo');
    }

    /**
     * isImage() answers "can a browser show this", not "is this clinically an
     * image". It used to answer the second question, which is how a CBCT — an
     * image by any clinical reading — got rendered into an <img> no browser can
     * decode.
     */
    public function test_a_file_nothing_can_render_says_so_instead_of_breaking_a_tile(): void
    {
        $patient = $this->makePatient();
        Queue::fake();

        $stl = $this->service()->store(
            UploadedFile::fake()->create('upper-arch.stl', 512, 'application/octet-stream'),
            ['patient_id' => $patient->id]
        );

        $this->assertFalse($stl->isImage(), 'no browser and no GD build can decode an STL');
        $this->assertSame('STL — open in your design software', $stl->no_preview_reason);
        $this->assertStringContainsString('file-placeholder', $stl->thumbnail_url, 'a placeholder, never a broken <img>');
    }

    public function test_a_cbct_is_clinically_an_image_and_still_not_a_renderable_one(): void
    {
        $patient = $this->makePatient();
        $cbct    = $this->makeFile($patient, [
            'file_type'         => 'cbct',
            'original_filename' => 'volume.dcm',
            'mime_type'         => 'application/dicom',
        ]);

        $this->assertTrue($cbct->isClinicalImageType(), 'a CBCT is an image to a dentist');
        $this->assertFalse($cbct->isImage(), 'and is not one to a browser');
    }

    // ── What happens after the row is written ─────────────────────────────────

    public function test_a_renderable_image_is_queued_for_watermarking(): void
    {
        Queue::fake();
        $patient = $this->makePatient();

        $this->service()->store(UploadedFile::fake()->image('smile.jpg'), ['patient_id' => $patient->id]);

        Queue::assertPushed(GenerateWatermark::class);
    }

    /** Nothing can be stamped onto a file GD cannot open, so nothing is queued. */
    public function test_a_non_renderable_file_is_never_queued_for_watermarking(): void
    {
        Queue::fake();
        $patient = $this->makePatient();

        $this->service()->store(
            UploadedFile::fake()->create('upper-arch.stl', 512, 'application/octet-stream'),
            ['patient_id' => $patient->id]
        );

        Queue::assertNotPushed(GenerateWatermark::class);
    }

    /** A file with no patient has no anchor, and the vault is the anchor. */
    public function test_a_file_with_no_patient_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/requires patient_id/');

        $this->service()->store(UploadedFile::fake()->image('orphan.jpg'), []);
    }
}
