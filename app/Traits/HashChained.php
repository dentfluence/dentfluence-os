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

        // Normalise values to strings/scalars so encoding is stable.
        foreach ($canonical as $k => $v) {
            if (is_array($v)) {
                $canonical[$k] = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } elseif ($v instanceof \DateTimeInterface) {
                $canonical[$k] = $v->format('Y-m-d H:i:s');
            }
        }

        ksort($canonical);
        return $canonical;
    }

    /** sha256 of "prev_hash | canonical-json". */
    public static function chainComputeHash(?string $prevHash, array $canonical): string
    {
        $json = json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return hash('sha256', ((string) $prevHash) . '|' . $json);
    }

    /**
     * Recompute the whole chain in id order and confirm nothing was tampered.
     * Returns ['ok' => bool, 'checked' => int, 'first_bad_id' => ?int].
     */
    public static function verifyChain(): array
    {
        $prev = null;
        $checked = 0;

        foreach (static::query()->orderBy('id')->cursor() as $row) {
            $expected = static::chainComputeHash($prev, static::chainCanonical($row->getAttributes()));

            if (! hash_equals($expected, (string) $row->hash) || (string) $row->prev_hash !== (string) $prev) {
                return ['ok' => false, 'checked' => $checked, 'first_bad_id' => $row->id];
            }

            $prev = $row->hash;
            $checked++;
        }

        return ['ok' => true, 'checked' => $checked, 'first_bad_id' => null];
    }
}
