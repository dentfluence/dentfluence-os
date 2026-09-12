<?php

namespace Tests\Feature\ClinicalLibrary;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\ClinicalLibrary\Concerns\BuildsClinicalFiles;
use Tests\TestCase;

/**
 * Who may fetch the bytes.
 *
 * These files used to sit on the public disk at /storage/clinical/{patient_id}/…
 * — anyone who could guess a patient id could download medical images without
 * logging in. Every request now passes through auth, a branch check, and (for
 * downloads) the audit trail.
 *
 * The branch check is the part with no UI and therefore no way to notice it
 * breaking: nothing on screen changes if it starts returning everyone's files,
 * and it stays correct only for as long as something asserts it. That is what
 * this class is.
 */
class SecureMediaAccessTest extends TestCase
{
    use RefreshDatabase;
    use BuildsClinicalFiles;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function fileOnBranch(int $branchId)
    {
        $patient = $this->makePatient(['branch_id' => $branchId]);
        $file    = $this->makeFile($patient);

        Storage::disk('local')->put($file->path, 'bytes');

        return $file;
    }

    public function test_a_guest_cannot_fetch_a_clinical_file_at_all(): void
    {
        $file = $this->fileOnBranch(1);

        $this->get(route('secure.media.file', $file->id))->assertRedirect(route('login'));
    }

    public function test_staff_can_fetch_a_file_from_their_own_branch(): void
    {
        $file = $this->fileOnBranch(1);
        $this->actingAs($this->makeUser(['role' => 'doctor', 'branch_id' => 1]));

        $this->get(route('secure.media.file', $file->id))->assertOk();
    }

    /**
     * The one that matters the day this is multi-tenant. Branch 2's dentist
     * asking for branch 1's patient photo is not a UI mistake to be handled
     * gracefully — it is a refusal.
     */
    public function test_staff_from_another_branch_are_refused(): void
    {
        $file = $this->fileOnBranch(1);
        $this->actingAs($this->makeUser(['role' => 'doctor', 'branch_id' => 2]));

        $this->get(route('secure.media.file', $file->id))->assertForbidden();
    }

    /**
     * Fail closed.
     *
     * patients.branch_id is NOT NULL, so the null branch this guard handles is
     * not a patient without a branch — it is a file whose patient is GONE:
     * soft-deleted, merged away, or a row that outlived its anchor. The bytes
     * are still on disk and the id is still a small integer anyone can count
     * up to. With no patient there is no branch to check against, and the only
     * safe answer is no.
     */
    public function test_a_file_whose_patient_is_gone_is_refused_rather_than_shared(): void
    {
        $file = $this->fileOnBranch(1);
        $file->patient->delete();
        $this->actingAs($this->makeUser(['role' => 'doctor', 'branch_id' => 1]));

        $this->get(route('secure.media.file', $file->id))->assertForbidden();
    }

    public function test_an_admin_reaches_every_branch(): void
    {
        $file = $this->fileOnBranch(2);
        $this->actingAs($this->makeUser(['role' => 'admin', 'branch_id' => 1]));

        $this->get(route('secure.media.file', $file->id))->assertOk();
    }

    /**
     * A thumbnail loading in a grid is not a disclosure event; a download is.
     * Only the second is written to the trail, or the trail becomes noise
     * nobody reads and stops being evidence of anything.
     */
    public function test_a_download_is_recorded_and_an_inline_view_is_not(): void
    {
        $file = $this->fileOnBranch(1);
        $user = $this->makeUser(['role' => 'doctor', 'branch_id' => 1]);
        $this->actingAs($user);

        $this->get(route('secure.media.file', $file->id))->assertOk();
        $this->assertSame(0, AuditLog::where('action', 'downloaded')->count(), 'a thumbnail is not a download');

        $this->get(route('secure.media.file', [$file->id, 'dl' => 1]))->assertOk();

        $entry = AuditLog::where('action', 'downloaded')->first();
        $this->assertNotNull($entry, 'a download leaves a trace with a name on it');
        $this->assertSame($user->id, $entry->user_id);
        $this->assertSame('clinical_files', $entry->module);
    }

    /** A row pointing at bytes that are gone is a 404, not a 200 with nothing in it. */
    public function test_a_missing_file_on_disk_is_a_404(): void
    {
        $file = $this->fileOnBranch(1);
        Storage::disk('local')->delete($file->path);
        $this->actingAs($this->makeUser(['role' => 'doctor', 'branch_id' => 1]));

        $this->get(route('secure.media.file', $file->id))->assertNotFound();
    }
}
