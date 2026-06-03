<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Watermark Settings
    |--------------------------------------------------------------------------
    */
    'watermark' => [
        'clinic_name'          => env('CMS_WATERMARK_CLINIC_NAME', 'Tulip Dental'),
        'include_patient_name' => env('CMS_WATERMARK_PATIENT_NAME', false),
        'position'             => 'bottom-right',  // bottom-right | bottom-left | bottom-center
        'opacity'              => 0.65,
        'font_size'            => 11,
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Paths (relative to disk root)
    |--------------------------------------------------------------------------
    */
    'storage' => [
        'originals'    => 'cms/originals',
        'watermarked'  => 'cms/watermarked',
        'education'    => 'cms/education',
        'thumbnails'   => 'cms/thumbnails',
    ],

    /*
    |--------------------------------------------------------------------------
    | Treatment Stage Labels
    |--------------------------------------------------------------------------
    */
    'stage_labels' => [
        'before'   => 'Before Treatment',
        'during'   => 'During Treatment',
        'after'    => 'After Treatment',
        'followup' => 'Follow-up',
    ],

    'stage_colors' => [
        'before'   => '#2563eb',
        'during'   => '#d97706',
        'after'    => '#16a34a',
        'followup' => '#7c3aed',
    ],

    /*
    |--------------------------------------------------------------------------
    | Media Type Labels
    |--------------------------------------------------------------------------
    */
    'media_type_labels' => [
        'photo'  => 'Photo',
        'xray'   => 'X-Ray',
        'opg'    => 'OPG',
        'cbct'   => 'CBCT',
        'scan'   => 'Scan',
        'video'  => 'Video',
        'pdf'    => 'PDF',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Education Categories (used by seeder)
    |--------------------------------------------------------------------------
    */
    'default_education_categories' => [
        ['name' => 'Restorative',        'slug' => 'restorative',    'icon' => 'tooth-fill',   'color' => '#2563eb', 'sort_order' => 1],
        ['name' => 'Implantology',       'slug' => 'implantology',   'icon' => 'implant',      'color' => '#7c3aed', 'sort_order' => 2],
        ['name' => 'Endodontics',        'slug' => 'endodontics',    'icon' => 'rct',          'color' => '#dc2626', 'sort_order' => 3],
        ['name' => 'Periodontics',       'slug' => 'periodontics',   'icon' => 'gum',          'color' => '#16a34a', 'sort_order' => 4],
        ['name' => 'Orthodontics',       'slug' => 'orthodontics',   'icon' => 'aligner',      'color' => '#0891b2', 'sort_order' => 5],
        ['name' => 'Oral Surgery',       'slug' => 'oral-surgery',   'icon' => 'surgery',      'color' => '#ea580c', 'sort_order' => 6],
        ['name' => 'Preventive',         'slug' => 'preventive',     'icon' => 'shield',       'color' => '#059669', 'sort_order' => 7],
        ['name' => 'Cosmetic Dentistry', 'slug' => 'cosmetic',       'icon' => 'smile',        'color' => '#db2777', 'sort_order' => 8],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */
    'per_page_options' => [10, 25, 50, 100],
    'default_per_page' => 10,

];
