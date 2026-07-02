<?php

namespace App\Services\Assistant;

use RuntimeException;

/**
 * PatientScanService — read a photographed patient registration / intake form
 * and return structured fields for the Add Patient modal to pre-fill.
 * ----------------------------------------------------------------------------
 * Owns the prompt + field cleanup. The image→text step is delegated to
 * VisionService, which uses a frontier cloud model when online + configured
 * (much better on handwriting), or the local Ollama model offline. Staff ALWAYS
 * reviews the filled tabs before tapping Register, so a misread is a quick fix.
 */
class PatientScanService
{
    public function __construct(protected VisionService $vision) {}

    /**
     * Read a patient intake form image and return structured patient fields.
     *
     * @param string $absolutePath  Absolute path to the uploaded image file.
     * @return array  Cleaned fields keyed exactly like the Add Patient form.
     */
    public function scan(string $absolutePath): array
    {
        if (!is_file($absolutePath)) {
            throw new RuntimeException("Form image not found: {$absolutePath}");
        }

        $prompt = <<<PROMPT
You are reading a photographed PATIENT REGISTRATION / INTAKE form from a dental
clinic in India. The form may be typed or handwritten. Extract the patient's
details and return ONLY a single JSON object — no prose, no markdown fences.

Use exactly these keys (use "" for anything not clearly readable; never invent):
{
  "title": "Mr./Mrs./Miss/Mast./Dr. or empty",
  "first_name": "string",
  "middle_name": "string",
  "last_name": "string",
  "gender": "one of: male, female, other, or empty",
  "dob": "YYYY-MM-DD or empty",
  "age_years": number or null,
  "mobile": "primary phone digits only, or empty",
  "alternate_phone": "string",
  "email": "string",
  "emergency_contact_name": "string",
  "emergency_contact_relationship": "string",
  "emergency_contact_number": "string",
  "address": "street / house, or empty",
  "area": "locality / area, or empty",
  "city": "string",
  "pincode": "6-digit PIN, or empty",
  "medical_conditions": ["array of conditions like Diabetes, Hypertension, Asthma…"],
  "current_medications": "string",
  "dental_conditions": ["array like Caries, Missing Teeth, Gum Disease…"],
  "habits": ["array like Smoking, Tobacco (Chewing), Alcohol, Gutkha…"],
  "notes": "any other useful free text, or empty"
}

Rules:
- mobile/phone: digits only, drop spaces, +91, dashes.
- Dates may be DD/MM/YYYY or DD-MM-YYYY — convert to YYYY-MM-DD. If only age is
  given, put it in age_years and leave dob "".
- gender: map M/Male→male, F/Female→female.
- Arrays: list each item as a short string; [] if none ticked/written.
Return the JSON object only.
PROMPT;

        // Engine selection (cloud-when-online vs local) lives in VisionService.
        $result  = $this->vision->read($absolutePath, $prompt);
        $content = trim($result['text']);
        $parsed  = $this->decodeJson($content);

        if ($parsed === null) {
            throw new RuntimeException(
                "Couldn't read the form clearly. Try a sharper, well-lit photo — " .
                "or just fill the patient in manually."
            );
        }

        $out = $this->normalise($parsed);
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
        if (preg_match('/\{.*\}/s', $content, $m)) {
            $data = json_decode($m[0], true);
            if (is_array($data)) {
                return $data;
            }
        }
        return null;
    }

    /** Clean + type-coerce the model's raw fields into safe form values. */
    protected function normalise(array $d): array
    {
        // Gender → allowed set
        $gender = strtolower(trim((string) ($d['gender'] ?? '')));
        $gender = in_array($gender, ['male', 'female', 'other'], true) ? $gender : '';

        // Date must be real YYYY-MM-DD, else drop it
        $dob = trim((string) ($d['dob'] ?? ''));
        if ($dob !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
            $dob = '';
        }

        $age = $d['age_years'] ?? null;
        $age = (is_numeric($age) && $age > 0 && $age < 150) ? (int) $age : null;

        return [
            'title'                          => $this->str($d['title'] ?? ''),
            'first_name'                     => $this->str($d['first_name'] ?? ''),
            'middle_name'                    => $this->str($d['middle_name'] ?? ''),
            'last_name'                      => $this->str($d['last_name'] ?? ''),
            'gender'                         => $gender,
            'dob'                            => $dob,
            'age_years'                      => $age,
            'mobile'                         => $this->phone($d['mobile'] ?? ''),
            'alternate_phone'                => $this->phone($d['alternate_phone'] ?? ''),
            'email'                          => $this->str($d['email'] ?? ''),
            'emergency_contact_name'         => $this->str($d['emergency_contact_name'] ?? ''),
            'emergency_contact_relationship' => $this->str($d['emergency_contact_relationship'] ?? ''),
            'emergency_contact_number'       => $this->phone($d['emergency_contact_number'] ?? ''),
            'address'                        => $this->str($d['address'] ?? ''),
            'area'                           => $this->str($d['area'] ?? ''),
            'city'                           => $this->str($d['city'] ?? ''),
            'pincode'                        => preg_replace('/[^0-9]/', '', (string) ($d['pincode'] ?? '')),
            'medical_conditions'             => $this->arr($d['medical_conditions'] ?? []),
            'current_medications'            => $this->str($d['current_medications'] ?? '', 1000),
            'dental_conditions'              => $this->arr($d['dental_conditions'] ?? []),
            'habits'                         => $this->arr($d['habits'] ?? []),
            'notes'                          => $this->str($d['notes'] ?? '', 1000),
        ];
    }

    /** Trim to a clean string, capping silly lengths. */
    protected function str($v, int $max = 200): string
    {
        return mb_substr(trim((string) $v), 0, $max);
    }

    /** Phone → digits only (keep last 15 to be safe). */
    protected function phone($v): string
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $v);
        return mb_substr($digits, -15);
    }

    /** Normalise an array-of-strings field; drop blanks, cap items. */
    protected function arr($v): array
    {
        if (!is_array($v)) {
            return [];
        }
        return collect($v)
            ->map(fn ($x) => $this->str($x, 100))
            ->filter()
            ->unique()
            ->take(20)
            ->values()
            ->all();
    }
}
