<?php

namespace App\Http\Controllers\Marketing\Concerns;

/**
 * Resolves the current user's clinic instead of a hardcoded id.
 *
 * Every Marketing controller used to declare `private const CLINIC_ID = 1;`,
 * which meant Analytics/Overview/etc. always reported clinic 1's data
 * regardless of who was logged in — fine for a single-clinic install, wrong
 * for anything multi-clinic. `IntegrationController` already used the
 * `auth()->user()->clinic_id ?? 1` pattern; this trait just shares that one
 * pattern across every Marketing controller instead of repeating it (or a
 * hardcoded constant) in each one.
 *
 * The `?? 1` fallback is kept intentionally: if a user record has no
 * clinic_id set (e.g. a single-clinic install that never populated the
 * column), behavior degrades to the old hardcoded-1 behavior rather than
 * throwing.
 */
trait ResolvesClinicId
{
    protected function currentClinicId(): int
    {
        return auth()->user()->clinic_id ?? 1;
    }
}
