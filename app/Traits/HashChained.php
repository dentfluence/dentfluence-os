<?php

namespace App\Traits;

use RuntimeException;

/**
 * HashChained (Phase A — tamper-evident audit logs)
 * -------------------------------------------------
 * Add `use HashChained;` to any append-only log model to make its table
 * tamper-evident, exactly like the DPDP consent_logs chain:
 *
 *   - On CREATE: each new row stores sha256(previous_row_hash | this_row_json)
 *     in `hash`, plus the previous row's hash in `prev_hash`. Altering or
 *     deleting any historical row breaks every hash after it — detectable by
 *     `php artisan audit:verify`.
 *   - On UPDATE / DELETE: blocked. The trail is append-only.
 *
 * Requirements:
 *   - The table needs nullable `prev_hash` and `hash` columns (char/string 64).
 *   - Rows must never be edited or removed (verified for our audit tables).
 *
 * The chain is per-table (each model has its own chain). At clinic scale the
 * extra "read the last hash" SELECT per insert is negligible.
 */
trait HashChained
{
    /** Keys never included in the hash (set after/around insert, or self-referential). */
    protected static array $chainExclude = ['id', 'hash', 'prev_hash', 'updated_at'];

    public static function bootHashChained(): void
    {
        static::creating(function ($model) {
            // Bind created_at into the hash (it isn't set yet at "creating" time).
            // IMPORTANT: zero the sub-second part. MySQL rounds fractional seconds
            // when storing into a DATETIME(0)/TIMESTAMP column, which would make
            // the stored value differ from the value we hashed here and break the
            // chain. startOfSecond() guarantees hashed value == stored value.
            if ($model->usesTimestamps() && empty($model->created_at)) {
                $model->created_at = $model->freshTimestamp()->startOfSecond();
            }

            $prev = static::query()->orderByDesc('id')->value('hash');

            $model->prev_hash = $prev;
            $model->hash      = static::chainComputeHash($prev, static::chainCanonical($model->getAttributes()));
        });

        static::updating(function () {
            throw new RuntimeException(static::class . ' rows are append-only and cannot be modified.');
        });

        static::deleting(function () {
            throw new RuntimeException(static::class . ' rows are append-only and cannot be deleted.');
        });
    }

    /**
     * The exact, ordered set of fields the hash is computed over. Excludes the
     * chain columns themselves and the auto id; everything else is sorted by key
     * so the result is deterministic regardless of attribute insertion order.
     */
    public static function chainCanonical(array $attributes): array
    {
        $canonical = array_diff_key($attributes, array_flip(static::$chainExclude));

        // Normalise values so the hash is STABLE across a database round-trip.
        //
        // 2026-07-14 — this is the fix for a chain break dating to 2026-07-04.
        // old_values/new_values are JSON columns. At write time Eloquent hands
        // us the compact serialisation ({"id":117,"type":"x"}) and we hashed
        // THAT string. MySQL then stores it in a JSON column, which re-formats
        // it — it inserts a space after each colon AND REORDERS OBJECT KEYS
        // (by key length, then lexically). Reading the row back therefore
        // yielded a different string to the one that was hashed, so every row
        // carrying a non-empty JSON payload failed verification.
        //
        // The rows before the break all happened to have empty old/new values
        // (login/logout events), which is the only reason it went unnoticed:
        // the first row with real JSON content was the first model audit entry.
        //
        // Hashing a canonical form (decoded → recursively key-sorted →
        // re-encoded) makes write-time and read-time identical regardless of
        // how MySQL chooses to store or reformat the text.
        foreach ($canonical as $k => $v) {
            if (is_array($v)) {
                $canonical[$k] = static::chainEncodeJson($v);
            } elseif ($v instanceof \DateTimeInterface) {
                $canonical[$k] = $v->format('Y-m-d H:i:s');
            } elseif (is_string($v) && $v !== '' && ($v[0] === '{' || $v[0] === '[')) {
                // Looks like a JSON payload read back from the DB — canonicalise
                // it the same way we canonicalise it on write.
                //
                // Empty payloads are left EXACTLY as stored: '{}' and '[]' both
                // decode to an empty PHP array and would both re-encode to '[]',
                // which would change the hash of rows that currently verify.
                $decoded = json_decode($v, true);
                if (is_array($decoded) && $decoded !== []) {
                    $canonical[$k] = static::chainEncodeJson($decoded);
                }
            }
        }

        ksort($canonical);
        return $canonical;
    }

    /**
     * Deterministic JSON: keys sorted recursively, fixed flags. Independent of
     * insertion order, whitespace, and whatever MySQL does to a JSON column.
     */
    protected static function chainEncodeJson(array $value): string
    {
        $sort = function (array $arr) use (&$sort): array {
            foreach ($arr as $k => $v) {
                if (is_array($v)) {
                    $arr[$k] = $sort($v);
                }
            }

            // Only sort associative arrays; preserve list order.
            if (! array_is_list($arr)) {
                ksort($arr);
            }

            return $arr;
        };

        return json_encode($sort($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * KEYED hash (HMAC-SHA256) of "prev_hash | canonical-json".
     *
     * 2026-07-14 (production hardening): this was previously a plain, unkeyed
     * sha256. That is only tamper-EVIDENT against an app-layer edit — an
     * attacker with direct database write access (a rogue admin, a stolen DB
     * credential, a restored backup) is exactly the actor an audit log defends
     * against, and they could simply edit a historical row and RECOMPUTE the
     * hash for it and every row after it, producing a chain that passes
     * `audit:verify` cleanly.
     *
     * With an HMAC keyed by a secret held OUTSIDE the database, forging the
     * chain requires the application key as well as DB write access.
     *
     * The key is AUDIT_HASH_KEY, falling back to APP_KEY (which is already
     * outside the DB, so the fallback is still meaningfully better than
     * unkeyed). Set a dedicated AUDIT_HASH_KEY in production.
     */
    public static function chainComputeHash(?string $prevHash, array $canonical): string
    {
        $json = json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash_hmac('sha256', ((string) $prevHash) . '|' . $json, static::chainKey());
    }

    /**
     * The pre-HMAC hash format. Rows written before the HMAC change verify
     * against this, so switching the algorithm does NOT invalidate the existing
     * chain (which would be indistinguishable from tampering).
     */
    public static function chainLegacyHash(?string $prevHash, array $canonical): string
    {
        $json = json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash('sha256', ((string) $prevHash) . '|' . $json);
    }

    /** Secret used to key the chain. Never stored in the database. */
    protected static function chainKey(): string
    {
        return (string) (config('audit.hash_key') ?: config('app.key'));
    }

    /**
     * Recompute the whole chain in id order and confirm nothing was tampered.
     *
     * Accepts EITHER the keyed (current) or legacy unkeyed hash for a given
     * row, so a database containing rows from both eras verifies cleanly.
     * `legacy_rows` reports how many rows still carry the weaker format.
     *
     * @return array{ok: bool, checked: int, first_bad_id: ?int, legacy_rows: int}
     */
    public static function verifyChain(): array
    {
        $prev       = null;
        $checked    = 0;
        $legacyRows = 0;

        foreach (static::query()->orderBy('id')->cursor() as $row) {
            $canonical = static::chainCanonical($row->getAttributes());
            $actual    = (string) $row->hash;

            $keyed  = static::chainComputeHash($prev, $canonical);
            $isKeyed = hash_equals($keyed, $actual);

            $isLegacy = false;
            if (! $isKeyed) {
                $isLegacy = hash_equals(static::chainLegacyHash($prev, $canonical), $actual);
            }

            if ((! $isKeyed && ! $isLegacy) || (string) $row->prev_hash !== (string) $prev) {
                return [
                    'ok'           => false,
                    'checked'      => $checked,
                    'first_bad_id' => $row->id,
                    'legacy_rows'  => $legacyRows,
                ];
            }

            if ($isLegacy) {
                $legacyRows++;
            }

            $prev = $row->hash;
            $checked++;
        }

        return [
            'ok'           => true,
            'checked'      => $checked,
            'first_bad_id' => null,
            'legacy_rows'  => $legacyRows,
        ];
    }
}
