<?php

namespace App\Support\Features;

use App\Models\FeatureFlag;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * FeatureFlagService — Phase 0 (Safety & Foundations).
 *
 * A deliberately small, dependency-free feature-flag resolver.
 *
 * Resolution order for a flag:
 *   1. Per-clinic override   (feature_flags row where key = ? AND branch_id = ?)
 *   2. Global override       (feature_flags row where key = ? AND branch_id IS NULL)
 *   3. Config default        (config('features.flags.KEY.default'), may read an env var)
 *   4. false
 *
 * Design principles (Blueprint §11):
 *   - No hard-coded flags: every key must be declared in config/features.php.
 *   - Never throws: an unknown/misconfigured flag resolves to false and warns once.
 *   - Cheap: DB overrides are cached (config('features.cache_ttl')).
 */
class FeatureFlagService
{
    /**
     * Is the given flag enabled for an optional clinic (branch) scope?
     */
    public function enabled(string $key, ?int $branchId = null): bool
    {
        // 1 + 2: DB overrides (per-clinic wins over global).
        $overrides = $this->overrides();

        if ($branchId !== null && array_key_exists("{$key}|{$branchId}", $overrides)) {
            return (bool) $overrides["{$key}|{$branchId}"];
        }

        if (array_key_exists("{$key}|", $overrides)) { // global (branch_id null)
            return (bool) $overrides["{$key}|"];
        }

        // 3: config default (declared flags only).
        $flags = (array) config('features.flags', []);
        if (array_key_exists($key, $flags)) {
            return (bool) ($flags[$key]['default'] ?? false);
        }

        // 4: unknown flag — fail safe (off) and warn so it gets declared.
        Log::warning('FeatureFlag requested but not declared in config/features.php', ['key' => $key]);
        return false;
    }

    /**
     * Fluent per-clinic scope: Feature::for($branchId)->enabled('flag').
     */
    public function for(?int $branchId): FeatureScope
    {
        return new FeatureScope($this, $branchId);
    }

    /**
     * Set (or clear) an override. Passing $enabled = null removes the override,
     * falling back to the config default. Additive, safe, cache-busting.
     */
    public function set(string $key, ?bool $enabled, ?int $branchId = null, ?string $note = null): void
    {
        if ($enabled === null) {
            FeatureFlag::query()
                ->where('key', $key)
                ->where('branch_id', $branchId)
                ->delete();
        } else {
            FeatureFlag::query()->updateOrCreate(
                ['key' => $key, 'branch_id' => $branchId],
                ['enabled' => $enabled, 'note' => $note],
            );
        }

        $this->flushCache();
    }

    /**
     * All declared flags with their currently-resolved global value.
     * Used by the monitoring/status surface — read-only.
     *
     * @return array<string, array{default: bool, resolved: bool, description: string}>
     */
    public function all(): array
    {
        $out = [];
        foreach ((array) config('features.flags', []) as $key => $meta) {
            $out[$key] = [
                'default'     => (bool) ($meta['default'] ?? false),
                'resolved'    => $this->enabled($key),
                'description' => (string) ($meta['description'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * Load the override map keyed by "key|branchId" ("" for global).
     *
     * @return array<string, bool>
     */
    protected function overrides(): array
    {
        $ttl = (int) config('features.cache_ttl', 60);

        $loader = function (): array {
            try {
                $map = [];
                foreach (FeatureFlag::query()->get(['key', 'branch_id', 'enabled']) as $row) {
                    $map["{$row->key}|{$row->branch_id}"] = (bool) $row->enabled;
                }
                return $map;
            } catch (\Throwable $e) {
                // e.g. the feature_flags table has not been migrated yet. Behave
                // as if there are no overrides → flags fall back to config
                // defaults (legacy behaviour). Flag reads must never break the app.
                return [];
            }
        };

        if ($ttl <= 0) {
            return $loader();
        }

        return Cache::remember('feature_flags.overrides', $ttl, $loader);
    }

    public function flushCache(): void
    {
        Cache::forget('feature_flags.overrides');
    }
}
