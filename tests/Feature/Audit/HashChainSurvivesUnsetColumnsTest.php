<?php

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The audit hash chain had ZERO test coverage, which is why a break that began
 * on 16 Jul 2026 was still unexplained on 21 Sep — 171 of 181 prescription audit
 * rows unverifiable, with `audit:verify` shouting TAMPERED every morning at a
 * defect nobody could name.
 *
 * MEASURED CAUSE (21 Sep 2026): at "creating" time Eloquent hands the trait only
 * the attributes the WRITER set. A nullable column nobody filled in was absent
 * from the array and never entered the hash. Read the row back and every column
 * is present, so that column arrives as null and the canonical set has one more
 * key than it had on write. Same row, two shapes, two hashes.
 *
 * Proof at the time: recomputing row 11's hash with the `snapshot` key removed
 * reproduced the stored hash exactly.
 *
 * These tests fail if that asymmetry ever returns.
 */
class HashChainSurvivesUnsetColumnsTest extends TestCase
{
    use RefreshDatabase;

    /** The bug itself: a row whose writer left nullable columns alone must verify. */
    public function test_row_written_without_optional_columns_still_verifies(): void
    {
        // Deliberately sparse — no module, no ip_address, no user_agent, no
        // old_values/new_values. These are exactly the columns a caller skips.
        AuditLog::create([
            'user_id' => null,
            'action'  => 'login',
        ]);

        $result = AuditLog::verifyChain();

        $this->assertTrue(
            $result['ok'],
            'A row written without its optional columns must still verify after a database round-trip.'
        );
        $this->assertSame(1, $result['checked']);
    }

    /** The write-time and read-time canonical forms must be the same shape. */
    public function test_canonical_form_is_identical_on_write_and_on_read(): void
    {
        $written = AuditLog::create(['action' => 'logout']);

        $onWrite = AuditLog::chainCanonical($written->getAttributes());
        $onRead  = AuditLog::chainCanonical(AuditLog::findOrFail($written->id)->getAttributes());

        $this->assertSame(
            array_keys($onWrite),
            array_keys($onRead),
            'The hashed field set differed between write and read — this is the 16 Jul defect.'
        );
    }

    /** A chain of sparse rows still detects a real edit. The fix must not blunt the alarm. */
    public function test_chain_still_catches_a_tampered_row(): void
    {
        AuditLog::create(['action' => 'login']);
        $middle = AuditLog::create(['action' => 'logout']);
        AuditLog::create(['action' => 'login']);

        // Edit the row underneath the model guard, the way a rogue DB write would.
        DB::table('audit_logs')->where('id', $middle->id)->update(['action' => 'role_change']);

        $result = AuditLog::verifyChain();

        $this->assertFalse($result['ok'], 'An edited row must still break the chain.');
        $this->assertSame($middle->id, $result['first_bad_id']);
    }

    /** An anchor excludes older rows and reports them — it never calls them intact. */
    public function test_anchor_excludes_older_rows_and_counts_them(): void
    {
        $first  = AuditLog::create(['action' => 'login']);
        $second = AuditLog::create(['action' => 'logout']);

        // Break the first row so the chain would fail without an anchor.
        DB::table('audit_logs')->where('id', $first->id)->update(['action' => 'failed_login']);

        $this->assertFalse(AuditLog::verifyChain()['ok']);

        Config::set('audit.anchors', ['audit_logs' => $second->id]);

        $result = AuditLog::verifyChain();

        $this->assertTrue($result['ok'], 'Rows at and after the anchor form a valid chain.');
        $this->assertSame(1, $result['pre_anchor'], 'The excluded row must be counted, not hidden.');
        $this->assertSame(1, $result['checked']);
    }
}
