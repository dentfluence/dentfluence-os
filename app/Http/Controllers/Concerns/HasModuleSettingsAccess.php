<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Auth\Access\AuthorizationException;

/**
 * HasModuleSettingsAccess — Settings Architecture v2, Phase 1.
 *
 * Every module-owned settings page (Appointment Settings, Billing Settings,
 * Consultation Settings, etc.) must be gated by the module's own
 * `<slug>.settings` permission tier, not by the module's `view`/`edit` tier
 * used for its operational data. This is the single shared helper every
 * module-settings controller calls, so the check is applied identically
 * everywhere instead of being re-implemented per module.
 *
 * Reference implementation: this trait is infrastructure only in Phase 1.
 * It is wired into a module's settings controller as that module's settings
 * are actually relocated (Phase 2 onward, per the frozen Settings
 * Architecture v2 roadmap) — using it ahead of a module's real move would be
 * moving ownership early, which the frozen spec explicitly forbids.
 *
 * Usage in a controller:
 *
 *     $this->authorizeModuleSettings('billing');
 *
 * Throws a 403 (via Laravel's standard authorization exception → the app's
 * existing "access denied" handling) if the current user's role does not
 * carry the `<slug>.settings` permission. Admin/Owner role always passes,
 * consistent with how User::canAccess() already treats the admin role
 * everywhere else in the app.
 */
trait HasModuleSettingsAccess
{
    /**
     * @throws AuthorizationException
     */
    protected function authorizeModuleSettings(string $moduleSlug): void
    {
        $user = auth()->user();

        if (! $user || ! $user->canAccess($moduleSlug, 'settings')) {
            throw new AuthorizationException(
                'You do not have permission to configure this module\'s settings.'
            );
        }
    }

    /**
     * Non-throwing variant for use in Blade (`@if($this->canConfigureModule('billing'))`)
     * or for conditionally showing/hiding the settings trigger in a header.
     */
    protected function canConfigureModule(string $moduleSlug): bool
    {
        $user = auth()->user();

        return $user && $user->canAccess($moduleSlug, 'settings');
    }
}
