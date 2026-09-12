<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Crypt;

/**
 * PHI decryption helper for RAW (query-builder) reads.
 *
 * Eloquent casts (App\Casts\Encrypted / App\Casts\EncryptedArray) decrypt PHI
 * columns transparently — but only when the row is hydrated through a model.
 * A DB::table() / join read bypasses the model entirely, so an encrypted
 * column comes back as raw ciphertext ("eyJpdiI6Ii...") and gets rendered
 * straight into the UI or the API payload.
 *
 * Any raw read of an encrypted column MUST pass the value through here.
 *
 * Resilient by design, mirroring App\Casts\Encrypted: a value that cannot be
 * decrypted (legacy plaintext written before the backfill ran) is returned
 * untouched rather than throwing, so mixed-state tables keep rendering.
 */
final class Phi
{
    /**
     * Decrypt a single PHI string read from a raw query.
     */
    public static function decrypt(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return $value;
        }
    }

    /**
     * Decrypt the named properties on a raw row (stdClass or array), in place.
     *
     * @param  array<int, string>  $fields
     */
    public static function decryptRow(mixed $row, array $fields): mixed
    {
        foreach ($fields as $field) {
            if (is_array($row)) {
                if (array_key_exists($field, $row)) {
                    $row[$field] = self::decrypt($row[$field]);
                }
            } elseif (is_object($row) && property_exists($row, $field)) {
                $row->{$field} = self::decrypt($row->{$field});
            }
        }

        return $row;
    }
}
