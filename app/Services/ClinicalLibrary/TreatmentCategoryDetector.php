<?php

namespace App\Services\ClinicalLibrary;

/**
 * TreatmentCategoryDetector
 *
 * Rule-based (no AI, no ML) keyword match that guesses a fixed
 * ClinicalFile::TREATMENT_CATEGORIES value from free-text procedure/diagnosis
 * strings. Used at upload time by ClinicalFileUploadService, and by the
 * clinical-library:backfill-treatment-category console command for existing
 * records.
 *
 * A guess is always correctable later from the Content Manager — this class
 * only proposes a default. It is deliberately conservative: returns null
 * (uncategorized) rather than guessing wrong when nothing matches, since a
 * blank filter value is more honest than a mislabeled one.
 */
class TreatmentCategoryDetector
{
    /**
     * category => keywords checked as case-insensitive substrings, in this
     * order. First match wins.
     */
    private const KEYWORD_MAP = [
        'implant'        => ['implant'],
        'aligner'        => ['aligner', 'invisalign'],
        'whitening'      => ['whiten', 'bleach'],
        'rct'            => ['root canal', 'rct', 'endodontic'],
        'veneer'         => ['veneer', 'laminate'],
        'crown'          => ['crown', 'bridge'],
        'smile_makeover' => ['smile makeover', 'smile design'],
        'braces'         => ['brace', 'orthodontic'],
        'extraction'     => ['extraction', 'wisdom tooth'],
    ];

    public static function detect(?string $procedureText): ?string
    {
        if (blank($procedureText)) {
            return null;
        }

        $haystack = strtolower($procedureText);

        foreach (self::KEYWORD_MAP as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($haystack, $keyword)) {
                    return $category;
                }
            }
        }

        return null;
    }
}
