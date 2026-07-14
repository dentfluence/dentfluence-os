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

];
