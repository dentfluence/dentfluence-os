<?php

namespace App\Services\Marketing;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Marketing\Campaign as MktCampaign;
use App\Models\Marketing\MarketingActivityLog;
use Illuminate\Support\Facades\Log;

/**
 * CampaignLeadService
 *
 * Cross-module wiring: Marketing Hub ↔ Inbound Leads (Communication module).
 *
 * When a marketing campaign drives a new lead (via UTM attribution),
 * this service records the link and creates a LeadActivity so the PRM
 * pipeline shows the campaign source.
 *
 * Phase 5 implementation — called from LeadController::store()
 * by checking utm_campaign against mkt_campaigns.slug.
 */
class CampaignLeadService
{
    /**
     * Called when a new Lead is created with UTM parameters.
     * Matches utm_campaign to a marketing campaign slug and logs the attribution.
     *
     * Usage (in LeadController::store after $lead is saved):
     *   CampaignLeadService::attributeLead($lead, $request->utm_campaign, $request->utm_source);
     */
    public static function attributeLead(Lead $lead, ?string $utmCampaign, ?string $utmSource): void
    {
        if (! $utmCampaign) return;

        // Try to find a matching marketing campaign by name (case-insensitive)
        // UTM value should be the campaign name or a lowercased slug version of it
        $mktCampaign = MktCampaign::where('clinic_id', $lead->clinic_id ?? 1)
            ->whereRaw('LOWER(name) = ?', [strtolower($utmCampaign)])
            ->first()

            // Fallback: partial match (e.g. utm_campaign=diwali matches "Diwali 2025 Promo")
            ?? MktCampaign::where('clinic_id', $lead->clinic_id ?? 1)
                ->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($utmCampaign) . '%'])
                ->first();

        if (! $mktCampaign) return;

        try {
            // Log attribution in lead activities
            LeadActivity::create([
                'lead_id'       => $lead->id,
                'activity_type' => 'note',
                'activity_date' => now()->toDateString(),
                'notes'         => "Lead attributed to marketing campaign: \"{$mktCampaign->name}\"" .
                                   ($utmSource ? " (source: {$utmSource})" : ''),
                'created_by'    => null,
            ]);

            // Log in Marketing activity log
            MarketingActivityLog::log(
                $mktCampaign->clinic_id,
                'campaign_lead_attributed',
                $mktCampaign,
                "New lead #{$lead->id} ({$lead->name}) attributed to campaign \"{$mktCampaign->name}\"",
                [
                    'lead_id'      => $lead->id,
                    'lead_name'    => $lead->name,
                    'utm_source'   => $utmSource,
                    'utm_campaign' => $utmCampaign,
                ],
                null
            );

            // Increment campaign leads_count if column exists
            if (\Illuminate\Support\Facades\Schema::hasColumn('mkt_campaigns', 'leads_count')) {
                $mktCampaign->increment('leads_count');
            }

        } catch (\Throwable $e) {
            // Attribution is non-critical — log but don't fail lead creation
            Log::warning("CampaignLeadService: attribution failed for lead #{$lead->id}: " . $e->getMessage());
        }
    }

    /**
     * Get attribution stats for a campaign (used in future Analytics view).
     * Returns: leads_count, conversion_count, estimated_revenue
     */
    public static function getStats(MktCampaign $campaign): array
    {
        // Count lead activities that mention this campaign
        $leadsCount = LeadActivity::where('notes', 'like', "%campaign: \"{$campaign->name}\"%")
            ->count();

        return [
            'leads_count'       => $leadsCount,
            'campaign_id'       => $campaign->id,
            'campaign_name'     => $campaign->name,
        ];
    }
}
