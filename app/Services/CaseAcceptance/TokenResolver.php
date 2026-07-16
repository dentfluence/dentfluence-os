<?php

namespace App\Services\CaseAcceptance;

/**
 * TokenResolver — injects whitelisted {{tokens}} into authored KB text at
 * render time (frozen §6/§7). ONLY the whitelist is resolved; unknown tokens
 * are left untouched (never executed — there is no logic in content). Resolution
 * happens AFTER translation / AI-rewrite so localized text keeps its variables.
 */
class TokenResolver
{
    /** The fixed whitelist. Anything not here is left as-is. */
    public const WHITELIST = ['tooth_name', 'patient_first_name', 'tooth_count'];

    /**
     * @param  array<string,string|int|null>  $context  keyed by token name
     */
    public function resolve(?string $body, array $context): ?string
    {
        if ($body === null || $body === '') {
            return $body;
        }

        return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/i', function ($m) use ($context) {
            $token = strtolower($m[1]);

            if (! in_array($token, self::WHITELIST, true)) {
                return $m[0];   // not whitelisted — leave the raw {{token}} intact
            }

            $value = $context[$token] ?? null;

            return $value === null || $value === '' ? $m[0] : (string) $value;
        }, $body);
    }
}
