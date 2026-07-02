<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

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
            // Legacy plaintext (pre-backfill) — hand it back untouched.
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
