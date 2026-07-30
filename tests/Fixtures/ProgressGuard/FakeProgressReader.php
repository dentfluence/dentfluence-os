<?php

namespace Tests\Fixtures\ProgressGuard;

/**
 * FIXTURE ONLY — never autoloaded into the application.
 *
 * A deliberate violation of the Clinical Progress invariant: a component
 * outside DerivedProgressService reading the captured clinical fact and
 * deriving its own answer. Exists so the guard can be proven to BITE, not
 * merely to pass. A guard that can only ever succeed proves nothing.
 *
 * It also carries the fact inside a docblock (work_outcome, right here) so the
 * same test proves documentation is NOT treated as a violation.
 */
class FakeProgressReader
{
    public function isDone(object $visitItem): bool
    {
        return $visitItem->work_outcome === 'completed_today';
    }
}
