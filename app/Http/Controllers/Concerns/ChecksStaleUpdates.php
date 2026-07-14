<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * ChecksStaleUpdates — optimistic locking (production hardening 2026-07-14).
 *
 * Edits were silent last-write-wins: two staff opening the same patient (or
 * invoice, or appointment) would overwrite each other with no warning and no
 * indication anything was lost. Classic scenario — reception fixes a phone
 * number while the manager, working from a copy opened ten minutes earlier,
 * saves an address change and silently reverts the phone.
 *
 * This is deliberately the lightest possible guard: the form ships the
 * `updated_at` it rendered with, and the update is refused if the row has moved
 * on since. No new columns, no schema change, no version counters.
 *
 * BACKWARD COMPATIBLE: when the request carries no `updated_at` (an older form,
 * or a client that hasn't been updated yet), the check is skipped and behaviour
 * is exactly as before. Adding the hidden field to a form opts that form in.
 *
 * Usage in a controller:
 *
 *     $this->assertNotStale($request, $patient);
 *     $patient->update($data);
 *
 * And in the form:
 *
 *     <input type="hidden" name="updated_at" value="{{ $patient->updated_at?->toIso8601String() }}">
 */
trait ChecksStaleUpdates
{
    /**
     * Refuse the write if the record changed after the client loaded it.
     *
     * @throws ValidationException  422 — the caller's copy is out of date.
     */
    protected function assertNotStale(Request $request, Model $model, string $field = 'updated_at'): void
    {
        $clientStamp = $request->input($field);

        // No stamp supplied → this form/client hasn't opted in. Don't break it.
        if (blank($clientStamp) || blank($model->updated_at)) {
            return;
        }

        try {
            $seen = \Illuminate\Support\Carbon::parse($clientStamp);
        } catch (\Throwable) {
            return; // Unparseable — treat as "not supplied" rather than blocking a save.
        }

        // Compare at second precision: the client round-trips an ISO string and
        // MySQL DATETIME has no sub-second part, so exact equality is unsafe.
        if ($seen->startOfSecond()->equalTo($model->updated_at->copy()->startOfSecond())) {
            return;
        }

        $who  = $model->updated_at->diffForHumans();
        $name = class_basename($model);

        throw ValidationException::withMessages([
            $field => "This {$name} was changed by someone else {$who}. "
                . 'Reload the page to see their changes before saving — otherwise you would overwrite them.',
        ]);
    }
}
