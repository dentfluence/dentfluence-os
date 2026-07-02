<?php

namespace App\Services\Voice;

use App\Services\Assistant\OllamaClient;

/**
 * ClinicalNoteService — turns a raw consultation transcript into STRUCTURED
 * dental clinical notes, locally via Ollama. This is the heart of the original
 * voice-notes idea: dictate/record a consultation → get organised notes.
 */
class ClinicalNoteService
{
    /** The structured fields we ask the model to fill (map cleanly to a consultation). */
    public const FIELDS = [
        'chief_complaint', 'history', 'findings', 'diagnosis',
        'treatment_done', 'treatment_plan', 'advice', 'prescription', 'follow_up',
    ];

    public function __construct(protected OllamaClient $ollama) {}

    /**
     * @return array Structured notes keyed by self::FIELDS (+ '_raw' on parse miss).
     */
    public function analyze(string $transcript): array
    {
        $transcript = trim($transcript);
        if ($transcript === '') {
            return array_fill_keys(self::FIELDS, '');
        }

        $reply = $this->ollama->chat(
            [
                ['role' => 'system', 'content' => $this->systemPrompt()],
                ['role' => 'user',   'content' => "Consultation transcript:\n\n" . $transcript],
            ],
            [],                                   // no tools
            config('assistant.model'),            // a pulled local model
            ['temperature' => 0.1],               // factual, low creativity
        );

        return $this->parse($reply['content'] ?? '');
    }

    protected function systemPrompt(): string
    {
        $keys = implode(', ', self::FIELDS);

        return <<<PROMPT
You are an experienced DENTAL clinical scribe. Read the consultation transcript
and extract structured, dental-native clinical notes for the patient record.

Return ONLY a single JSON object with exactly these keys: {$keys}.
- chief_complaint: why the patient came, in their words (include duration if said).
- history: relevant history of the complaint + any medical history, allergies,
  medications, or habits (smoking, bruxism) mentioned.
- findings: clinical/examination findings. Be dental-specific:
  * Refer to teeth by FDI two-digit notation when a tooth is named or numbered
    (e.g. upper-right first molar = 16; lower-left central incisor = 31). If the
    dentist says a tooth in plain words, convert it to the FDI number AND keep the
    plain description in brackets, e.g. "16 (upper right first molar): deep caries".
  * Note involved surfaces using standard codes when stated: M, D, O, B/F, L/P
    (mesial, distal, occlusal, buccal/facial, lingual/palatal).
  * Cover relevant categories when mentioned: caries, mobility (grade I-III),
    periodontal (pocket depths, BOP, recession), pulpal/periapical status,
    fractures, existing restorations, soft tissue, occlusion, and radiographic
    findings (IOPA/OPG/CBCT).
- diagnosis: the dentist's diagnosis or provisional diagnosis, per tooth where
  applicable (e.g. "16: irreversible pulpitis").
- treatment_done: procedures performed today, tooth-wise, with materials/details
  when stated (e.g. "16: access opening + pulpectomy; 36: GIC restoration").
- treatment_plan: planned future treatment, tooth-wise and in sequence/visits if
  mentioned (e.g. "16: RCT then PFM crown next visit").
- advice: post-op or general advice given.
- prescription: medicines prescribed — name, strength, dose, frequency, duration
  (e.g. "Amoxicillin 500mg TDS x 5 days").
- follow_up: follow-up instructions or next visit timing.

Rules:
- Use ONLY information present in the transcript. Do NOT invent anything, and do
  NOT guess tooth numbers that were not stated — only convert clearly-named teeth.
- If something isn't mentioned, set that key to an empty string "".
- Be concise and clinical. Respond with JSON only — no prose, no markdown fences.
PROMPT;
    }

    /** Pull the JSON object out of the model's reply, leniently. */
    protected function parse(string $content): array
    {
        $content = trim($content);

        // Strip ```json fences if present.
        $content = preg_replace('/^```(?:json)?|```$/m', '', $content);

        $json = null;
        if (preg_match('/\{.*\}/s', $content, $m)) {
            $json = json_decode($m[0], true);
        }

        if (!is_array($json)) {
            // Couldn't parse — return the raw text so nothing is lost.
            return array_merge(array_fill_keys(self::FIELDS, ''), ['_raw' => $content]);
        }

        // Normalise: ensure every expected key exists.
        $out = [];
        foreach (self::FIELDS as $f) {
            $out[$f] = is_string($json[$f] ?? null) ? trim($json[$f]) : (string) ($json[$f] ?? '');
        }
        return $out;
    }
}
