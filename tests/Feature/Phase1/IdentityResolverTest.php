<?php

namespace Tests\Feature\Phase1;

use App\Models\Relationship;
use App\Services\Relationship\IdentityResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1 · Workstream A — IdentityResolver (read-only matching + dedup).
 */
class IdentityResolverTest extends TestCase
{
    use RefreshDatabase;

    private function makeRelationship(array $attrs = []): Relationship
    {
        return Relationship::create(array_merge([
            'name'               => 'Test Person',
            'status'             => 'active',
            'score'              => 0,
            'relationship_since' => now()->toDateString(),
        ], $attrs));
    }

    public function test_matches_by_phone_then_email(): void
    {
        $r = $this->makeRelationship(['phone' => '9998887776', 'email' => 'a@example.com']);
        $resolver = app(IdentityResolver::class);

        $this->assertSame($r->id, $resolver->match(['phone' => '9998887776'])?->id);
        $this->assertSame($r->id, $resolver->match(['email' => 'a@example.com'])?->id);
    }

    public function test_returns_null_when_no_match(): void
    {
        $this->makeRelationship(['phone' => '9998887776']);
        $resolver = app(IdentityResolver::class);

        $this->assertNull($resolver->match(['phone' => '0000000000']));
        $this->assertNull($resolver->match([]));
    }

    public function test_normalises_phone(): void
    {
        $resolver = app(IdentityResolver::class);

        $this->assertSame('9998887776', $resolver->normalizePhone('+91 99988-87776'));
        $this->assertSame('12345', $resolver->normalizePhone('12345'));
        $this->assertNull($resolver->normalizePhone(null));
        $this->assertNull($resolver->normalizePhone('no-digits'));
    }

    public function test_finds_duplicate_candidates_by_shared_contact(): void
    {
        $a = $this->makeRelationship(['phone' => '9998887776']);
        $b = $this->makeRelationship(['phone' => '9998887776']); // same phone → candidate
        $this->makeRelationship(['phone' => '1112223334']);       // unrelated

        $candidates = app(IdentityResolver::class)->findDuplicateCandidates($a);

        $this->assertCount(1, $candidates);
        $this->assertSame($b->id, $candidates->first()->id);
    }

    public function test_no_candidates_when_no_contact_details(): void
    {
        $a = $this->makeRelationship(); // no phone/email
        $this->assertTrue(app(IdentityResolver::class)->findDuplicateCandidates($a)->isEmpty());
    }
}
