<?php

namespace App\Services\ContentManagement;

use Illuminate\Support\Collection;

class TimelineService
{
    /**
     * Build Day-based timeline from a collection of ClinicalMedia records.
     * Groups by upload_date, sorted chronologically.
     * Each group has: date, day_label, stage, media (collection).
     */
    public function build(Collection $media): Collection
    {
        if ($media->isEmpty()) return collect();

        $firstDate = $media->min('upload_date');

        return $media
            ->groupBy(fn($m) => $m->upload_date?->toDateString() ?? 'unknown')
            ->sortKeys()
            ->map(function (Collection $group, string $date) use ($firstDate) {
                $uploadDate = $group->first()->upload_date;

                // Calculate day number from first upload
                $dayNum = $uploadDate
                    ? (int) \Carbon\Carbon::parse($firstDate)->diffInDays($uploadDate) + 1
                    : 1;

                // Primary stage for the day (most common)
                $primaryStage = $group
                    ->groupBy('treatment_stage')
                    ->sortByDesc(fn($g) => $g->count())
                    ->keys()
                    ->first();

                return [
                    'date'         => $date,
                    'day_label'    => 'Day ' . $dayNum,
                    'day_num'      => $dayNum,
                    'display_date' => $uploadDate ? $uploadDate->format('d M Y') : $date,
                    'stage'        => $primaryStage,
                    'stage_label'  => config('cms.stage_labels.' . $primaryStage, ucfirst($primaryStage ?? 'Unknown')),
                    'media'        => $group->values(),
                    'counts'       => [
                        'photos' => $group->whereIn('media_type', ['photo', 'xray', 'opg', 'cbct', 'scan'])->count(),
                        'videos' => $group->where('media_type', 'video')->count(),
                        'pdfs'   => $group->where('media_type', 'pdf')->count(),
                    ],
                ];
            })
            ->values();
    }

    /**
     * Group media by treatment_stage for the gallery view.
     * Returns ['before' => Collection, 'during' => Collection, ...]
     */
    public function groupByStage(Collection $media): array
    {
        $stages = ['before', 'during', 'after', 'followup'];
        $result = [];

        foreach ($stages as $stage) {
            $result[$stage] = $media->where('treatment_stage', $stage)->values();
        }

        // Anything without a stage goes into a misc bucket
        $result['other'] = $media->whereNotIn('treatment_stage', $stages)
            ->whereNull('treatment_stage')
            ->values();

        return $result;
    }
}
