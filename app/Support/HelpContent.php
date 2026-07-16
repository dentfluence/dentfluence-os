<?php

namespace App\Support;

/**
 * Resolves contextual screen-guide content for the current route.
 *
 * All guide text lives in ONE file — resources/help/content.php — keyed by
 * full route name (e.g. 'patients.create') or route-name prefix
 * (e.g. 'patients'). Exact route match wins over prefix match.
 *
 * Updating guidance never touches a Blade view: edit content.php only.
 */
class HelpContent
{
    protected static ?array $registry = null;

    public static function all(): array
    {
        if (static::$registry === null) {
            $path = resource_path('help/content.php');
            static::$registry = is_file($path) ? require $path : [];
        }

        return static::$registry;
    }

    /**
     * All screen entries (excludes '_'-prefixed meta keys like _workflows),
     * role-resolved, keyed by their registry key. Used by the Help Centre.
     */
    public static function screens(bool $isAdmin): array
    {
        $screens = [];

        foreach (static::all() as $key => $entry) {
            if (str_starts_with($key, '_')) {
                continue;
            }
            $screens[$key] = static::resolve($key, $entry, $isAdmin);
        }

        return $screens;
    }

    /** Cross-module workflow stories shown on the Help Centre page. */
    public static function workflows(): array
    {
        return static::all()['_workflows'] ?? [];
    }

    /**
     * Entry for a route with the role variant already resolved,
     * or null when no guide content exists for the page.
     */
    public static function forRoute(?string $routeName, bool $isAdmin): ?array
    {
        if (! $routeName || str_starts_with($routeName, '_')) {
            return null;
        }

        $registry = static::all();
        $key      = $routeName;
        $entry    = $registry[$key] ?? null;

        if (! $entry && str_contains($routeName, '.')) {
            $key   = strstr($routeName, '.', true);
            $entry = $registry[$key] ?? null;
        }

        if (! $entry) {
            return null;
        }

        return static::resolve($key, $entry, $isAdmin);
    }

    /** Resolves the staff/admin variant of a raw registry entry. */
    protected static function resolve(string $key, array $entry, bool $isAdmin): array
    {
        $variant = $isAdmin ? 'admin' : 'staff';

        return [
            'key'   => $key,
            'title' => $entry['title'] ?? ucfirst($key),
            'hint'  => $entry['hint'][$variant] ?? $entry['hint']['staff'] ?? null,
            'what'  => $entry['what'] ?? null,
            'tasks' => $entry['tasks'] ?? [],
            'flows' => $entry['flows'] ?? [],
            'roi'   => $isAdmin ? ($entry['roi'] ?? null) : null,
        ];
    }
}
