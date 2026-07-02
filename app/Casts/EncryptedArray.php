<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * EncryptedArray cast (Phase A — PHI encryption at rest).
 *
 * Array/JSON equivalent of {@see Encrypted}. Stores the array as encrypted
 * JSON text, and is RESILIENT during migration:
 *  - On WRITE: json_encode -> encrypt.
 *  - On READ : try decrypt -> json_decode. If the value isn't encrypted yet
 *              (legacy JSON column content), fall back to json_decode of the
 *              raw value. Always returns an array (or null).
 *
 * The underlying column MUST be text/longText (NOT json), because encrypted
 * ciphertext is not valid JSON — see the column-widening migration.
 */
class EncryptedArray implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null || $value === '') {
            return $value === '' ? [] : null;
        }

        // Encrypted path.
        try {
            $plain = Crypt::decryptString($value);
            $decoded = json_decode($plain, true);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            // Legacy plaintext JSON (pre-backfill).
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return [$key => null];
        }

        // Accept an already-encoded string or a real array.
        $json = is_string($value) ? $value : json_encode(array_values((array) $value));

        return [$key => Crypt::encryptString($json)];
    }
}
