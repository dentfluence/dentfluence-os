<?php

namespace App\Services\Presentations;

use App\Models\AppSetting;
use App\Models\Presentation;
use App\Models\PresentationAccessToken;
use Illuminate\Support\Str;

/**
 * PresentationLinkService — the secure/expiring/revocable link a patient
 * opens without logging in. See docs/plan-smart-treatment-presentation.md §8
 * ("Engineering risks") — this is the one component carrying PHI to an
 * unauthenticated recipient, so every link is single-active-per-presentation,
 * always revocable, and default-expiring rather than open-ended.
 */
class PresentationLinkService
{
    public const DEFAULT_EXPIRY_DAYS_SETTING = 'presentations_default_link_expiry_days';
    public const DEFAULT_EXPIRY_DAYS = 30;

    /**
     * Issue a fresh token, auto-revoking any previously-valid one for this
     * presentation — never leave two live links for the same presentation
     * floating around (WhatsApp forwards/caches longer than expected).
     */
    public function issue(Presentation $presentation, ?int $expiryDays = null): PresentationAccessToken
    {
        $presentation->accessTokens()
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $days = $expiryDays ?? (int) AppSetting::get(self::DEFAULT_EXPIRY_DAYS_SETTING, self::DEFAULT_EXPIRY_DAYS);

        return $presentation->accessTokens()->create([
            'token'      => Str::random(48),
            'expires_at' => $days > 0 ? now()->addDays($days) : null, // 0/null = never expires
        ]);
    }

    public function revoke(PresentationAccessToken $token): void
    {
        $token->update(['revoked_at' => now()]);
    }

    public function url(PresentationAccessToken $token): string
    {
        return route('presentations.public.show', $token->token);
    }

    /**
     * Has the underlying plan/cost changed since the snapshot we already
     * showed the patient? Used before a resend that comes a month or more
     * after the original send — see the confirmed resend behavior in
     * docs/plan-smart-treatment-presentation.md.
     */
    public function isStale(Presentation $presentation): bool
    {
        $snapshot = $presentation->snapshot?->snapshot;
        if (! $snapshot) {
            return false; // never finalized/snapshotted — nothing to compare against
        }

        $currentCost = $presentation->currentCostSummary();
        $currentItemCount = $presentation->treatmentPlan?->items->count() ?? 0;
        $snapshotItemCount = count($snapshot['items'] ?? []);

        $costChanged = round((float) ($snapshot['cost']['total'] ?? 0), 2) !== round($currentCost['total'], 2);
        $itemsChanged = $snapshotItemCount !== $currentItemCount;

        return $costChanged || $itemsChanged;
    }

    /** Days since this presentation was last (re)sent — null if never sent. */
    public function daysSinceLastSend(Presentation $presentation): ?int
    {
        return $presentation->sent_at?->diffInDays(now());
    }
}
