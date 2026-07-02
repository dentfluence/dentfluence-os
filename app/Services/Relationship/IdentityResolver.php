<?php

namespace App\Services\Relationship;

use App\Models\Relationship;
use Illuminate\Support\Collection;

/**
 * IdentityResolver — Phase 1 (Workstream A).
 *
 * The matching component of the Relationship Engine. It answers "who is this
 * person?" — read-only. It creates nothing and merges nothing; it only finds
 * existing relationships and surfaces possible duplicates for human review.
 *
 * Phase 1 keeps matching conservative (exact phone / exact email) to mirror
 * the existing RelationshipEngine::findOrCreate semantics. `normalizePhone()`
 * is provided for future normalized matching and for the (later) backfill.
 *
 * This service owns NO write path in Sprint 1 — the backfill (Workstream G)
 * and the dedup review UI consume it later.
 */
class IdentityResolver
{
    /**
     * Best single existing match for a person, or null. Read-only.
     * Order mirrors findOrCreate: phone first (most reliable), then email.
     *
     * @param array{phone?:?string, email?:?string} $data
     */
    public function match(array $data): ?Relationship
    {
        $phone = $data['phone'] ?? null;
        $email = $data['email'] ?? null;

        if ($phone) {
            $byPhone = Relationship::byPhone($phone)->first();
            if ($byPhone) {
                return $byPhone;
            }
        }

        if ($email) {
            return Relationship::byEmail($email)->first();
        }

        return null;
    }

    /**
     * Other relationships that MIGHT be the same person as $relationship,
     * based on a shared exact phone or email. For the dedup review queue.
     * Never includes $relationship itself. Empty if it has no phone/email.
     *
     * @return Collection<int,Relationship>
     */
    public function findDuplicateCandidates(Relationship $relationship): Collection
    {
        if (! $relationship->phone && ! $relationship->email) {
            return collect();
        }

        return Relationship::query()
            ->where('id', '!=', $relationship->id)
            ->where(function ($q) use ($relationship) {
                if ($relationship->phone) {
                    $q->orWhere('phone', $relationship->phone);
                }
                if ($relationship->email) {
                    $q->orWhere('email', $relationship->email);
                }
            })
            ->get();
    }

    /**
     * Normalise a phone number for tolerant matching: strip non-digits and
     * keep the last 10 digits (India mobile). Returns null for empty input.
     * Read-only helper — does not mutate stored data.
     */
    public function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return null;
        }

        return strlen($digits) > 10 ? substr($digits, -10) : $digits;
    }
}
