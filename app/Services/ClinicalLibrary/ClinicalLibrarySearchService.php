<?php

namespace App\Services\ClinicalLibrary;

use App\Models\ClinicalFile;
use App\Models\TreatmentPlanItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * ClinicalLibrarySearchService — ONE place that answers "find me that file".
 *
 * It backs a single endpoint which drives BOTH the Clinical Library dashboard's
 * search drawer AND the Content Manager's filter bar. That is deliberate: those
 * two surfaces used to each carry their own query logic and they disagreed —
 * the Content Manager's Treatment dropdown matched a hardcoded specialty list
 * against free-text `procedure` while the page beside it filtered on
 * `treatment_category`. Two front-ends, one backend, so they cannot drift.
 *
 * The search box takes one line of plain text and works out what each word is,
 * rather than making a dentist choose a field first:
 *
 *   "26"                   → every file on tooth 26
 *   "Sharma implant after" → that patient's implant after-photos
 *   "opg 36 pending"       → OPGs on tooth 36 whose treatment is not yet done
 *
 * A word is read as a tooth, a stage, a file type, a treatment category or a
 * treatment status when it unambiguously is one; everything left over is
 * matched as text against the patient, the procedure, the title, the notes and
 * the tags. What it decided is returned in `interpreted` so the UI can show it
 * back as removable chips — a search that silently reinterprets what you typed
 * is worse than one that asks.
 */
class ClinicalLibrarySearchService
{
    /**
     * FDI notation. A bare number in this set is a tooth, not a quantity.
     * Permanent 11-48, deciduous 51-85.
     */
    private const FDI_TEETH = [
        11, 12, 13, 14, 15, 16, 17, 18,  21, 22, 23, 24, 25, 26, 27, 28,
        31, 32, 33, 34, 35, 36, 37, 38,  41, 42, 43, 44, 45, 46, 47, 48,
        51, 52, 53, 54, 55,  61, 62, 63, 64, 65,
        71, 72, 73, 74, 75,  81, 82, 83, 84, 85,
    ];

    /**
     * Tooth regions, the way the chart in the rest of the app is laid out.
     * A dentist thinks "upper arch", not "11,12,13,14,15,16,17,18,21,…", and
     * a full-arch case is filed tooth by tooth — so the filter has to speak
     * both. Deciduous codes are included because Tulip Kids uses the same
     * library; leaving them out would quietly hide every paediatric file.
     */
    public const TOOTH_REGIONS = [
        'full_mouth'  => 'Full mouth (any tooth)',
        'maxillary'   => 'Maxillary arch (upper)',
        'mandibular'  => 'Mandibular arch (lower)',
        'q1'          => 'Upper right',
        'q2'          => 'Upper left',
        'q3'          => 'Lower left',
        'q4'          => 'Lower right',
    ];

    private const REGION_TEETH = [
        'q1' => [18, 17, 16, 15, 14, 13, 12, 11, 55, 54, 53, 52, 51],
        'q2' => [21, 22, 23, 24, 25, 26, 27, 28, 61, 62, 63, 64, 65],
        'q3' => [31, 32, 33, 34, 35, 36, 37, 38, 71, 72, 73, 74, 75],
        'q4' => [41, 42, 43, 44, 45, 46, 47, 48, 81, 82, 83, 84, 85],
    ];

    /** Words a clinician actually types for a stage. */
    private const STAGE_ALIASES = [
        'before' => 'before', 'pre' => 'before', 'preop' => 'before', 'pre-op' => 'before',
        'during' => 'during', 'intra' => 'during', 'intraop' => 'during',
        'after' => 'after', 'post' => 'after', 'postop' => 'after', 'post-op' => 'after', 'final' => 'after',
        'followup' => 'followup', 'follow-up' => 'followup', 'recall' => 'followup', 'review' => 'followup',
        'general' => 'general',
    ];

    /** Words for a file type. */
    private const FILE_TYPE_ALIASES = [
        'photo' => 'photo', 'photos' => 'photo', 'pic' => 'photo', 'pics' => 'photo', 'image' => 'photo',
        'xray' => 'xray', 'x-ray' => 'xray', 'iopa' => 'xray', 'rvg' => 'xray',
        'opg' => 'opg', 'panoramic' => 'opg',
        'cbct' => 'cbct', 'dicom' => 'cbct', 'ct' => 'cbct',
        'stl' => 'stl',
        'scan' => 'intraoral_scan', 'iosscan' => 'intraoral_scan', 'ios' => 'intraoral_scan',
        'pdf' => 'pdf', 'consent' => 'consent', 'estimate' => 'estimate',
        'invoice' => 'invoice', 'bill' => 'invoice',
        'lab' => 'lab_slip', 'labslip' => 'lab_slip',
        'video' => 'video',
    ];

    /** Treatment status — reads TreatmentPlanItem::billing_progress. */
    private const STATUS_ALIASES = [
        'pending'     => TreatmentPlanItem::PROGRESS_PENDING,
        'ongoing'     => TreatmentPlanItem::PROGRESS_PARTIAL,
        'partial'     => TreatmentPlanItem::PROGRESS_PARTIAL,
        'incomplete'  => TreatmentPlanItem::PROGRESS_PARTIAL,
        'completed'   => TreatmentPlanItem::PROGRESS_COMPLETED,
        'complete'    => TreatmentPlanItem::PROGRESS_COMPLETED,
        'done'        => TreatmentPlanItem::PROGRESS_COMPLETED,
        'invoiced'    => TreatmentPlanItem::PROGRESS_INVOICED,
        'billed'      => TreatmentPlanItem::PROGRESS_INVOICED,
    ];

    /**
     * Work out what each word in the search box is.
     *
     * @return array{tooth:array,stage:array,file_type:array,treatment_category:array,status:array,text:array}
     */
    public function parse(?string $q): array
    {
        $out = [
            'tooth' => [], 'stage' => [], 'file_type' => [],
            'treatment_category' => [], 'status' => [], 'text' => [],
        ];

        foreach (preg_split('/[\s,]+/', trim((string) $q), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
            $word = strtolower($token);

            if (ctype_digit($word) && in_array((int) $word, self::FDI_TEETH, true)) {
                $out['tooth'][] = $word;
                continue;
            }

            if (isset(self::STAGE_ALIASES[$word])) {
                $out['stage'][] = self::STAGE_ALIASES[$word];
                continue;
            }

            if (isset(self::FILE_TYPE_ALIASES[$word])) {
                $out['file_type'][] = self::FILE_TYPE_ALIASES[$word];
                continue;
            }

            if ($category = $this->matchTreatmentCategory($word)) {
                $out['treatment_category'][] = $category;
                continue;
            }

            if (isset(self::STATUS_ALIASES[$word])) {
                $out['status'][] = self::STATUS_ALIASES[$word];
                continue;
            }

            $out['text'][] = $token;
        }

        return array_map(fn ($values) => array_values(array_unique($values)), $out);
    }

    /**
     * Run the search. Explicit filters from the UI are merged with whatever the
     * free-text box was understood to mean — a chip the user set always applies.
     *
     * @param  array<string,mixed>  $input
     */
    public function search(array $input): LengthAwarePaginator
    {
        $parsed = $this->parse($input['q'] ?? null);

        $teeth      = $this->merge($parsed['tooth'], $input['tooth'] ?? null);
        $stages     = $this->merge($parsed['stage'], $input['stage'] ?? null);
        $fileTypes  = $this->merge($parsed['file_type'], $input['file_type'] ?? null);
        $categories = $this->merge($parsed['treatment_category'], $input['treatment_category'] ?? $input['treatment'] ?? null);
        $statuses   = $this->merge($parsed['status'], $input['status'] ?? null);

        $query = ClinicalFile::query()->with(['patient:id,name', 'uploadedBy:id,name']);

        // A lab case files one row for the WHOLE case, so tooth_number can read
        // "36, 37" — an exact whereIn would never find it. FIND_IN_SET against
        // the de-spaced value matches a single tooth and a list identically,
        // without the false positives a LIKE '%36%' would bring.
        if ($teeth) {
            $teeth = self::expandTeeth($teeth);

            $query->where(function (Builder $sub) use ($teeth) {
                foreach ($teeth as $tooth) {
                    $sub->orWhereRaw("FIND_IN_SET(?, REPLACE(COALESCE(tooth_number, ''), ' ', ''))", [$tooth]);
                }
            });
        }
        if ($stages)     { $query->whereIn('stage', $stages); }
        if ($fileTypes)  { $query->whereIn('file_type', $fileTypes); }
        if ($categories) { $query->whereIn('treatment_category', $categories); }

        // Treatment status lives on the plan item this file is attached to.
        // A file with no plan item has no status, so it is correctly excluded
        // when a status is asked for rather than silently included.
        if ($statuses) {
            $query->whereHas('treatmentPlanItem', fn (Builder $item) => $item->whereIn('billing_progress', $statuses));
        }

        // Every leftover word must match SOMETHING (AND across words, OR across
        // columns) — "sharma implant" should not return every Sharma.
        foreach ($parsed['text'] as $word) {
            $like = '%' . $word . '%';

            $query->where(function (Builder $sub) use ($like) {
                $sub->where('procedure', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhere('tags', 'like', $like)
                    ->orWhereHas('patient', fn (Builder $p) => $p
                        ->where('name', 'like', $like)
                        ->orWhere('phone', 'like', $like));
            });
        }

        if (! empty($input['patient_id']))  { $query->where('patient_id', $input['patient_id']); }
        if (! empty($input['doctor_id']))   { $query->where('uploaded_by', $input['doctor_id']); }
        if (! empty($input['tag']))         { $query->whereJsonContains('tags', $input['tag']); }
        if (! empty($input['consent']))     { $query->where('consent_status', $input['consent']); }
        if (! empty($input['marketing']))   { $query->where('marketing_status', $input['marketing']); }
        if (! empty($input['from']))        { $query->where('captured_at', '>=', $input['from']); }
        if (! empty($input['to']))          { $query->where('captured_at', '<=', $input['to'] . ' 23:59:59'); }

        foreach (['marketing', 'education', 'teaching', 'research', 'case_library'] as $lane) {
            if (! empty($input['eligible_' . $lane])) {
                $query->where('is_' . $lane . '_eligible', true);
            }
        }

        $perPage = min(96, max(1, (int) ($input['per_page'] ?? 48)));

        return $query->latest('captured_at')->paginate($perPage)->withQueryString();
    }

    /**
     * What the search box was understood to mean, ready to render as chips.
     *
     * @return array<int,array{type:string,value:string,label:string}>
     */
    public function interpretation(?string $q): array
    {
        $parsed = $this->parse($q);
        $chips  = [];

        foreach ($parsed['tooth'] as $tooth) {
            $chips[] = ['type' => 'tooth', 'value' => $tooth, 'label' => 'Tooth ' . $tooth];
        }
        foreach ($parsed['treatment_category'] as $category) {
            $chips[] = ['type' => 'treatment', 'value' => $category, 'label' => ClinicalFile::TREATMENT_CATEGORIES[$category] ?? $category];
        }
        foreach ($parsed['stage'] as $stage) {
            $chips[] = ['type' => 'stage', 'value' => $stage, 'label' => ucfirst($stage)];
        }
        foreach ($parsed['file_type'] as $type) {
            $chips[] = ['type' => 'file_type', 'value' => $type, 'label' => ucfirst(str_replace('_', ' ', $type))];
        }
        foreach ($parsed['status'] as $status) {
            $chips[] = ['type' => 'status', 'value' => $status, 'label' => ucfirst(str_replace('_', ' ', $status))];
        }
        foreach ($parsed['text'] as $word) {
            $chips[] = ['type' => 'text', 'value' => $word, 'label' => '"' . $word . '"'];
        }

        return $chips;
    }

    /**
     * Turn whatever the tooth filter sent — a region key, a single tooth, or a
     * mix — into the list of tooth numbers to match.
     *
     * @param  array<int,string>  $values
     * @return array<int,string>
     */
    public static function expandTeeth(array $values): array
    {
        $teeth = [];

        foreach ($values as $value) {
            $value = strtolower(trim((string) $value));

            if ($value === '') {
                continue;
            }

            $teeth = match (true) {
                $value === 'full_mouth'  => array_merge($teeth, self::FDI_TEETH),
                $value === 'maxillary'   => array_merge($teeth, self::REGION_TEETH['q1'], self::REGION_TEETH['q2']),
                $value === 'mandibular'  => array_merge($teeth, self::REGION_TEETH['q3'], self::REGION_TEETH['q4']),
                isset(self::REGION_TEETH[$value]) => array_merge($teeth, self::REGION_TEETH[$value]),
                default                  => array_merge($teeth, [$value]),
            };
        }

        return array_values(array_unique(array_map('strval', $teeth)));
    }

    /**
     * Options for the tooth dropdown: regions first, then every tooth grouped
     * by quadrant. One list, used by the dashboard drawer and the Content
     * Manager filter bar, so the two cannot offer different teeth.
     *
     * @return array{regions:array<string,string>,quadrants:array<string,array<int,int>>}
     */
    public static function toothOptions(): array
    {
        return [
            'regions'   => self::TOOTH_REGIONS,
            'quadrants' => [
                'Upper right' => self::REGION_TEETH['q1'],
                'Upper left'  => self::REGION_TEETH['q2'],
                'Lower left'  => self::REGION_TEETH['q3'],
                'Lower right' => self::REGION_TEETH['q4'],
            ],
        ];
    }

    /** Match a word against the fixed treatment vocabulary, by key or by label. */
    private function matchTreatmentCategory(string $word): ?string
    {
        foreach (ClinicalFile::TREATMENT_CATEGORIES as $key => $label) {
            if ($word === $key || $word === strtolower($label)) {
                return $key;
            }
        }

        // "aligners" / "braces" / "veneers" — plurals people actually type.
        $singular = rtrim($word, 's');

        foreach (ClinicalFile::TREATMENT_CATEGORIES as $key => $label) {
            if ($singular === $key || $singular === strtolower($label)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Combine what the box was understood to mean with what the user explicitly
     * picked — and when both speak to the same dimension, THE EXPLICIT PICK WINS.
     *
     * Merging them would widen instead of narrow: type "after", then set the
     * Stage filter to Before, and a union returns both, which is the opposite of
     * what setting a filter means. Every filter is optional, but each one that
     * IS set must only ever reduce the result.
     */
    private function merge(array $fromText, mixed $explicit): array
    {
        $explicit = is_array($explicit)
            ? array_values(array_filter($explicit, fn ($v) => $v !== null && $v !== ''))
            : (blank($explicit) ? [] : [$explicit]);

        if ($explicit !== []) {
            return array_values(array_unique($explicit));
        }

        return array_values(array_unique($fromText));
    }
}
