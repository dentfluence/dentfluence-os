<?php

namespace App\Services\Prm;

use App\Models\Lead;
use App\Services\Assistant\OllamaClient;
use Illuminate\Support\Facades\Log;

/**
 * LeadEnrichmentService — PRM AI, Phase 1.
 * ----------------------------------------------------------------------------
 * Boxly's headline trick: every new lead is auto-summarised, labelled, and
 * prioritised so staff understand it at a glance and type almost nothing.
 *
 * What it does, 100% locally (no cloud, no cost):
 *   • 5-word summary of the enquiry
 *   • treatment label (chosen from our allowed list)
 *   • urgency (low | medium | high)
 *   • estimated ₹ value — looked up from config bands by treatment, so the
 *     number is deterministic and never invented by the model.
 *
 * The model ONLY classifies text. It never produces money figures or IDs.
 */
class LeadEnrichmentService
{
    public function __construct(
        protected OllamaClient $ollama = new OllamaClient(),
    ) {}

    /**
     * Enrich a lead and (by default) save the ai_* columns.
     *
     * @return array The enrichment values written (summary, treatment, urgency, value).
     */
    public function enrich(Lead $lead, bool $persist = true): array
    {
        $ai = $this->classify($lead);

        // Map the AI's treatment label → a deterministic ₹ band from config.
        $value = $this->valueForTreatment($ai['treatment'] ?? null);

        $data = [
            'ai_summary'         => $ai['summary'] ?: null,
            'ai_treatment_label' => $ai['treatment'] ?: null,
            'ai_urgency'         => $ai['urgency'] ?: null,
            'ai_estimated_value' => $value,
            'ai_enriched_at'     => now(),
        ];

        if ($persist) {
            // Use saveQuietly so enrichment doesn't re-trigger the created/updated
            // observer (which would loop). We only touch the ai_* columns.
            $lead->forceFill($data)->saveQuietly();
        }

        return $data;
    }

    /**
     * Ask the local model to classify the enquiry. Returns
     * ['summary' => string, 'treatment' => string, 'urgency' => string].
     * Always returns safe defaults — never throws into the caller.
     */
    protected function classify(Lead $lead): array
    {
        $allowed = implode(', ', config('prm.treatments', []));

        // Only feed the parts that describe what the patient wants. No need for
        // the model to see name/phone (privacy + keeps it focused).
        $context = collect([
            'Stated treatment'   => $lead->treatment,
            'Secondary interest' => $lead->secondary_treatment,
            'Notes / enquiry'    => $lead->notes,
            'Source channel'     => $lead->source ?: $lead->lead_source,
        ])->filter()->map(fn ($v, $k) => "{$k}: {$v}")->implode("\n");

        if ($context === '') {
            // Nothing to work with — return blanks rather than calling the model.
            return ['summary' => '', 'treatment' => '', 'urgency' => ''];
        }

        $system = <<<PROMPT
You classify dental clinic leads. Reply with ONLY a JSON object, no prose, no markdown.
Schema:
{
  "summary": "max 6 words, what the lead wants",
  "treatment": "EXACTLY ONE of: {$allowed}",
  "urgency": "one of: low, medium, high"
}
Rules:
- "summary" is a glance-able phrase, e.g. "Wants implant, broken front tooth".
- Pick the closest "treatment" from the list. If unclear, use "Other".
- "urgency": high = pain/emergency/broken tooth/swelling; medium = wants treatment soon; low = just enquiring.
- Output JSON only.
PROMPT;

        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $context],
        ];

        try {
            $reply = $this->ollama->chat(
                $messages,
                tools: [],
                model: config('prm.ai.model') ?: config('assistant.model'),
                options: ['temperature' => 0.2],
            );

            return $this->parse($reply['content'] ?? '');
        } catch (\Throwable $e) {
            // AI down / model missing — log and degrade gracefully. The lead is
            // still created; staff just won't see AI tags until a re-run.
            Log::warning('PRM lead enrichment failed', [
                'lead_id' => $lead->id,
                'error'   => $e->getMessage(),
            ]);
            return ['summary' => '', 'treatment' => '', 'urgency' => ''];
        }
    }

    /**
     * Pull the JSON object out of the model's reply and normalise it.
     */
    protected function parse(string $content): array
    {
        // Grab the first {...} block in case the model wrapped it in text.
        if (preg_match('/\{.*\}/s', $content, $m)) {
            $content = $m[0];
        }

        $json = json_decode($content, true) ?: [];

        $summary   = trim((string) ($json['summary'] ?? ''));
        $treatment = trim((string) ($json['treatment'] ?? ''));
        $urgency   = strtolower(trim((string) ($json['urgency'] ?? '')));

        // Keep treatment honest — only accept a value from our allowed list.
        $allowed = config('prm.treatments', []);
        if ($treatment !== '' && ! in_array($treatment, $allowed, true)) {
            // Try a loose, case-insensitive match before giving up.
            $match = collect($allowed)->first(
                fn ($t) => strtolower($t) === strtolower($treatment)
            );
            $treatment = $match ?: 'Other';
        }

        if (! in_array($urgency, ['low', 'medium', 'high'], true)) {
            $urgency = '';
        }

        // Trim the summary to a sane length (model sometimes over-writes).
        if (mb_strlen($summary) > 110) {
            $summary = mb_substr($summary, 0, 110);
        }

        return ['summary' => $summary, 'treatment' => $treatment, 'urgency' => $urgency];
    }

    /**
     * Deterministic ₹ value from the treatment label via config bands.
     */
    protected function valueForTreatment(?string $treatment): ?float
    {
        if (! $treatment) {
            return null;
        }
        $bands = config('prm.value_bands', []);
        return isset($bands[strtolower($treatment)])
            ? (float) $bands[strtolower($treatment)]
            : null;
    }
}
