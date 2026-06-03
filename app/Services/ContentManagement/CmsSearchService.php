<?php

namespace App\Services\ContentManagement;

use App\Models\ClinicalMedia;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CmsSearchService
{
    public function searchCases(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $q = DB::table('clinical_media as cm')
            ->join('patients as p', 'p.id', '=', 'cm.patient_id')
            ->leftJoin('users as u', 'u.id', '=', 'cm.doctor_id')
            ->whereNull('cm.deleted_at')
            ->select([
                'cm.patient_id',
                'cm.treatment_name',
                'cm.tooth_no',
                'p.name as patient_name',
                DB::raw('TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age'),
                'p.gender',
                DB::raw('u.name as doctor_name'),
                DB::raw('MIN(cm.visit_date) as start_date'),
                DB::raw('MAX(cm.visit_date) as last_date'),
                DB::raw('COUNT(cm.id) as media_count'),
                DB::raw("MAX(CASE WHEN cm.treatment_stage = 'after' THEN cm.visit_date END) as completion_date"),
                DB::raw("MAX(CASE WHEN cm.treatment_stage = 'followup' THEN cm.visit_date END) as last_followup_date"),
                DB::raw("GROUP_CONCAT(DISTINCT cm.tags SEPARATOR ',') as all_tags"),
            ])
            ->groupBy('cm.patient_id', 'cm.treatment_name', 'cm.tooth_no', 'p.name', 'p.date_of_birth', 'p.gender', 'u.name');

        if (!empty($filters['patient_id'])) {
            $q->where('cm.patient_id', $filters['patient_id']);
        }
        if (!empty($filters['tooth'])) {
            $q->where('cm.tooth_no', $filters['tooth']);
        }
        if (!empty($filters['treatment'])) {
            $q->where('cm.treatment_name', 'like', '%' . $filters['treatment'] . '%');
        }
        if (!empty($filters['doctor_id'])) {
            $q->where('cm.doctor_id', $filters['doctor_id']);
        }
        if (!empty($filters['date_from'])) {
            $q->where('cm.visit_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $q->where('cm.visit_date', '<=', $filters['date_to']);
        }
        if (!empty($filters['tag'])) {
            $q->where('cm.tags', 'like', '%' . $filters['tag'] . '%');
        }
        if (!empty($filters['q'])) {
            $search = '%' . $filters['q'] . '%';
            $q->where(function ($sub) use ($search) {
                $sub->where('p.name', 'like', $search)
                    ->orWhere('cm.treatment_name', 'like', $search)
                    ->orWhere('cm.tooth_no', 'like', $search);
            });
        }

        $sort    = $filters['sort'] ?? 'start_date_desc';
        $sortMap = [
            'start_date_desc'  => ['MIN(cm.visit_date)', 'desc'],
            'start_date_asc'   => ['MIN(cm.visit_date)', 'asc'],
            'patient_asc'      => ['p.name', 'asc'],
            'media_count_desc' => ['COUNT(cm.id)', 'desc'],
        ];
        [$col, $dir] = $sortMap[$sort] ?? ['MIN(cm.visit_date)', 'desc'];
        $q->orderByRaw("{$col} {$dir}");

        return $q->paginate($perPage)->withQueryString();
    }

    public function getCaseMedia(int $patientId, ?string $treatment, ?string $tooth): Collection
    {
        return ClinicalMedia::where('patient_id', $patientId)
            ->when($treatment, fn($q) => $q->where('treatment_name', $treatment))
            ->when($tooth, fn($q) => $q->where('tooth_no', $tooth))
            ->whereNull('deleted_at')
            ->orderBy('visit_date')
            ->orderBy('created_at')
            ->get();
    }

    public function getTreatmentOptions(): Collection
    {
        return DB::table('clinical_media')
            ->select('treatment_name')
            ->whereNotNull('treatment_name')
            ->whereNull('deleted_at')
            ->distinct()
            ->orderBy('treatment_name')
            ->pluck('treatment_name');
    }

    public function getToothOptions(): array
    {
        return array_merge(range(11, 18), range(21, 28), range(31, 38), range(41, 48));
    }

    public function getTagOptions(): Collection
    {
        return DB::table('clinical_media')
            ->whereNotNull('tags')
            ->whereNull('deleted_at')
            ->pluck('tags')
            ->flatMap(fn($t) => array_map('trim', explode(',', $t)))
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    public function getStats(): array
    {
        return [
            'total_cases'      => DB::table('clinical_media')->whereNull('deleted_at')->distinct()->count(DB::raw('CONCAT(patient_id, treatment_name, tooth_no)')),
            'total_media'      => DB::table('clinical_media')->whereNull('deleted_at')->count(),
            'total_patients'   => DB::table('clinical_media')->whereNull('deleted_at')->distinct('patient_id')->count('patient_id'),
            'treatments_count' => DB::table('clinical_media')->whereNull('deleted_at')->whereNotNull('treatment_name')->distinct('treatment_name')->count('treatment_name'),
        ];
    }
}
