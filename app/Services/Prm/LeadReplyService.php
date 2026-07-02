<?php

namespace App\Services\Prm;

use App\Models\Lead;
use App\Services\Assistant\OllamaClient;

/**
 * LeadReplyService — PRM Phase 3 (AI draft replies).
 * ----------------------------------------------------------------------------
 * Generates a ready-to-edit reply for a lead, using the local model and the
 * context we already have (name, treatment, AI summary, stage, source). The
 * draft is ALWAYS reviewed and sent by a human — this never sends anything.
 *
 * Channel-aware: WhatsApp/SMS are short and friendly; email gets a greeting,
 * body and sign-off. Tone = warm, professional clinic front desk. The model is
 * told NOT to invent prices, dates, discounts or promises.
 */
class LeadReplyService
{
    public function __construct(
        protected OllamaClient $ollama = new OllamaClient(),
    ) {}

    /**
     * Draft a reply. Returns plain text ready to drop into the chosen channel.
     */
    public function draft(Lead $lead, string $channel = 'whatsapp'): string
    {
        $channel    = in_array($channel, config('prm.replies.channels', ['whatsapp']), true)
            ? $channel
            : 'whatsapp';

        $clinic   = config('prm.replies.clinic_name') ?: config('app.name', 'our clinic');
        $language = config('assistant.reply_language', 'English');
        $first    = trim(explode(' ', trim($lead->name))[0] ?? 'there');

        // What does the patient want + where are they in the journey?
        $treatment = $lead->ai_treatment_label ?: $lead->treatment ?: 'a dental consultation';
        $context = collect([
            'Patient first name' => $first,
            'Interested in'      => $treatment,
            'Quick summary'      => $lead->ai_summary,
            'Pipeline stage'     => $lead->stage,
            'Enquiry source'     => $lead->source ?: $lead->lead_source,
            'Notes'              => $lead->notes,
        ])->filter()->map(fn ($v, $k) => "{$k}: {$v}")->implode("\n");

        $channelRules = match ($channel) {
            'email' => "Write a short email: a greeting line, 2-3 sentence body, and a sign-off as the {$clinic} team. You may use line breaks.",
            'sms'   => "Write ONE short SMS, under 320 characters, friendly and clear. No subject line.",
            default => "Write a short, warm WhatsApp message (2-4 short sentences). You may use a single emoji at most.",
        };

        $stageHint = match ($lead->stage) {
            'new_lead'    => "This is a brand-new enquiry — thank them, acknowledge their interest, and invite them to book a consultation.",
            'contacted'   => "You've already reached out — gently follow up and offer to answer questions or book a visit.",
            'plan_given'  => "They've received a treatment plan — warmly nudge them to proceed and offer to help with any questions.",
            'appointment' => "An appointment is being arranged — help confirm and reassure them.",
            'consultation'=> "They've had a consultation — follow up on next steps.",
            default       => "Encourage them toward the next step in a helpful way.",
        };

        $system = <<<PROMPT
You are the front desk at {$clinic}, a dental clinic. Write a reply to a prospective patient.
Reply ONLY with the message text — no preamble, no quotes, no labels.

Guidelines:
- Write in {$language}.
- Address the patient by their first name.
- {$channelRules}
- {$stageHint}
- Be warm, respectful and professional. Sound human, not salesy.
- Do NOT invent or promise prices, discounts, exact dates, or clinical outcomes.
- Do NOT make medical claims. Keep it about helping them take the next step.
PROMPT;

        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $context],
        ];

        $reply = $this->ollama->chat(
            $messages,
            tools: [],
            model: config('prm.replies.model') ?: config('assistant.model'),
            options: ['temperature' => 0.6], // a little warmth/variety
        );

        return $this->clean($reply['content'] ?? '');
    }

    /**
     * Strip stray wrapping quotes / leading labels the model sometimes adds.
     */
    protected function clean(string $text): string
    {
        $text = trim($text);
        // Remove a single pair of wrapping quotes if present.
        if (preg_match('/^"(.*)"$/s', $text, $m)) {
            $text = trim($m[1]);
        }
        return $text;
    }
}
