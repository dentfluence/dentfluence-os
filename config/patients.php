<?php

/**
 * Patients module — small, module-scoped settings that don't warrant a
 * database-backed feature flag.
 */
return [

    /**
     * Duplicate Merge — safety-net undo window (Final Design §1).
     *
     * How many minutes after a merge an admin can undo it, PROVIDED the
     * surviving patient has had zero activity since (see
     * PatientMergeService::hasActivitySince()). This is deliberately not a
     * general-purpose rollback: past this window, or the moment any activity
     * is recorded against the surviving patient, undo is refused outright —
     * never partial, never best-effort.
     */
    'merge_undo_window_minutes' => env('PATIENT_MERGE_UNDO_WINDOW_MINUTES', 15),

];
