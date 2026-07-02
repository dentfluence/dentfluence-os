<?php

namespace App\Services\Assistant;

use RuntimeException;

/**
 * ReceiptScanService — read a snapped bill/receipt and return structured fields.
 * ----------------------------------------------------------------------------
 * Owns the prompt + how the reply is cleaned. The actual image→text step is
 * delegated to VisionService, which picks the engine: a frontier cloud model
 * when online + configured (best on handwriting/varied invoices), or the local
 * Ollama model offline. Either way the human reviews the pre-filled form before
 * saving, so a misread is a quick fix, never a silent bad record.
 */
class ReceiptScanService
{
    public function __construct(protected VisionService $vision) {}

    /**
     * Read a bill image and return structured expense fields.
     *
     * @param string $absolutePath  Absolute path to the uploaded image file.
     * @param array  $categories    Existing category names, so the model picks
     *                              one of YOUR categories instead of inventing one.
     *
     * @return array{
     *   title:?string, vendor_name:?string, amount:?float, expense_date:?string,
     *   gst_applicable:bool, gst_rate:?float, category_guess:?string,
     *   payment_mode:?string, reference:?string, description:?string, raw:string
     * }
     */
    public function scan(string $absolutePath, array $categories = []): array
    {
        if (!is_file($absolutePath)) {
            throw new RuntimeException("Bill image not found: {$absolutePath}");
        }

        // Give the model the clinic's real category list to choose from.
        $categoryList = !empty($categories)
            ? 'Choose the single best category from this list (use the EXACT text, or "" if none fit): '
              . implode(', ', $categories) . '.'
            : 'Suggest a short category if obvious, else "".';

        $prompt = <<<PROMPT
You are reading a photographed bill, invoice, or receipt for a dental clinic in India.
Extract the payment details and return ONLY a single JSON object, no prose, no markdown fences.

Use exactly these keys:
{
  "title": "short label for this expense, e.g. vendor or item (string)",
  "vendor_name": "who was paid / shop name (string or empty)",
  "amount": number  // the GRAND TOTAL actually payable, as a plain number, no currency symbol or commas,
  "expense_date": "YYYY-MM-DD or empty if not visible",
  "gst_applicable": true or false,
  "gst_rate": number  // GST percentage if shown (5,12,18,28), else 0,
  "category_guess": "string",
  "payment_mode": "one of: cash, upi, card, bank_transfer, cheque, other, or empty",
  "reference": "bill/invoice number if visible, else empty",
  "description": "1 short line of what was bought, else empty"
}

Rules:
- amount must be the final total payable (after taxes/discounts), not a line item.
- If a value is not clearly readable, use "" (or 0 for amount/gst_rate). Never guess wildly.
- $categoryList
- Dates may be DD/MM/YYYY or DD-MM-YYYY — convert to YYYY-MM-DD.
Return the JSON object only.
PROMPT;

        // Engine selection (cloud-when-online vs local) lives in VisionService.
        $result  = $this->vision->read($absolutePath, $prompt);
        $content = trim($result['text']);
        $parsed  = $this->decodeJson($content);

        if ($parsed === null) {
            throw new RuntimeException(
                "Couldn't read the bill clearly. Try a sharper, well-lit photo — " .
                "or just type the expense in manually."
            );
        }

        $out = $this->normalise($parsed, $content);
        $out['engine'] = $result['engine'];   // 'cloud:<driver>' or 'local'
        return $out;
    }

    /** Tolerantly pull a JSON object out of the model's reply. */
    protected function decodeJson(string $content): ?array
    {
        $data = json_decode($content, true);
        if (is_array($data)) {
            return $data;
        }
        // Fallback: grab the first {...} block if the model wrapped it in text.
        if (preg_match('/\{.*\}/s', $content, $m)) {
            $data = json_decode($m[0], true);
            if (is_array($data)) {
                return $data;
            }
        }
        return null;
    }

    /** Clean and type-coerce the model's raw fields into safe form values. */
    protected function normalise(array $d, string $raw): array
    {
        // Amount: strip anything that isn't a digit or dot (commas, ₹, "Rs.").
        $amount = $d['amount'] ?? null;
        if (is_string($amount)) {
            $amount = preg_replace('/[^0-9.]/', '', $amount);
        }
        $amount = ($amount === '' || $amount === null) ? null : (float) $amount;

        $gstRate = (float) ($d['gst_rate'] ?? 0);
        $allowedModes = ['cash', 'upi', 'card', 'bank_transfer', 'cheque', 'other'];
        $mode = strtolower(trim((string) ($d['payment_mode'] ?? '')));
        $mode = in_array($mode, $allowedModes, true) ? $mode : null;

        // Validate the date is real; otherwise drop it so the form keeps "today".
        $date = trim((string) ($d['expense_date'] ?? ''));
        if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = '';
        }

        return [
            'title'          => $this->str($d['title'] ?? '') ?: $this->str($d['vendor_name'] ?? ''),
            'vendor_name'    => $this->str($d['vendor_name'] ?? ''),
            'amount'         => $amount,
            'expense_date'   => $date ?: null,
            'gst_applicable' => (bool) ($d['gst_applicable'] ?? ($gstRate > 0)),
            'gst_rate'       => $gstRate > 0 ? $gstRate : null,
            'category_guess' => $this->str($d['category_guess'] ?? ''),
            'payment_mode'   => $mode,
            'reference'      => $this->str($d['reference'] ?? ''),
            'description'    => $this->str($d['description'] ?? ''),
            'raw'            => $raw,
        ];
    }

    /** Trim to a clean string, capping silly lengths. */
    protected function str($v): string
    {
        return mb_substr(trim((string) $v), 0, 200);
    }
}
