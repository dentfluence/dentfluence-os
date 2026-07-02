<?php

namespace App\Observers;

use App\Jobs\EnrichLeadJob;
use App\Models\Lead;
use App\Services\Prm\LeadFollowUpService;
use App\Services\Prm\LeadRoutingService;
use App\Services\Relationship\ActivityEngine;
use App\Services\Relationship\RelationshipEngine;
use Illuminate\Support\Facades\Log;

/**
 * LeadObserver — hooks the Lead model lifecycle.
 * ----------------------------------------------------------------------------
 * On create, kicks off AI enrichment in the background (if enabled in config).
 * Registered via the #[ObservedBy(LeadObserver::class)] attribute on the Lead
 * model, so no manual provider wiring is needed.
 */
class LeadObserver
{
    /**
     * After a new lead is created → enrich it with the local AI.
     */
    public function created(Lead $lead): void
    {
        // 0) Relationship identity (Phase 1) — link EVERY new lead to its Master
        //    Relationship, no matter how it was created (webhook ingest, PRM board
        //    Add Lead / Quick Add, import, …). Previously only LeadIngestService
        //    (the webhook path) called linkLead, so manually-added PRM leads kept
        //    relationship_id = null — invisible to PRE and untargetable by
        //    Automation. linkLead is idempotent and saves quietly, so the webhook
        //    path's later call is a harmless no-op and there's no observer loop.
        try {
            app(RelationshipEngine::class)->linkLead($lead);
        } catch (\Throwable $e) {
            // linkLead already swallows its own errors; this is a belt-and-braces
            // guard so identity linking can never block lead creation.
            Log::warning('LeadObserver linkLead failed', [
                'lead_id' => $lead->id,
                'error'   => $e->getMessage(),
            ]);
        }

        // 1) Auto-assign to staff (Phase 2a). Runs inline — it's a couple of
        //    cheap queries — but never blocks lead creation if it fails.
        if (config('prm.routing.enabled') && config('prm.routing.auto_on_create')) {
            try {
                app(LeadRoutingService::class)->assign($lead);
            } catch (\Throwable $e) {
                Log::warning('PRM lead auto-assign failed', [
                    'lead_id' => $lead->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        // 2) Follow-up reminder for the lead's starting stage (Phase 2b).
        if (config('prm.followups.enabled')) {
            try {
                app(LeadFollowUpService::class)->createForStage($lead, $lead->stage);
            } catch (\Throwable $e) {
                Log::warning('PRM lead follow-up creation failed', [
                    'lead_id' => $lead->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        // 3) AI enrichment (Phase 1) — runs in the background queue.
        if (config('prm.ai.enabled') && config('prm.ai.auto_on_create')) {
            EnrichLeadJob::dispatch($lead->id);
        }

        // 4) Relationship Engine — Phase 1: log 'lead.created' to the universal Activity table.
        //    This runs after linkLead() (called in LeadIngestService) has set relationship_id.
        //    We refresh $lead so relationship_id is populated if it was just saved.
        try {
            $lead->refresh();
            app(ActivityEngine::class)->log(
                subject:        $lead,
                event:          'lead.created',
                actor:          auth()->user() ?? null,
                metadata:       [
                    'source'  => $lead->lead_source,
                    'stage'   => $lead->stage,
                    'channel' => $lead->source,
                ],
                description: 'New lead created from ' . ($lead->source ?? 'unknown channel'),
            );
        } catch (\Throwable $e) {
            Log::warning('ActivityEngine lead.created log failed', [
                'lead_id' => $lead->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
