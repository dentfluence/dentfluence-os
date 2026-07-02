<?php

namespace App\Services\ContentManagement;

use App\Models\CmsTag;

class TagService
{
    // Auto-suggest tags based on treatment + tooth + visit type
    public function suggestTags(
        ?string $treatmentName,
        ?string $toothNo,
        ?string $visitType,
        ?string $stage
    ): array {
        $tags = [];

        if ($treatmentName) {
            $tags[] = ['name' => $treatmentName, 'type' => 'treatment'];
        }

        if ($toothNo) {
            foreach (explode(',', $toothNo) as $t) {
                $t = trim($t);
                if ($t) {
                    $tags[] = ['name' => $t, 'type' => 'tooth'];
                }
            }
        }

        if ($stage) {
            $tags[] = ['name' => $this->stageLabel($stage), 'type' => 'stage'];
        }

        if ($visitType) {
            $tags[] = ['name' => ucfirst($visitType), 'type' => 'general'];
        }

        return $tags;
    }

    // Resolve tag names to IDs (create if not exists)
    public function resolveTags(array $tagNames): array
    {
        $resolved = [];
        foreach ($tagNames as $name) {
            $tag = CmsTag::firstOrCreate(
                ['name' => trim($name)],
                ['type' => 'general', 'color' => '#6b7280']
            );
            $tag->incrementUsage();
            $resolved[] = $tag->name;
        }
        return $resolved;
    }

    // Popular tags for filter dropdown
    public function popularTags(int $limit = 20): array
    {
        return CmsTag::orderByDesc('usage_count')
            ->limit($limit)
            ->get(['name', 'type', 'color'])
            ->toArray();
    }

    private function stageLabel(string $stage): string
    {
        return match($stage) {
            'before_treatment'  => 'Before Treatment',
            'during_treatment'  => 'During Treatment',
            'after_treatment'   => 'After Treatment',
            'follow_up'         => 'Follow-up',
            default             => 'General',
        };
    }
}
