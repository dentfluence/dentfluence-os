<?php

namespace Tests\Feature\ClinicalLibrary;

use App\Models\ClinicalFile;
use App\Services\ClinicalLibrary\ClinicalFileUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\ClinicalLibrary\Concerns\BuildsClinicalFiles;
use Tests\TestCase;

/**
 * The Clinical Library on the phone.
 *
 * Two things are being defended here, and only one of them is about search.
 *
 * The first is that the phone holds NO vocabulary and NO query of its own. The
 * Add Document screen used to ship eight hardcoded categories and seven file
 * extensions compiled into the APK — a fifth vocabulary and a sixth format
 * list, both already wrong, and neither fixable by deploying the server. The
 * options endpoint exists so that can never be true again, and the search
 * endpoint hands everything to the same service the web goes through.
 *
 * The second is the showcase, and it is the one that matters most. `present`
 * mode is what a patient sitting in the chair is shown. A file reaches it only
 * with consent GIVEN and marketing approval APPROVED, and the payload carries
 * no patient name and no patient id — not hidden by the app, absent from the
 * response. Anything that reaches the device has already left the clinic.
 */
class MobileLibraryApiTest extends TestCase
{
    use RefreshDatabase;
    use BuildsClinicalFiles;

    private function actingAsMobile(int $branchId = 1): \App\Models\User
    {
        $user = $this->makeUser(['role' => 'admin', 'branch_id' => $branchId]);
        Sanctum::actingAs($user);

        return $user;
    }

    // ── The phone holds no vocabulary ─────────────────────────────────────────

    public function test_every_vocabulary_the_phone_needs_is_served_by_the_server(): void
    {
        $this->actingAsMobile();

        $res = $this->getJson('/api/v1/clinical-library/options')->assertOk();
        $data = $res->json('data');

        $this->assertCount(
            count(ClinicalFile::TREATMENT_CATEGORIES),
            $data['treatments'],
            'all ten categories — the phone used to offer eight of its own, matching neither list'
        );
        $this->assertCount(count(ClinicalFile::STAGES), $data['stages']);
        $this->assertCount(count(ClinicalFile::FILE_TYPES), $data['file_types']);
        $this->assertArrayHasKey('regions', $data['teeth']);
        $this->assertArrayHasKey('quadrants', $data['teeth']);
    }

    /**
     * The phone's file picker offered jpg, jpeg, png, pdf, dcm, doc, docx — no
     * .avif, .xls, .xlsx or .stl, all of which the server accepts. A format
     * list compiled into an APK cannot be corrected by deploying the server,
     * which is exactly why it must not be compiled into the APK.
     */
    public function test_the_upload_allowlist_and_the_per_format_caps_come_from_the_service(): void
    {
        $this->actingAsMobile();

        $upload = $this->getJson('/api/v1/clinical-library/options')->json('data.upload');

        $this->assertSame(ClinicalFileUploadService::allowedExtensions(), $upload['allowed_extensions']);

        foreach (['avif', 'xls', 'xlsx', 'stl'] as $missingBefore) {
            $this->assertContains($missingBefore, $upload['allowed_extensions']);
        }

        $this->assertSame(15360, $upload['max_kb']['jpg']);
        $this->assertSame(102400, $upload['max_kb']['dcm']);
    }

    // ── Search is the web's search ────────────────────────────────────────────

    public function test_the_endpoint_finds_a_multi_tooth_lab_row_by_each_tooth(): void
    {
        $this->actingAsMobile();
        $patient = $this->makePatient(['branch_id' => 1]);
        $bridge  = $this->makeFile($patient, ['tooth_number' => '24, 25']);
        $this->makeFile($patient, ['tooth_number' => '36']);

        $ids = $this->getJson('/api/v1/clinical-library/search?q=25')->assertOk()->json('data.files.*.id');

        $this->assertSame([$bridge->id], $ids, 'the phone gets the same FIND_IN_SET behaviour the web does');
    }

    public function test_an_explicit_filter_beats_a_guessed_word_here_too(): void
    {
        $this->actingAsMobile();
        $patient = $this->makePatient(['branch_id' => 1]);
        $before  = $this->makeFile($patient, ['stage' => 'before']);
        $this->makeFile($patient, ['stage' => 'after']);

        $ids = $this->getJson('/api/v1/clinical-library/search?q=after&stage[]=before')
            ->assertOk()->json('data.files.*.id');

        $this->assertSame([$before->id], $ids);
    }

    public function test_what_the_box_was_understood_to_mean_comes_back_for_the_chips(): void
    {
        $this->actingAsMobile();

        $chips = $this->getJson('/api/v1/clinical-library/search?q=26+implant+after')
            ->assertOk()->json('data.interpreted');

        $this->assertContains('tooth', array_column($chips, 'type'));
        $this->assertContains('treatment', array_column($chips, 'type'));
        $this->assertContains('stage', array_column($chips, 'type'));
    }

    /** Every other mobile read is locked to the caller's branch. So is this. */
    public function test_the_phone_never_sees_another_branchs_files(): void
    {
        $this->actingAsMobile(1);

        $mine    = $this->makeFile($this->makePatient(['branch_id' => 1]), ['tooth_number' => '26']);
        $theirs  = $this->makeFile($this->makePatient(['branch_id' => 2]), ['tooth_number' => '26']);

        $ids = $this->getJson('/api/v1/clinical-library/search?q=26')->assertOk()->json('data.files.*.id');

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($theirs->id, $ids);
    }

    /**
     * The library reads are gated on `cms` (the module the web Clinical Library
     * lives under, and the slug the phone's Library tile already uses). The
     * raw-bytes route is gated on nothing but auth and the branch check, exactly
     * like secure.media.file, because the same file is reached from both the
     * library grid and the patient Documents tab and those carry different
     * module gates.
     */
    public function test_an_unauthenticated_caller_gets_nothing(): void
    {
        $this->getJson('/api/v1/clinical-library/search?q=26')->assertUnauthorized();
        $this->getJson('/api/v1/clinical-library/options')->assertUnauthorized();
        $this->getJson('/api/v1/clinical-library/showcase')->assertUnauthorized();
    }

    // ── The showcase, and the gate on it ─────────────────────────────────────

    /**
     * THE ONE THAT MATTERS. A case with no consent, or with marketing approval
     * still pending, must not reach present mode at all — and the app must not
     * be the thing deciding that.
     */
    public function test_present_mode_shows_only_what_consent_and_approval_allow(): void
    {
        $this->actingAsMobile();

        $ready  = $this->makePatient(['branch_id' => 1, 'name' => 'Ready Patient']);
        $noCons = $this->makePatient(['branch_id' => 1, 'name' => 'No Consent Patient']);
        $unappr = $this->makePatient(['branch_id' => 1, 'name' => 'Unapproved Patient']);

        foreach ([
            [$ready,  'given',     'approved'],
            [$noCons, 'not_given', 'approved'],
            [$unappr, 'given',     'pending'],
        ] as [$patient, $consent, $marketing]) {
            foreach (['before', 'after'] as $stage) {
                $this->makeFile($patient, [
                    'stage'                    => $stage,
                    'treatment_category'       => 'implant',
                    'is_case_library_eligible' => true,
                    'consent_status'           => $consent,
                    'marketing_status'         => $marketing,
                ]);
            }
        }

        $present = $this->getJson('/api/v1/clinical-library/showcase?mode=present')->assertOk()->json('data');

        $this->assertSame('present', $present['mode']);
        $this->assertCount(1, $present['cases'], 'one case is presentable; the other two are not');

        $browse = $this->getJson('/api/v1/clinical-library/showcase?mode=browse')->assertOk()->json('data');
        $this->assertCount(3, $browse['cases'], 'the clinician still sees all three internally');
    }

    /**
     * The payload itself. Not "the app hides the name" — the name is not in the
     * response. A name that reaches the device has already left the clinic, and
     * the next screen someone writes would show it.
     */
    public function test_present_mode_carries_no_patient_identity_at_all(): void
    {
        $this->actingAsMobile();

        $patient = $this->makePatient(['branch_id' => 1, 'name' => 'Zzyzx Testpatient']);

        foreach (['before', 'after'] as $stage) {
            $this->makeFile($patient, [
                'stage'                    => $stage,
                'treatment_category'       => 'implant',
                'is_case_library_eligible' => true,
                'consent_status'           => 'given',
                'marketing_status'         => 'approved',
            ]);
        }

        $res = $this->getJson('/api/v1/clinical-library/showcase?mode=present')->assertOk();

        $res->assertDontSee('Zzyzx');
        $res->assertDontSee('Testpatient');
        $res->assertDontSee('patient_name');
        $res->assertDontSee('patient_id');

        $case = $res->json('data.cases.0');
        $this->assertArrayNotHasKey('patient_name', $case);
        $this->assertArrayNotHasKey('patient_id', $case);
        $this->assertNotEmpty($case['before']);
        $this->assertNotEmpty($case['after']);

        foreach ([...$case['before'], ...$case['after']] as $file) {
            $this->assertArrayNotHasKey('patient_name', $file);
            $this->assertArrayNotHasKey('patient_id', $file);
            $this->assertArrayNotHasKey('notes', $file, 'clinical notes are not for the person in the chair');
        }
    }

    /** Browse mode says WHY a case cannot be presented, so it can be fixed. */
    public function test_browse_mode_names_what_is_blocking_a_case(): void
    {
        $this->actingAsMobile();

        $patient = $this->makePatient(['branch_id' => 1]);
        foreach (['before', 'after'] as $stage) {
            $this->makeFile($patient, [
                'stage'                    => $stage,
                'treatment_category'       => 'implant',
                'is_case_library_eligible' => true,
                'consent_status'           => 'not_given',
                'marketing_status'         => 'approved',
            ]);
        }

        $case = $this->getJson('/api/v1/clinical-library/showcase?mode=browse')->assertOk()->json('data.cases.0');

        $this->assertFalse($case['presentable']);
        $this->assertSame('Needs patient consent', $case['blocked_by']);
    }

    /** A "case" with no files in it is not a case. */
    public function test_a_file_not_flagged_for_the_case_library_is_not_a_case(): void
    {
        $this->actingAsMobile();
        $patient = $this->makePatient(['branch_id' => 1]);
        $this->makeFile($patient, ['stage' => 'before', 'is_case_library_eligible' => false]);

        $this->getJson('/api/v1/clinical-library/showcase?mode=browse')
            ->assertOk()->assertJsonPath('data.total', 0);
    }

    // ── The bytes ────────────────────────────────────────────────────────────

    public function test_the_phone_can_finally_fetch_a_file_it_is_allowed_to_see(): void
    {
        Storage::fake('local');
        $this->actingAsMobile(1);

        $file = $this->makeFile($this->makePatient(['branch_id' => 1]));
        Storage::disk('local')->put($file->path, 'bytes');

        $this->get('/api/v1/clinical-library/files/' . $file->id . '/raw')->assertOk();
    }

    public function test_and_cannot_fetch_one_from_another_branch(): void
    {
        Storage::fake('local');
        $this->actingAsMobile(1);

        $file = $this->makeFile($this->makePatient(['branch_id' => 2]));
        Storage::disk('local')->put($file->path, 'bytes');

        $this->get('/api/v1/clinical-library/files/' . $file->id . '/raw')->assertForbidden();
    }
}
