<?php

namespace Database\Seeders;

use App\Models\KbBlock;
use App\Models\KbBlockMedia;
use App\Models\KbTopic;
use App\Models\MediaAsset;
use Illuminate\Database\Seeder;

/**
 * MediaLibrarySeeder — Case Acceptance Engine, Milestone 4.
 * See docs/plan-case-acceptance-engine.md §5.2 / §11.
 *
 * Seeds GLOBAL placeholder assets and links them to Knowledge Bank blocks so
 * the engine (Milestone 6 assembler) can be built and tested against real
 * pivots before finished media exists. Real artwork replaces these same rows
 * later (keyed on `path`, so re-running is idempotent).
 *
 * All assets here are scope=global (Dentfluence stock). Clinic/PHI captures
 * (before/after photos) are NEVER seeded — they are consent-gated at runtime.
 *
 * Run AFTER KnowledgeBankSeeder:
 *   php artisan db:seed --class=MediaLibrarySeeder
 */
class MediaLibrarySeeder extends Seeder
{
    /** Placeholder hero image per topic slug. */
    private const HEROES = [
        'missing-tooth'              => 'Missing tooth — overview',
        'dental-implant'             => 'Dental implant — cutaway',
        'dental-bridge'              => 'Dental bridge — 3-unit',
        'removable-partial-denture'  => 'Removable partial denture',
        'crown-zirconia'             => 'Zirconia crown',
        'crown-pfm'                  => 'PFM crown',
        'crown-emax'                 => 'E-max crown',
    ];

    public function run(): void
    {
        // ── Hero image per topic → linked as PRIMARY to that topic's intro ──
        foreach (self::HEROES as $slug => $label) {
            $topic = KbTopic::where('slug', $slug)->first();
            if (! $topic) {
                continue;
            }

            $asset = $this->placeholder(
                path: "media/case-acceptance/placeholders/{$slug}-hero.svg",
                type: 'image',
            );

            $intro = KbBlock::where('kb_topic_id', $topic->id)
                ->where('block_type', 'intro')
                ->first();

            if ($intro) {
                $this->link($intro->id, $asset->id, 'primary', 0);
            }
        }

        // ── Shared healing-timeline animation → linked INLINE where relevant ──
        $healingAnim = $this->placeholder(
            path: 'media/case-acceptance/placeholders/healing-timeline.json',
            type: 'lottie',
        );

        $healingBlocks = KbBlock::where('block_type', 'healing_timeline')->get();
        foreach ($healingBlocks as $block) {
            $this->link($block->id, $healingAnim->id, 'inline', 0);
        }
    }

    private function placeholder(string $path, string $type): MediaAsset
    {
        return MediaAsset::updateOrCreate(
            ['path' => $path],
            [
                'scope'      => 'global',
                'media_type' => $type,
                'mime'       => $type === 'lottie' ? 'application/json' : 'image/svg+xml',
                'locale'     => null,
            ]
        );
    }

    private function link(int $blockId, int $assetId, string $role, int $sort): void
    {
        KbBlockMedia::updateOrCreate(
            ['kb_block_id' => $blockId, 'media_asset_id' => $assetId, 'role' => $role],
            ['sort_order' => $sort]
        );
    }
}
