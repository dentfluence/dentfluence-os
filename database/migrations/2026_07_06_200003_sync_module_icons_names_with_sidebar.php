<?php

use App\Models\Module;
use Illuminate\Database\Migrations\Migration;

/**
 * Several Module rows (used by the Roles & Permissions grid) had truncated
 * SVG icon paths — missing closing arcs/segments — that rendered as broken
 * glyphs (confirmed via screenshot 2026-07-06: CMS, Finance, Inventory, Lab,
 * Reports, Analytics, Settings, Patients all showed incomplete icons).
 *
 * This brings name, icon and sort_order for every module in sync with the
 * real sidebar (resources/views/components/sidebar.blade.php), so the two
 * screens agree on what things are called and in what order they appear:
 * Overview → Clinical → Communication → Operations → Insights → System.
 *
 * Safe to re-run: only updates existing rows by slug, creates nothing,
 * deletes nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        $updates = [
            'dashboard'          => ['sort_order' => 1],
            'daily_huddle'       => ['sort_order' => 2],
            'patients'           => [
                'icon'       => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
                'sort_order' => 3,
            ],
            'appointments'       => [
                'icon'       => '<rect x="3" y="4" width="18" height="18" rx="0"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
                'sort_order' => 4,
            ],
            'treatments'         => ['sort_order' => 5],
            'cms'                => [
                'name'       => 'Clinical Library',
                'icon'       => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/><line x1="12" y1="6" x2="16" y2="6"/><line x1="12" y1="10" x2="16" y2="10"/>',
                'section'    => 'clinical',
                'sort_order' => 6,
            ],
            'communication'      => ['section' => 'communication', 'sort_order' => 7],
            'marketing'          => ['section' => 'communication', 'sort_order' => 8],
            'finance'            => [
                'name'       => 'Accounts & Finance',
                'icon'       => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
                'sort_order' => 9,
            ],
            'inventory'          => [
                'icon'       => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>',
                'sort_order' => 10,
            ],
            'lab'                => [
                'icon'       => '<path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2v-4M9 21H5a2 2 0 0 1-2-2v-4m0 0h18"/>',
                'sort_order' => 11,
            ],
            'tasks'              => [
                'icon'       => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
                'sort_order' => 12,
            ],
            'practice_protocols' => ['sort_order' => 13],
            'hr'                 => ['sort_order' => 14],
            'reports'            => [
                'icon'       => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
                'sort_order' => 15,
            ],
            'analytics'          => [
                'icon'       => '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>',
                'sort_order' => 16,
            ],
            'settings'           => [
                'icon'       => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
                'sort_order' => 17,
            ],
        ];

        foreach ($updates as $slug => $fields) {
            Module::where('slug', $slug)->update($fields);
        }
    }

    public function down(): void
    {
        // Intentionally a no-op — cosmetic/data sync, not a reversible schema change.
    }
};
