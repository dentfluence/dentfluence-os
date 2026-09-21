<?php

namespace Tests\Feature\Access;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Signal Board row 2.5 — every state-changing route must be gated by a
 * permission that means WRITE.
 *
 * ApiAccessParityCharacterizationTest already catches a route gated by a job
 * TITLE instead of a permission. This is the other half: a route gated by
 * nothing at all, or gated only by the module's VIEW grant while it writes.
 *
 * A gate counts as a write gate when it is one of:
 *   module:<m>,edit | module:<m>,delete
 *   api.role:module:<m>,edit | api.role:module:<m>,delete
 *   admin.only (EnsureAdminRole) | superadmin (EnsureIsSuperadmin)
 *
 * Everything else — no middleware, auth only, module:<m> (view), or
 * communication.access — is an offender.
 *
 * Measured on main 4d308ce, 20 Sep 2026, from `php artisan route:list --json`
 * (_release/ROUTES_BEFORE.json): 1,204 routes, 712 write routes, 587 correctly
 * gated, 125 offenders. Those 125 are split below into three lists. The point
 * of this test is that the first two never grow and the third only shrinks.
 */
class WriteRouteGateGuardTest extends TestCase
{
    /** Verbs that change state. */
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * Public by design — nobody is logged in yet, so there is no permission to
     * check. Each one must protect itself another way (throttle, signed token,
     * webhook signature, OTP). Adding to this list is a security decision.
     */
    private const PUBLIC_BY_DESIGN = [
        'POST api/v1/auth/login',
        'POST api/v1/webhooks/prm/whatsapp',
        // 1.5: the host's alert script has no user session. Gated by a shared
        // secret compared with hash_equals in OpsAlertController, and the route
        // is CLOSED when that secret is unset.
        'POST api/v1/ops/alert',
        'POST auth/mobile/send-otp',
        'POST auth/mobile/verify',
        'POST forgot-pin/reset',
        'POST forgot-pin/send',
        'POST forgot-pin/verify',
        'POST login',
        'POST p/{token}/accept',
        'POST p/{token}/decline',
        'POST p/{token}/request-callback',
        'POST p/{token}/select',
        'POST present/{token}/accept',
        'POST present/{token}/decline',
        'POST present/{token}/request-callback',
        'POST r/{token}',
        'POST two-factor/challenge',
        'PUT storage/{path}',
    ];

    /**
     * Authenticated self-service: the caller may only act on their own
     * account, session, device or attendance record. There is no module grant
     * to check because there is no other person's data in reach.
     */
    private const SELF_SERVICE = [
        'DELETE api/v1/devices/token',
        'DELETE profile/avatar',
        'PATCH api/v1/notifications/{id}/read',
        'POST api/v1/auth/logout',
        'POST api/v1/auth/logout-all',
        'POST api/v1/devices/token',
        'POST api/v1/hr/attendance/check-in',
        'POST api/v1/hr/attendance/check-out',
        'POST api/v1/notifications/mark-all-read',
        'POST api/v1/notifications/{id}/acknowledge',
        'POST api/v1/notifications/{id}/later',
        'POST hr/scan',
        'POST logout',
        'POST notifications/mark-all-read',
        'POST notifications/{id}/acknowledge',
        'POST notifications/{id}/later',
        'POST notifications/{id}/read',
        'POST profile',
        'POST profile/avatar',
        'POST profile/password',
        'POST two-factor/disable',
        'POST two-factor/enable',
        'PUT api/v1/auth/me',
    ];

    /**
     * KNOWN OFFENDERS — real gaps, quarantined so the guard can go in today.
     * This list is a debt register, not an exemption: it may only shrink.
     * Fix the route, then delete its line here; the stale-entry test below
     * fails if you fix one and leave the line, so the list cannot rot.
     *
     * The bulk of it is the marketing module (46), which has never
     * distinguished view from edit, and 13 finance routes that void, cancel,
     * refund, adjust or reverse money behind nothing more than finance VIEW.
     */
    private const KNOWN_UNGATED_WRITES = [
        'DELETE billing/final-bill/{finalBill}',
        'DELETE communication/templates/{id}',
        'DELETE finance/vouchers/{voucher}',
        'DELETE lab-vendors/{labVendor}/contacts/{contact}',
        'DELETE lab-vendors/{labVendor}/services/{service}',
        'DELETE lab/{labCase}',
        'DELETE marketing/assets/{asset}',
        'DELETE marketing/assets/{asset}/tags',
        'DELETE marketing/blog/{blog}',
        'DELETE marketing/blog/{blog}/publications/{publication}',
        'DELETE marketing/blog/{blog}/versions/{version}',
        'DELETE marketing/campaigns/{campaign}',
        'DELETE marketing/campaigns/{campaign}/team',
        'DELETE marketing/ideas/{idea}',
        'DELETE marketing/library/folders/{folder}',
        'DELETE settings/tags/{tag}',
        'POST api/v1/billing-prompts/{prompt}/dismiss',
        'POST api/v1/rx/check-alerts',
        'POST api/v1/rx/check-repeat',
        'POST assistant/chat',
        'POST assistant/confirm/{action}',
        'POST assistant/reject/{action}',
        'POST assistant/transcribe',
        'POST billing/{invoice}/cancel',
        'POST billing/{invoice}/cancel-with-reason',
        'POST billing/{invoice}/delete-auth',
        'POST billing/{invoice}/edit-auth',
        'POST billing/{invoice}/receipt/{receipt}/void',
        'POST communication/recall-settings/anniversary',
        'POST communication/recall-settings/birthday',
        'POST communication/recall-settings/general',
        'POST communication/recall-settings/treatment/{treatmentType}',
        'POST communication/templates',
        'POST finance/wallets/{patient}/adjust',
        'POST finance/wallets/{patient}/receive-advance',
        'POST finance/wallets/{patient}/refund',
        'POST finance/wallets/{patient}/transactions/{transaction}/reverse',
        'POST finance/wallets/{patient}/transactions/{transaction}/wrong-entry',
        'POST marketing/assets/upload',
        'POST marketing/assets/{asset}/tags',
        'POST marketing/blog',
        'POST marketing/blog-taxonomy/categories',
        'POST marketing/blog-taxonomy/tags',
        'POST marketing/blog/{blog}/archive',
        'POST marketing/blog/{blog}/autosave',
        'POST marketing/blog/{blog}/draft',
        'POST marketing/blog/{blog}/duplicate',
        'POST marketing/blog/{blog}/publications/{publication}/retry',
        'POST marketing/blog/{blog}/publish',
        'POST marketing/blog/{blog}/unarchive',
        'POST marketing/blog/{blog}/versions/{version}/restore',
        'POST marketing/brand-kit/logo',
        'POST marketing/campaigns',
        'POST marketing/campaigns/{campaign}/team',
        'POST marketing/ideas',
        'POST marketing/ideas/from-review/{review}',
        'POST marketing/ideas/{idea}/convert-campaign',
        'POST marketing/ideas/{idea}/convert-post',
        'POST marketing/integrations/whatsapp/save',
        'POST marketing/integrations/wordpress/save',
        'POST marketing/integrations/{platform}/disconnect',
        'POST marketing/integrations/{platform}/health-check',
        'POST marketing/library/folders',
        'POST marketing/publish',
        'POST marketing/publish/draft',
        'POST marketing/settings/integrated-mode',
        'POST patients/{patient}/receipt/{receipt}/void',
        'POST tasks/{task}/done',
        'POST tasks/{task}/evidence',
        'PUT communication/templates/{id}',
        'PUT lab-vendors/{labVendor}/contacts/{contact}',
        'PUT lab-vendors/{labVendor}/services/{service}',
        'PUT marketing/assets/{asset}',
        'PUT marketing/blog/{blog}',
        'PUT marketing/brand-kit',
        'PUT marketing/calendar/{post}/reschedule',
        'PUT marketing/campaigns/{campaign}',
        'PUT marketing/campaigns/{campaign}/goals',
        'PUT marketing/ideas/{idea}',
        'PUT marketing/library/folders/{folder}',
        'PUT marketing/publish/{post}/variants/{platform}',
        'PUT settings/tags/{tag}',
    ];

    /**
     * @return array<string> "METHOD uri" for every write route with no write gate
     */
    private function ungatedWriteRoutes(): array
    {
        $offenders = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $middleware = array_values(array_filter($route->gatherMiddleware(), 'is_string'));

            if ($this->hasWriteGate($middleware)) {
                continue;
            }

            foreach ($route->methods() as $method) {
                if (in_array($method, self::WRITE_METHODS, true)) {
                    $offenders[] = $method . ' ' . $route->uri();
                }
            }
        }

        $offenders = array_values(array_unique($offenders));
        sort($offenders);

        return $offenders;
    }

    /**
     * True when the middleware stack contains a gate that means WRITE.
     * Accepts both the alias form used in the route files and the resolved
     * class form, so the test does not depend on how the stack is gathered.
     */
    private function hasWriteGate(array $middleware): bool
    {
        foreach ($middleware as $mw) {
            if ($mw === 'admin.only' || $mw === 'superadmin') {
                return true;
            }

            if ($mw === \App\Http\Middleware\EnsureAdminRole::class
                || $mw === \App\Modules\Hq\Middleware\EnsureIsSuperadmin::class) {
                return true;
            }

            // module:<m>,edit|delete  — web permission gate
            if (str_starts_with($mw, 'module:')
                || str_starts_with($mw, \App\Http\Middleware\CheckModulePermission::class . ':')) {
                $args = explode(',', substr($mw, strpos($mw, ':') + 1));

                if (in_array(trim($args[1] ?? 'view'), ['edit', 'delete'], true)) {
                    return true;
                }

                continue;
            }

            // api.role:module:<m>,edit|delete — the same grid, API side
            if (str_starts_with($mw, 'api.role:')
                || str_starts_with($mw, \App\Http\Middleware\EnsureApiRole::class . ':')) {
                $args = substr($mw, strpos($mw, ':') + 1);

                if (str_contains($args, 'module:')
                    && (str_contains($args, ',edit') || str_contains($args, ',delete'))) {
                    return true;
                }
            }
        }

        return false;
    }

    public function test_every_write_route_carries_an_edit_delete_or_admin_gate(): void
    {
        $allowed = array_merge(
            self::PUBLIC_BY_DESIGN,
            self::SELF_SERVICE,
            self::KNOWN_UNGATED_WRITES,
        );

        $new = array_values(array_diff($this->ungatedWriteRoutes(), $allowed));

        $this->assertSame([], $new,
            "These state-changing routes carry no edit/delete/admin gate:\n"
            . implode("\n", $new)
            . "\n\nGate them with module:<m>,edit (web) or api.role:module:<m>,edit (API), "
            . "or, if the route is genuinely public or self-service, add it to the matching "
            . "list in " . __CLASS__ . " with a reason.");
    }

    public function test_the_known_ungated_write_list_holds_no_stale_entries(): void
    {
        $stillOpen = $this->ungatedWriteRoutes();

        $fixed = array_values(array_diff(self::KNOWN_UNGATED_WRITES, $stillOpen));

        $this->assertSame([], $fixed,
            "These routes are now gated but are still listed as known offenders. "
            . "Delete their lines from KNOWN_UNGATED_WRITES:\n" . implode("\n", $fixed));
    }

    public function test_the_allow_lists_do_not_overlap(): void
    {
        $this->assertSame([], array_values(array_intersect(
            self::PUBLIC_BY_DESIGN, self::SELF_SERVICE, self::KNOWN_UNGATED_WRITES,
        )));
    }
}
