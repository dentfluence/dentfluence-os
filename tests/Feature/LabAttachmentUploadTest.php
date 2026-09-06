<?php

namespace Tests\Feature;

use App\Models\LabCase;
use App\Models\LabCaseAttachment;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Lab attachment upload — the round trip, writer AND reader.
 *
 * On 5 Sep 2026 the first ever lab attachment upload returned a 500 in
 * production. Cause: LabController::attachmentStore passed `file_name` and
 * `file_size`, but the columns are `original_name` and `size_bytes`. The
 * model's $fillable matches the table, so both keys were silently dropped,
 * original_name went in NULL against a NOT NULL column, and the insert blew
 * up. lab/show.blade.php read the same two wrong names, so even a successful
 * upload would have rendered a blank filename and "0 KB".
 *
 * It survived because the table had ZERO rows — W-2 measured that and used it
 * to justify deferring the fix. That reasoning was wrong: "nobody uses it" is
 * only true until somebody does, and the first person to try was the owner.
 *
 * This is the third writer/reader name mismatch found in two days (the W-2
 * storage disk, the W-4 view spacing, this). The suite is blind to all of
 * them, so the assertions below deliberately cover BOTH ends: the row is
 * written with the real column names, AND the attributes the view reads are
 * actually populated.
 */
class LabAttachmentUploadTest extends TestCase
{
    use RefreshDatabase;

    private function labCase(): LabCase
    {
        $patient = Patient::create([
            'first_name' => 'Attachment',
            'last_name'  => 'Case',
            'name'       => 'Attachment Case',
            'gender'     => 'male',
            'phone'      => '9' . random_int(100000000, 999999999),
            'branch_id'  => 1,
        ]);

        return LabCase::create([
            'patient_id'    => $patient->id,
            'work_category' => 'Crown & Bridge',
            'status'        => 'order_placed',
            'branch_id'     => 1,
        ]);
    }

    public function test_an_uploaded_attachment_persists_the_columns_that_actually_exist(): void
    {
        Storage::fake('local');
        $case = $this->labCase();
        $user = User::factory()->create();

        $file = UploadedFile::fake()->image('shade-photo.jpg')->size(128); // KB

        $path = $file->store('lab-attachments', 'local');
        $case->attachments()->create([
            'file_path'     => $path,
            'original_name' => $file->getClientOriginalName(),
            'size_bytes'    => $file->getSize(),
            'mime_type'     => $file->getMimeType(),
            'uploaded_by'   => $user->id,
        ]);

        $this->assertDatabaseHas('lab_case_attachments', [
            'lab_case_id'   => $case->id,
            'original_name' => 'shade-photo.jpg',
        ]);

        $att = LabCaseAttachment::first();
        $this->assertNotNull($att->original_name, 'original_name is NOT NULL in the schema');
        $this->assertGreaterThan(0, $att->size_bytes, 'size_bytes must be the real byte count');
        $this->assertSame('other', $att->category, 'category has no UI yet and defaults to other');
    }

    /**
     * The exact failure that shipped: passing keys that are not columns.
     * $fillable drops them, original_name stays NULL, and the NOT NULL
     * constraint rejects the row.
     */
    public function test_the_old_wrong_column_names_are_rejected_not_silently_accepted(): void
    {
        $case = $this->labCase();

        $this->expectException(\Illuminate\Database\QueryException::class);

        $case->attachments()->create([
            'file_path'   => 'lab-attachments/whatever.jpg',
            'file_name'   => 'whatever.jpg',   // not a column
            'file_size'   => 1234,             // not a column
            'mime_type'   => 'image/jpeg',
        ]);
    }

    /** The attributes lab/show.blade.php renders must exist on the model. */
    public function test_the_view_reads_attributes_that_the_model_actually_has(): void
    {
        $case = $this->labCase();
        $case->attachments()->create([
            'file_path'     => 'lab-attachments/xray.jpg',
            'original_name' => 'xray.jpg',
            'size_bytes'    => 204800,
            'mime_type'     => 'image/jpeg',
        ]);

        $att = LabCaseAttachment::first();

        // lab/show.blade.php uses these three. If a rename ever breaks them
        // again the page renders a blank name and "0 KB" with no error at all.
        $this->assertSame('xray.jpg', $att->original_name);
        $this->assertSame(200, (int) round($att->size_bytes / 1024), 'the "KB" the view prints');
        $this->assertNotNull($att->url(), 'url() feeds the <img src> and the download link');
    }
}
