<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Encrypted cast (Phase A — PHI encryption at rest).
 *
 * Like Laravel's built-in "encrypted" cast, but RESILIENT during migration:
 *  - On WRITE: always encrypts the value.
 *  - On READ : tries to decrypt. If the stored value isn't encrypted yet
 *              (a legacy plaintext row not yet processed by the backfill
 *              command), it returns the raw value instead of throwing.
 *
 * This lets us flip the cast on BEFORE the backfill has run without crashing
 * the app on old rows. Once `php artisan patients:encrypt-phi` has run, every
 * row is real ciphertext and the fallback never triggers.
 *
 * 2026-07-14 (production hardening): the fallback now LOGS. It previously
 * returned plaintext completely silently, which meant a row that never got
 * encrypted — or an APP_KEY problem that broke decryption for every row —
 * looked exactly like normal operation. An encryption gap you cannot see is
 * worse than one you can. The read still succeeds (so the app never breaks on
 * a legacy row); it just no longer does so invisibly.
 */
class Encrypted implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            // Legacy plaintext (pre-backfill) — hand it back untouched, but
            // make the gap visible. Never log the value itself.
            Log::warning('Encrypted cast: value is not decryptable — returning as-is (plaintext at rest?)', [
                'model'  => $model::class,
                'key'    => $key,
                'row_id' => $model->getKey(),
            ]);

            return $value;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return [$key => null];
        }

        return [$key => Crypt::encryptString((string) $value)];
    }
}
