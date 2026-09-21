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
        // 2026-09-21 — the fix for the prescription_audit_logs break (rows 11-181,
        // written from 16 Jul onward, none of which verify).
        //
        // At "creating" time $model->getAttributes() holds only the attributes the
        // WRITER actually set. A nullable column nobody filled in — snapshot, on
        // that table — is simply ABSENT from the array, so it never entered the
        // hash. Reading the row back returns every column, so snapshot arrives as
        // null and the canonical set has one more key than it did at write time.
        // Same string, different shape, different hash. It is the 14 Jul JSON bug
        // in a new costume: hashed one way on write, another on read.
        //
        // Filling every table column that is missing with null makes the two
        // identical. This changes NOTHING for rows already stored: a row read back
        // from the database already carries all its columns, so its canonical form
        // is unaffected and rows that verify today still verify.
        //
        // RESIDUAL RISK, stated because it will bite someone: ADDING A COLUMN to a
        // hash-chained table still changes the canonical set and breaks every row
        // written before the migration. There is no way around that without
        // recording the field list per row. A migration on one of these four tables
        // therefore needs a deliberate chain re-anchor (config('audit.anchors')),
        // not a --backfill.
        foreach (static::chainColumns() as $column) {
            if (! array_key_exists($column, $attributes)) {
                $attributes[$column] = null;
            }
        }

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
     * Every column on this model's table, cached per process. Used to give the
     * canonical form the same shape on write as it has on read.
     */
    protected static function chainColumns(): array
    {
        static $cache = [];

        $model = new static;
        $key   = $model->getConnectionName() . '|' . $model->getTable();

        if (! isset($cache[$key])) {
            $cache[$key] = $model->getConnection()
                ->getSchemaBuilder()
                ->getColumnListing($model->getTable());
        }

        return $cache[$key];
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
    /**
     * The id this table's chain is authoritative FROM. Rows below it are not
     * checked and are reported separately as unverifiable — never as intact.
     *
     * An anchor is the honest answer to a diagnosed code defect that made older
     * hashes structurally unverifiable. It does NOT claim those rows are good;
     * it states, every single morning, exactly how many rows cannot be vouched
     * for. --backfill is the dishonest answer to the same problem: it rewrites
     * history's hashes to match whatever history currently says, which is what
     * an attacker would do, and afterwards nobody can tell the difference.
     */
    public static function chainAnchorId(): int
    {
        $anchors = (array) config('audit.anchors', []);

        return (int) ($anchors[(new static)->getTable()] ?? 0);
    }

    public static function verifyChain(): array
    {
        $prev       = null;
        $checked    = 0;
        $legacyRows = 0;
        $anchor     = static::chainAnchorId();
        $preAnchor  = 0;

        foreach (static::query()->orderBy('id')->cursor() as $row) {
            // Rows before the anchor are outside the chain. Count them, say so,
            // and start the chain fresh from the anchor row's own prev_hash.
            if ($anchor > 0 && $row->id < $anchor) {
                $preAnchor++;
                continue;
            }

            if ($anchor > 0 && $row->id === $anchor && $checked === 0) {
                $prev = $row->prev_hash;
            }

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
                    'pre_anchor'   => $preAnchor,
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
            'pre_anchor'   => $preAnchor,
        ];
    }
}
