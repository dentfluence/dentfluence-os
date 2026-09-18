<?php

/**
 * Server-side PDF rendering (V.27).
 *
 * One document, one renderer: the browser prints a Blade template, and the
 * same template is rendered to PDF here so the phone prints the identical
 * page. Chromium (Gotenberg) is the engine because the print Blades are
 * flexbox-heavy — dompdf and mpdf cannot lay them out at all, and Chromium is
 * what Ctrl+P already uses, so the PDF matches what the clinic knows.
 */
return [
    // The gotenberg container on the dentfluence network. Never host-exposed.
    'gotenberg_url' => env('PDF_GOTENBERG_URL', 'http://gotenberg:3000'),

    // Seconds to wait for a rendered document before giving up. A print page
    // is small; anything slower than this means gotenberg is unwell.
    'timeout' => (int) env('PDF_TIMEOUT', 30),

    // Paper. The print Blades declare @page A4 themselves and preferCssPageSize
    // honours that; these are the fallback for a template that does not.
    'paper' => [
        'width'  => 8.27,   // inches — A4
        'height' => 11.69,
    ],
];
