<?php

namespace Database\Seeders;

use App\Models\CmsEduCategory;
use App\Models\CmsEduItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CmsEduSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Restorative',      'color' => '#2563eb', 'items' => 24],
            ['name' => 'Implantology',     'color' => '#6a0f70', 'items' => 18],
            ['name' => 'Endodontics',      'color' => '#dc2626', 'items' => 16],
            ['name' => 'Periodontics',     'color' => '#16a34a', 'items' => 12],
            ['name' => 'Orthodontics',     'color' => '#d97706', 'items' => 14],
            ['name' => 'Oral Surgery',     'color' => '#374151', 'items' => 10],
            ['name' => 'Preventive',       'color' => '#0891b2', 'items' =>  8],
            ['name' => 'Cosmetic',         'color' => '#db2777', 'items' => 11],
        ];

        $sampleItems = [
            ['Implantology', 'Dental Implant Placement', 'Step by step implant placement procedure with clinical tips.', 'video', 405, 28, 6, 3],
            ['Restorative',  'Porcelain Crown',          'Before and after cases of porcelain crown restorations.',     'photo', null, 18, 4, 2],
            ['Endodontics',  'Root Canal Treatment',     'Endodontic treatment cases with pre-op and post-op radiographs.', 'xray', null, 12, 24, 4],
            ['Orthodontics', 'Orthodontic Treatment',    'Orthodontic alignment process and mechanics.',                'video', 330, 20, 10, 5],
            ['Cosmetic',     'Teeth Whitening',          'Clinical cases of in-office and at-home teeth whitening.',    'photo', null, 16,  0, 1],
            ['Oral Surgery', 'Wisdom Tooth Extraction',  'Impacted wisdom tooth cases with radiographic evaluation.',   'xray', null,  8, 16, 2],
            ['Periodontics', 'Scaling & Root Planing',   'Step by step periodontal therapy procedure.',                 'video', 255, 22,  0, 2],
            ['Cosmetic',     'Porcelain Veneers',        'Smile makeover cases with porcelain veneers.',                'photo', null, 14,  2, 1],
        ];

        foreach ($categories as $idx => $catData) {
            CmsEduCategory::firstOrCreate(
                ['slug' => Str::slug($catData['name'])],
                [
                    'name'       => $catData['name'],
                    'color'      => $catData['color'],
                    'sort_order' => $idx,
                    'is_active'  => true,
                ]
            );
        }

        foreach ($sampleItems as [$catName, $title, $desc, $type, $dur, $photos, $xrays, $videos]) {
            $cat = CmsEduCategory::where('name', $catName)->first();
            if (! $cat) continue;

            \App\Models\CmsEduItem::firstOrCreate(
                ['slug' => Str::slug($title)],
                [
                    'category_id'      => $cat->id,
                    'title'            => $title,
                    'description'      => $desc,
                    'media_type'       => $type,
                    'file_path'        => 'edu/placeholder.jpg',
                    'thumbnail_path'   => null,
                    'duration_seconds' => $dur,
                    'photo_count'      => $photos,
                    'xray_count'       => $xrays,
                    'video_count'      => $videos,
                    'is_active'        => true,
                    'sort_order'       => 0,
                ]
            );
        }
    }
}
