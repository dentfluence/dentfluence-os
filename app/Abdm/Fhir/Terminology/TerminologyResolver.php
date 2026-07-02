<?php

namespace App\Abdm\Fhir\Terminology;

use App\Models\TerminologyMap;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves a local term/code to a standard FHIR coding using the terminology_maps
 * table. Results are cached (the table changes rarely). If no mapping is found it
 * returns null so callers can decide how to degrade gracefully — we never invent a
 * code. This is the data-driven heart of FHIR conformance.
 */
class TerminologyResolver
{
    /**
     * @return array|null  ['system' => ..., 'code' => ..., 'display' => ...] or null
     */
    public function resolve(string $domain, ?string $localCodeOrTerm): ?array
    {
        if ($localCodeOrTerm === null || $localCodeOrTerm === '') {
            return null;
        }

        $key = "termmap:{$domain}:" . md5($localCodeOrTerm);

        return Cache::remember($key, now()->addHours(6), function () use ($domain, $localCodeOrTerm) {
            $row = TerminologyMap::active()->domain($domain)
                ->where(function ($q) use ($localCodeOrTerm) {
                    $q->where('local_code', $localCodeOrTerm)
                      ->orWhere('local_term', $localCodeOrTerm);
                })
                ->first();

            if (! $row) {
                return null;
            }

            return [
                'system'  => $row->standard_system,
                'code'    => $row->standard_code,
                'display' => $row->standard_display,
            ];
        });
    }

    /** Convenience: return a FHIR CodeableConcept array, or null. */
    public function codeableConcept(string $domain, ?string $localCodeOrTerm, ?string $fallbackText = null): ?array
    {
        $coding = $this->resolve($domain, $localCodeOrTerm);

        if (! $coding) {
            // No standard code — still emit the human text so nothing is lost.
            return $fallbackText ? ['text' => $fallbackText] : null;
        }

        return [
            'coding' => [array_filter($coding, fn ($v) => $v !== null)],
            'text'   => $fallbackText ?? $coding['display'] ?? null,
        ];
    }
}
