<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Audit chain key
    |--------------------------------------------------------------------------
    |
    | Secret used to key the tamper-evident hash chain on audit_logs and
    | consent_logs (App\Traits\HashChained).
    |
    | The chain used to be a plain sha256, which an attacker with direct DB
    | write access could simply recompute after editing history. Keying it with
    | an HMAC means forging the chain now also requires this secret — which
    | lives OUTSIDE the database.
    |
    | Falls back to APP_KEY when unset (still outside the DB, so still far
    | better than unkeyed), but set a dedicated AUDIT_HASH_KEY in production so
    | the audit trail's integrity doesn't share a fate with the app key.
    |
    | ⚠ Changing this key does NOT invalidate existing rows — verifyChain()
    | accepts the legacy format too — but rows written under a previous key
    | will fail verification. Set it once, before go-live, and don't rotate it
    | without a documented re-anchoring plan.
    |
    | Generate one with:  php -r "echo bin2hex(random_bytes(32));"
    |
    */
    'hash_key' => env('AUDIT_HASH_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Chain anchors
    |--------------------------------------------------------------------------
    |
    | table => the first row id the chain is AUTHORITATIVE from. Rows below it
    | are excluded from verification and reported every morning as unverifiable.
    |
    | An anchor is not a way to make audit:verify go green. It is a written
    | admission, kept in code, that a stretch of the trail cannot be vouched for
    | and why. Adding one requires a diagnosis first. --backfill, which rewrites
    | those rows' hashes so they pass, is the thing an attacker would do and is
    | never the answer.
    |
    | prescription_audit_logs => 182
    |   Rows 1-181 (09 Jul - 21 Sep 2026) do not verify, and nothing can make
    |   them. Measured 21 Sep 2026: the link is intact (prev_hash matches
    |   throughout, zero id gaps, so no row was deleted or inserted) and the
    |   content of every failing row is a bare "prescription created" entry with
    |   snapshot and notes both NULL - there is nothing in them worth forging.
    |   The cause is a code defect, proven by elimination: recomputing row 11's
    |   hash with the `snapshot` key REMOVED reproduces the stored hash exactly.
    |   At "creating" time Eloquent hands the trait only the attributes the
    |   writer set, so a nullable column nobody filled never entered the hash;
    |   read back, it arrives as null and the canonical set has an extra key.
    |   Hashed one way on write, another on read. Fixed in HashChained (the
    |   canonical form now fills every table column), which stops it recurring
    |   but cannot repair hashes already stored.
    |
    |   Note the older rows 1-10 DO verify: they predate 16 Jul and their hashes
    |   were rewritten by the 14 Jul re-anchor, which recomputed them from the
    |   database, where every column is present. That is why the break starts at
    |   11 and not at 1.
    |
    */
    'anchors' => [
        'prescription_audit_logs' => 182,
    ],

];
