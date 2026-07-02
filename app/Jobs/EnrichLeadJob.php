<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Services\Prm\LeadEnrichmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * EnrichLeadJob — runs AI lead enrichment in the background.
 * ----------------------------------------------------------------------------
 * Queued so saving a lead form stays instant — the slow part (the local model
 * call) happens after the response is sent. Needs a queue worker running:
 *   php artisan queue:work
 * (QUEUE_CONNECTION=database, so jobs land in the `jobs` table.)
 */
class EnrichLeadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Don't hammer a struggling Ollama: a couple of tries, then give up quietly.
    public int $tries = 2;
    public int $timeout = 150;

    public function __construct(public int $leadId) {}

    public function handle(LeadEnrichmentService $service): void
    {
        $lead = Lead::find($this->leadId);
        if (! $lead) {
            return; // lead deleted before the job ran — nothing to do.
        }

        $service->enrich($lead);
    }
}
