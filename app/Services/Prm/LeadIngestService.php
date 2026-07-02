<?php

namespace App\Services\Prm;

use App\Models\Lead;
use App\Services\Relationship\RelationshipEngine;

/**
 * LeadIngestService — PRM Phase 4 (one-inbox channel ingestion).
 * ----------------------------------------------------------------------------
 * Single place every inbound channel (website form, Meta Lead Ads, WhatsApp)
 * creates a Lead. Keeps the webhook controllers thin and guarantees identical
 * behaviour: same dedupe rule, same defaults, same "created" activity — and the
 * created lead always flows through LeadObserver (auto-assign + follow-up + AI).
 *
 * Source is set by the CALLER (the channel), never trusted from the payload.
 */
class LeadIngestService
{
    /**
     * Create a lead from an inbound channel.
     *
     * @param array  $data       name, phone, email, treatment, notes (any may be null)
     * @param string $leadSource lead_source enum key, e.g. 'website_form', 'facebook', 'whatsapp'
     * @param string $by         activity "by" label, e.g. 'Website', 'Meta Lead Ads', 'WhatsApp'
     * @param string $logNote    creation note for the timeline
     *
     * @return array{lead: Lead, duplicate: bool}
     */
    public function ingest(array $data, string $leadSource, string $by, string $logNote): array
    {
        $phone = $data['phone'] ?? null;

        // Dedupe: same phone, still open, created within the window.
        if ($phone) {
            $window   = (int) config('prm.webhooks.dedupe_minutes', 10);
            $existing = Lead::where('phone', $phone)
                ->whereNotIn('stage', ['converted', 'lost'])
                ->where('created_at', '>=', now()->subMinutes($window))
                ->first();

            if ($existing) {
                return ['lead' => $existing, 'duplicate' => true];
            }
        }

        $lead = Lead::create([
            'name'        => $data['name']      ?? 'New Lead',
            'phone'       => $phone ?? '', // column is NOT null; '' when channel has no phone

            'email'       => $data['email']     ?? null,
            'treatment'   => $data['treatment'] ?? null,
            'notes'       => $data['notes']     ?? null,
            'stage'       => 'new_lead',
            'lead_source' => $leadSource,
            'source'      => Lead::LEAD_SOURCES[$leadSource] ?? ucfirst($leadSource),
            'urgency'     => 'medium',
        ]);

        $lead->activities()->create([
            'type'          => 'note',
            'label'         => 'Lead Created (' . $by . ')',
            'note'          => $logNote,
            'activity_date' => today(),
            'activity_time' => now()->format('h:i A'),
            'by'            => $by,
        ]);

        // Phase 1 — Relationship Engine: link this lead to a Relationship record.
        // Runs after the lead is created so relationship_id can be saved back.
        // Wrapped in try/catch inside linkLead — never breaks lead creation.
        app(RelationshipEngine::class)->linkLead($lead);

        return ['lead' => $lead, 'duplicate' => false];
    }
}
