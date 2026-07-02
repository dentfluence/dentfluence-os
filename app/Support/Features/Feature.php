<?php

namespace App\Support\Features;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for the FeatureFlagService.
 *
 * Usage:
 *   use App\Support\Features\Feature;
 *
 *   Feature::enabled('guard.fail_closed');
 *   Feature::for($branch->id)->enabled('today.projection');
 *
 * @method static bool enabled(string $key, ?int $branchId = null)
 * @method static \App\Support\Features\FeatureScope for(?int $branchId)
 * @method static void set(string $key, ?bool $enabled, ?int $branchId = null, ?string $note = null)
 * @method static array all()
 * @method static void flushCache()
 *
 * @see \App\Support\Features\FeatureFlagService
 */
class Feature extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FeatureFlagService::class;
    }
}
