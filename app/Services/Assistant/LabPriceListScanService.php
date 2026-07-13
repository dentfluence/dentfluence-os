<?php

namespace App\Services\Assistant;

use RuntimeException;

/**
 * LabPriceListScanService — read a lab vendor's price-list photo/scan and
 * return a table of {service_name, category, rate, turnaround_days} rows.
 * ----------------------------------------------------------------------------
 * Same shape as ReceiptScanService: owns the prompt + response cleanup, image
 * transport is delegated to VisionService (cloud-when-online, local Ollama
 * otherwise). Extraction only — nothing is saved until the clinic reviews the
 * rows and confirms the import, same "human checks before it's real" rule as
 * every other scan feature in this app.
 *
 * Images only for now (same constraint Receipt Scan has) — a scanned/printed
 * price list as a photo or PNG/JPG works; PDF price lists can still be
 * uploaded and kept as a document, just not auto-extracted yet.
 */
class LabPriceListScanService
{
    public function __construct(protected VisionService $vision) {}

    /**
     * @param string $absolutePath Absolute path to the uploaded image file.
     * @param array  $categories   The real LabCase::WORK_CATEGORIES keys, so the
     *                             model maps each row to one of YOUR categories.
     *
     * @return array{rows: array<int, array{service_name:?string, category:?string, rate:?float, turnaround_days:?int}>, engine:string}
     */
    public function scan(string $absolutePath, array $categories = []): array
    {
        if (!is_file($absolutePath)) {
            throw new RuntimeException("Price list image not found: {$absolutePath}");
        }

        $categoryList = !empty($categories)
            ? 'Map each row to the single best category from this list (use the EXACT text, or "" if none fit): '
              . implode(', ', $categories) . '.'
            : 'Suggest a short category per row if obvious, else "".';

        $prompt = <<<PROMPT
You are reading a dental lab's price list / rate card (a photo or scan), for a dental clinic in India.
It's usually a table with a service/work name and a price per row, sometimes with a turnaround time.
Extract every row you can read and return ONLY a single JSON object, no prose, no markdown fences.

Use exactly this shape:
{
  "rows": [
    {
      "service_name": "the treatment/work name as written, e.g. 'Zirconia Crown' (string)",
      "category": "string",
      "rate": number,
      "turnaround_days": number or null
    }
  ]
}

Rules:
- One row per distinct service/line item in the price list. Skip headers, totals, and blank rows.
- rate is the price as a plain number, no currency symbol or commas.
- turnaround_days: only set this if a turnaround/delivery time is explicitly shown for that row (e.g. "3 days"); otherwise null. Never guess.
- $categoryList
- If a field is not clearly readable, use "" for text or null for numbers. Never guess wildly.
- If you cannot find a price-list table at all, return {"rows": []}.
Return the JSON object only.
PROMPT;

        $result  = $this->vision->read($absolutePath, $prompt);
        $content = trim($result['text']);
        $parsed  = $this->decodeJson($content);

        if ($parsed === null || !isset($parsed['rows']) || !is_array($parsed['rows'])) {
            throw new RuntimeException(
                "Couldn't read a price-list table clearly. Try a sharper, well-lit photo — " .
                'or add services manually.'
            );
        }

        return [
            'rows'   => $this->normaliseRows($parsed['rows'], $categories),
            'engine' => $result['engine'],
        ];
    }

    /** Tolerantly pull a JSON object out of the model's reply. */
    protected function decodeJson(string $content): ?array
    {
        $data = json_decode($content, true);
        if (is_array($data)) {
            return $data;
        }
        if (preg_match('/\{.*\}/s', $content, $m)) {
            $data = json_decode($m[0], true);
            if (is_array($data)) {
                return $data;
            }
        }
        return null;
    }

    /** Clean, type-coerce, and cap the extracted rows into safe form values. */
    protected function normaliseRows(array $rows, array $categories): array
    {
        $out = [];

        foreach (array_slice($rows, 0, 100) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $name = mb_substr(trim((string) ($row['service_name'] ?? '')), 0, 150);
            if ($name === '') {
                continue;
            }

            $rate = $row['rate'] ?? null;
            if (is_string($rate)) {
                $rate = preg_replace('/[^0-9.]/', '', $rate);
            }
            $rate = ($rate === '' || $rate === null) ? null : (float) $rate;

            $turnaround = $row['turnaround_days'] ?? null;
            $turnaround = is_numeric($turnaround) ? max(1, min(90, (int) $turnaround)) : null;

            // Only keep the category if it exactly matches one of our real categories —
            // otherwise leave it blank for the reviewer to pick, rather than store a
            // category that doesn't exist in LabCase::WORK_CATEGORIES.
            $category = mb_substr(trim((string) ($row['category'] ?? '')), 0, 100);
            if (!in_array($category, $categories, true)) {
                $category = '';
            }

            $out[] = [
                'service_name'    => $name,
                'category'        => $category,
                'rate'            => $rate,
                'turnaround_days' => $turnaround,
            ];
        }

        return $out;
    }
}
