<?php

/**
 * FDI two-digit tooth notation — permanent (adult) vs primary (child) dentition.
 *
 * Only the anterior + premolar zone (positions 1-5 per quadrant) has a primary
 * predecessor: central incisor, lateral incisor, canine, 1st molar, 2nd molar.
 * The permanent 1st/2nd/3rd molars (positions 6-8, e.g. 16/17/18) erupt behind
 * the primary row rather than replacing a primary tooth, so they are always
 * "permanent-only" — no toggle applies to them.
 *
 * This is the single source of truth for tooth-notation mapping across the
 * app. PHP views read it via config('dental_notation'); Blade exposes the
 * same array to JS via @json() so client-side tooth-chart toggles never
 * hand-duplicate the mapping.
 */
return [

    // permanent code => primary code, for the 20 positions that have both.
    'permanent_to_primary' => [
        11 => 51, 12 => 52, 13 => 53, 14 => 54, 15 => 55,
        21 => 61, 22 => 62, 23 => 63, 24 => 64, 25 => 65,
        31 => 71, 32 => 72, 33 => 73, 34 => 74, 35 => 75,
        41 => 81, 42 => 82, 43 => 83, 44 => 84, 45 => 85,
    ],

    // primary code => permanent code (inverse of the above, kept explicit
    // rather than computed so PHP and JS both read a flat, ready-to-use map).
    'primary_to_permanent' => [
        51 => 11, 52 => 12, 53 => 13, 54 => 14, 55 => 15,
        61 => 21, 62 => 22, 63 => 23, 64 => 24, 65 => 25,
        71 => 31, 72 => 32, 73 => 33, 74 => 34, 75 => 35,
        81 => 41, 82 => 42, 83 => 43, 84 => 44, 85 => 45,
    ],

    // Permanent molars with no primary predecessor — never toggle these.
    'molar_only' => [16, 17, 18, 26, 27, 28, 36, 37, 38, 46, 47, 48],

    // Full permanent (adult) arch, quadrant order matches existing charts.
    'permanent_arches' => [
        'upper_right' => [18, 17, 16, 15, 14, 13, 12, 11],
        'upper_left'  => [21, 22, 23, 24, 25, 26, 27, 28],
        'lower_right' => [48, 47, 46, 45, 44, 43, 42, 41],
        'lower_left'  => [31, 32, 33, 34, 35, 36, 37, 38],
    ],

    // Full primary (child) arch — only 5 teeth per quadrant.
    'primary_arches' => [
        'upper_right' => [55, 54, 53, 52, 51],
        'upper_left'  => [61, 62, 63, 64, 65],
        'lower_right' => [85, 84, 83, 82, 81],
        'lower_left'  => [71, 72, 73, 74, 75],
    ],
];
