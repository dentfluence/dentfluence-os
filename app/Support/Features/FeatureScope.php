<?php

namespace App\Support\Features;

/**
 * A small immutable per-clinic scope so callers can write:
 *
 *   Feature::for($branch->id)->enabled('guard.fail_closed')
 *
 * It holds no logic of its own — it simply forwards to FeatureFlagService
 * with the branch scope applied.
 */
final class FeatureScope
{
    public function __construct(
        private readonly FeatureFlagService $flags,
        private readonly ?int $branchId,
    ) {
    }

    public function enabled(string $key): bool
    {
        return $this->flags->enabled($key, $this->branchId);
    }
}
