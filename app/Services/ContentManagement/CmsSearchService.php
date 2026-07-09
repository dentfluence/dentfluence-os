<?php

namespace App\Services\ContentManagement;

use App\Models\ClinicalFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Phase 8E — Rewritten to use clinical_files (clinical_media dropped).
 *
 * Column mapping from old clinical_media:
 *   treatment_name → procedure
 *   tooth_no       → tooth_number
 *   doctor_id      → uploaded_by
 *   visit_date     → captured_at
 *   tags           → tags (JSON array, not comma-separated)
 */
class CmsSearchService
{
    /**
     * Paginated "case" view — grouped by patient + procedure + tooth.
     */
    public function searchCases(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $q = DB::table('clinical_files as cm')
            ->join('patients as p', 'p.id', '=', 'cm.patient_id')
            ->leftJoin('users as u', 'u.id', '=', 'cm.uploaded_by')
            ->whereNull('cm.deleted_at')
            ->select([
                'cm.patient_id',
                'cm.procedure as treatment_name',
                'cm.tooth_number as tooth_no',
                'p.name as patient_name',
                DB::raw('TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age'),
                'p.gender',
                DB::raw('u.name as doctor_name'),
                DB::raw('MIN(cm.captured_at) as start_date'),
                DB::raw('MAX(cm.captured_at) as last_date'),
                DB::raw('COUNT(cm.id) as media_count'),
                DB::raw("MAX(CASE WHEN cm.stage = 'after' THEN cm.captured_at END) as completion_date"),
                DB::raw("MAX(CASE WHEN cm.stage = 'followup' THEN cm.captured_at END) as last_followup_date"),
            ])
            ->groupBy('cm.patient_id', 'cm.procedure', 'cm.tooth_number', 'p.name', 'p.date_of_birth', 'p.gender', 'u.name');

        if (!empty($filters['patient_id'])) {
            $q->where('cm.patient_id', $filters['patient_id']);
        }
        if (!empty($filters['tooth'])) {
            $q->where('cm.tooth_number', $filters['tooth']);
        }
        if (!empty($filters['treatment'])) {
            // Filters on the fixed treatment_category vocabulary (exact match), not
            // the free-text procedure column — see ClinicalFile::TREATMENT_CATEGORIES.
            $q->where('cm.treatment_category', $filters['treatment']);
        }
        if (!empty($filters['doctor_id'])) {
            $q->where('cm.uploaded_by', $filters['doctor_id']);
        }
        if (!empty($filters['date_from'])) {
            $q->where('cm.captured_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $q->where('cm.captured_at', '<=', $filters['date_to']);
        }
        if (!empty($filters['tag'])) {
            // tags is a JSON array — use JSON_CONTAINS or a LIKE fallback
            $q->where('cm.tags', 'like', '%' . $filters['tag'] . '%');
        }
        if (!empty($filters['q'])) {
            $search = '%' . $filters['q'] . '%';
            $q->where(function ($sub) use ($search) {
                $sub->where('p.name', 'like', $search)
                    ->orWhere('cm.procedure', 'like', $search)
                    ->orWhere('cm.tooth_number', 'like', $search);
            });
        }

        $sort    = $filters['sort'] ?? 'start_date_desc';
        $sortMap = [
            'start_date_desc'  => ['MIN(cm.captured_at)', 'desc'],
            'start_date_asc'   => ['MIN(cm.captured_at)', 'asc'],
            'patient_asc'      => ['p.name', 'asc'],
            'media_count_desc' => ['COUNT(cm.id)', 'desc'],
        ];
        [$col, $dir] = $sortMap[$sort] ?? ['MIN(cm.captured_at)', 'desc'];
        $q->orderByRaw("{$col} {$dir}");

        return $q->paginate($perPage)->withQueryString();
    }

    /**
     * Individual files for a single case (patient + procedure + tooth).
     */
    public function getCaseMedia(int $patientId, ?string $treatment, ?string $tooth): Collection
    {
        return ClinicalFile::where('patient_id', $patientId)
            ->when($treatment, fn($q) => $q->where('procedure', $treatment))
            ->when($tooth, fn($q) => $q->where('tooth_number', $tooth))
            ->orderBy('captured_at')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Distinct procedure (treatment) names for filter dropdowns.
     * Free-text — kept for the global search box's autocomplete, not the
     * structured "Treatment" filter (see getTreatmentCategoryOptions()).
     */
    public function getTreatmentOptions(): Collection
    {
        return ClinicalFile::query()
            ->distinct()
            ->orderBy('procedure')
            ->pluck('procedure')
            ->filter()
            ->values();
    }

    /**
     * Fixed-vocabulary treatment categories for the "Treatment" filter dropdown.
     * Reliable because every value here is exactly what's stored in
     * clinical_files.treatment_category — unlike free-text procedure matching.
     */
    public function getTreatmentCategoryOptions(): array
    {
        return ClinicalFile::TREATMENT_CATEGORIES;
    }

    public function getToothOptions(): array
    {
        return array_merge(range(11, 18), range(21, 28), range(31, 38), range(41, 48));
    }

    /**
     * Unique tag values across all clinical files.
     * tags is a JSON array column — fetch and flatten all rows.
     */
    public function getTagOptions(): Collection
    {
        return ClinicalFile::query()
            ->whereNotNull('tags')
            ->pluck('tags')                                        // Eloquent decodes JSON → array
            ->flatMap(fn($t) => is_array($t) ? $t : [])
            ->map(fn($tag) => trim((string) $tag))
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    public function getStats(): array
    {
        $base = DB::table('clinical_files')->whereNull('deleted_at');

        return [
            'total_cases'      => (clone $base)->distinct()->count(DB::raw('CONCAT(patient_id, `procedure`, tooth_number)')),
            'total_media'      => (clone $base)->count(),
            'total_patients'   => (clone $base)->distinct('patient_id')->count('patient_id'),
            'treatments_count' => (clone $base)->whereNotNull('procedure')->distinct('procedure')->count('procedure'),
        ];
    }

    /**
     * Full-text search used by the Clinical tab.
     */
    public function search(array $filters): mixed
    {
        return ClinicalFile::with(['patient', 'uploadedBy', 'visit'])
            ->when(!empty($filters['patient_id']), fn($q) => $q->where('patient_id', $filters['patient_id']))
            ->when(!empty($filters['tooth']),      fn($q) => $q->where('tooth_number', $filters['tooth']))
            ->when(!empty($filters['treatment']),  fn($q) => $q->where('treatment_category', $filters['treatment']))
            ->when(!empty($filters['doctor_id']),  fn($q) => $q->where('uploaded_by', $filters['doctor_id']))
            ->when(!empty($filters['tag']),        fn($q) => $q->where('tags', 'like', '%' . $filters['tag'] . '%'))
            ->when(!empty($filters['q']), function ($q) use ($filters) {
                $s = '%' . $filters['q'] . '%';
                $q->where(fn($sub) =>
                    $sub->whereHas('patient', fn($p) => $p->where('name', 'like', $s))
                        ->orWhere('procedure', 'like', $s)
                        ->orWhere('title', 'like', $s)
                );
            })
            ->latest('captured_at')
            ->paginate(24)
            ->withQueryString();
    }
}
