<?php

namespace App\Contracts\Marketing\Providers;

/**
 * Where Marketing's Dashboard/Reviews screen gets review data from.
 *
 * Integrated: wraps the existing Communication Reviews system
 * (App\Services\Reviews\ReviewService) — collect, stats, and reply are
 * already fully built there (see docs/marketing-module-technical-dossier.md
 * §2 verification). Standalone: a clinic with no Dentfluence OS connection
 * has no Patient/Appointment records to request reviews *from*, so this
 * degrades to manual logging only — "I got a review elsewhere, record it."
 *
 * See docs/marketing-module-reengineering-plan.md §9.
 */
interface ReviewProvider
{
    /** Reviews needing a reply right now (for the Dashboard task list). */
    public function pendingCount(int $clinicId): int;

    /** requested / rated / avg / positive / negative counts. */
    public function stats(int $clinicId): array;

    public function isManual(): bool;
}
