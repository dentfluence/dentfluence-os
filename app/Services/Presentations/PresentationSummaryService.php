<?php

namespace App\Services\Presentations;

use App\Models\Presentation;
use App\Services\Assistant\OllamaClient;

/**
 * PresentationSummaryService — drafts a plain-language, patient-facing
 * explanation of a treatment plan, locally via Ollama. Mirrors the calling
 * pattern of App\Services\Voice\ClinicalNoteService.
 *
 * The draft is always a STARTING POINT: Presentation::ai_summary_text is only
 * ever shown to a patient after a dentist has reviewed/edited it and set
 * reviewed_at (see PresentationController@finalize) — this service never
 * marks anything reviewed itself.
 */
class PresentationSummaryService
{
    public function __construct(protected OllamaClient $ollama) {}

    /**
     * @return string Plain-text draft summary (may be edited by the dentist before send).
     * @throws \RuntimeException if the local model is unreachable — caller must
     *         catch this and let the dentist write the summary manually instead.
     */
    public function generate(Presentation $presentation): string
    {
        $patient = $presentation->patient;
        $consultation = $presentation->consultation;
        $plan = $presentation->treatmentPlan;
        $items = $plan?->items ?? collect();
        $cost = $presentation->currentCostSummary();

        $reply = $this->ollama->chat(
            [
                ['role' => 'system', 'content' => $this->systemPrompt()],
                ['role' => 'user',   'content' => $this->buildPrompt($patient, $consultation, $items, $cost)],
            ],
            [],                          // no tools
            config('assistant.model'),   // local model, e.g. qwen2.5:7b
            ['temperature' => 0.4],      // a little warmer than the clinical scribe — this is patient-facing prose
        );

        return trim($reply['content'] ?? '');
    }

    protected function systemPrompt(): string
    {
        return <<<'PROMPT'
You are writing a short, warm, plain-language explanation of a dental treatment
plan for the PATIENT to read — not clinical documentation for another dentist.

Rules:
- Do NOT write a letter. No "Hello [Name]," greeting, no "Dear...", no sign-off
  like "Take care," or "[Your Dentist's Name]". The patient already sees their
  name and the clinic's name in the page header above this text — starting or
  ending with either is redundant. Start directly with the explanation itself.
- Use simple words. No jargon, no ICD codes, no abbreviations (spell out what a
  procedure is instead of just naming it).
- Explain WHY the treatment is needed (the diagnosis, in plain terms), WHAT will
  be done (each procedure, briefly), and reassure without exaggerating.
- When describing WHERE a tooth is (front/back, top/bottom), get it right for
  EACH tooth individually — do not average or guess a shared location for a
  group of teeth. If teeth in the same procedure are in different locations,
  either describe each one separately or drop the location description and
  just refer to them by treatment (e.g. "the two implants we discussed").
- 2-4 short paragraphs. Warm but plain-spoken, like a dentist talking directly
  to the patient face-to-face — not writing them a formal letter. Use "you"/
  "your smile" naturally.
- Do NOT invent any clinical detail, procedure, tooth, or cost that isn't given
  to you below. If something isn't provided, don't mention it.
- Do NOT make promises about outcomes, timelines being guaranteed, or pain-free
  claims. Do NOT give advice about delaying treatment or comparing alternatives
  — that is a clinical judgement for the dentist to make in person.
- Output plain text only — no markdown, no headings, no bullet points, no
  greeting, no sign-off.
PROMPT;
    }

    protected function buildPrompt($patient, $consultation, $items, array $cost): string
    {
        $lines = [];

        $lines[] = 'Patient: ' . ($patient?->name ?? 'the patient') .
            ($patient?->age ? ', ' . $patient->age : '') .
            ($patient?->gender ? ', ' . $patient->gender : '');

        if ($consultation) {
            if ($consultation->chief_complaint) {
                $lines[] = 'Chief complaint: ' . $consultation->chief_complaint;
            }
            if ($consultation->primary_diagnosis) {
                $lines[] = 'Diagnosis: ' . $consultation->primary_diagnosis;
            }
            if ($consultation->secondary_diagnosis) {
                $lines[] = 'Secondary diagnosis: ' . $consultation->secondary_diagnosis;
            }
        }

        if ($items->isNotEmpty()) {
            $lines[] = 'Planned procedures:';
            foreach ($items as $item) {
                $tooth = $item->tooth_number ? " (tooth {$item->tooth_number})" : '';
                $lines[] = "- {$item->treatment_name}{$tooth}";
            }
        }

        $lines[] = 'Total estimated cost: Rs. ' . number_format($cost['total'], 0);
        if ($cost['discount_amount'] > 0 || $cost['membership_discount'] > 0) {
            $savings = $cost['discount_amount'] + $cost['membership_discount'];
            $lines[] = 'Total savings/discount applied: Rs. ' . number_format($savings, 0);
        }

        return implode("\n", $lines);
    }
}
