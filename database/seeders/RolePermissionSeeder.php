<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Module;
use App\Models\RoleModulePermission;
use App\Models\User;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Modules ─────────────────────────────────────────────────────────
        // Names, icons and ordering are kept identical to the real sidebar
        // (resources/views/components/sidebar.blade.php) — several of these
        // were previously truncated SVG paths that rendered as broken glyphs
        // on the Roles & Permissions screen (2026-07-06 fix).
        $modules = [
            // section => [ [name, slug, icon, sort_order], ... ]
            'overview'    => [
                ['Dashboard',     'dashboard',     '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>', 1],
                ['Daily Huddle',  'daily_huddle',  '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 11l-4 4-4-4"/>', 2],
            ],
            'clinical'    => [
                ['Patients',      'patients',      '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>', 3],
                ['Appointments',  'appointments',  '<rect x="3" y="4" width="18" height="18" rx="0"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>', 4],
                ['Treatments',    'treatments',    '<path d="M12 22 C12 22 5 17 5 11 C5 7 7.5 4 12 4 C16.5 4 19 7 19 11 C19 17 12 22 12 22Z"/>', 5],
                ['Clinical Library', 'cms',        '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/><line x1="12" y1="6" x2="16" y2="6"/><line x1="12" y1="10" x2="16" y2="10"/>', 6],
            ],
            'communication' => [
                // PRM was retired in Phase 8 (2026-07-03) — routes/prm.php removed,
                // every lead-pipeline write now goes through PRE. Communication OS
                // (routes/communication.php) is the live module here instead.
                // Relationships (PRE) itself is deliberately ungated — see
                // components/sidebar.blade.php — so it has no row here, same as
                // Prescriptions doesn't either.
                ['Communication (PRE)', 'communication', '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>', 7],
                ['Marketing',     'marketing',     '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>', 8],
            ],
            'operations'  => [
                ['Accounts & Finance', 'finance',  '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>', 9],
                ['Inventory',     'inventory',     '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>', 10],
                ['Lab',           'lab',           '<path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2v-4M9 21H5a2 2 0 0 1-2-2v-4m0 0h18"/>', 11],
                ['Tasks',         'tasks',         '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>', 12],
                ['Practice Protocols', 'practice_protocols', '<path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 12h6"/><path d="M9 16h6"/>', 13],
                ['HR',            'hr',            '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>', 14],
            ],
            'insights'    => [
                ['Reports',       'reports',       '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>', 15],
                ['Analytics',     'analytics',     '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>', 16],
            ],
            'system'      => [
                ['Settings',      'settings',      '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>', 17],
            ],
        ];

        $moduleMap = []; // slug => Module

        foreach ($modules as $section => $items) {
            foreach ($items as [$name, $slug, $icon, $sort]) {
                $moduleMap[$slug] = Module::updateOrCreate(
                    ['slug' => $slug],
                    ['name' => $name, 'icon' => $icon, 'section' => $section, 'sort_order' => $sort]
                );
            }
        }

        // ── 2. Roles ───────────────────────────────────────────────────────────
        $roleData = [
            ['name' => 'Admin',       'slug' => Role::ADMIN,      'category' => Role::CATEGORY_STAFF,  'color' => '#6a0f70', 'description' => 'Full access to all modules and settings.'],
            ['name' => 'Manager',     'slug' => Role::MANAGER,    'category' => Role::CATEGORY_STAFF,  'color' => '#1a5ea8', 'description' => 'Manages clinic operations. No dashboard or settings.'],
            ['name' => 'Doctor',      'slug' => Role::DOCTOR,     'category' => Role::CATEGORY_DOCTOR, 'color' => '#1a7a45', 'description' => 'Clinical access — patients, treatments, appointments.'],
            ['name' => 'Assistant',   'slug' => Role::ASSISTANT,  'category' => Role::CATEGORY_STAFF,  'color' => '#a05c00', 'description' => 'View-only: appointments and patients.'],
            ['name' => 'Front Desk',  'slug' => Role::FRONT_DESK, 'category' => Role::CATEGORY_STAFF,  'color' => '#c0392b', 'description' => 'Appointments, patients, billing.'],
            ['name' => 'Accounts',    'slug' => Role::ACCOUNTS,   'category' => Role::CATEGORY_STAFF,  'color' => '#2c3e50', 'description' => 'Finance and billing only.'],
        ];

        $roles = [];
        foreach ($roleData as $data) {
            $roles[$data['slug']] = Role::updateOrCreate(
                ['slug' => $data['slug']],
                array_merge($data, ['is_system' => true])
            );
        }

        // ── 3. Default permission matrix ───────────────────────────────────────
        // Format: 'module_slug' => [view, edit, delete]
        $matrix = [
            Role::ADMIN => [
                // Admin gets everything
                'dashboard'   => [1,1,1], 'daily_huddle' => [1,1,1],
                'patients'    => [1,1,1], 'appointments' => [1,1,1],
                'treatments'  => [1,1,1], 'cms'          => [1,1,1],
                'communication' => [1,1,1], 'finance'    => [1,1,1],
                'inventory'   => [1,1,1], 'lab'          => [1,1,1],
                'tasks'       => [1,1,1], 'marketing'    => [1,1,1],
                'hr'          => [1,1,1],
                'practice_protocols' => [1,1,1],
                'reports'     => [1,1,1], 'analytics'    => [1,1,1],
                'settings'    => [1,1,1],
            ],
            Role::MANAGER => [
                'daily_huddle' => [1,1,0], 'patients'    => [1,1,0],
                'appointments' => [1,1,0], 'treatments'  => [1,1,0],
                'communication' => [1,1,0], 'finance'    => [1,1,0],
                'inventory'    => [1,1,0], 'lab'         => [1,1,0],
                'tasks'        => [1,1,0], 'marketing'   => [1,1,0],
                'hr'           => [1,1,0],
                'cms'          => [1,1,0],
                'practice_protocols' => [1,1,1],
                'reports'      => [1,0,0],
            ],
            Role::DOCTOR => [
                'daily_huddle' => [1,1,0], 'patients'   => [1,1,0],
                'appointments' => [1,1,0], 'treatments' => [1,1,1],
                'cms'          => [1,0,0], 'lab'        => [1,1,0],
                'tasks'        => [1,1,0],
                'hr'           => [1,1,0], 'communication' => [1,1,0],
            ],
            Role::ASSISTANT => [
                'appointments' => [1,0,0], 'patients' => [1,0,0],
                'tasks'        => [1,0,0],
                'hr'           => [1,1,0], 'cms'      => [1,0,0],
                'communication' => [1,1,0],
            ],
            Role::FRONT_DESK => [
                'appointments' => [1,1,0], 'patients' => [1,1,0],
                'finance'      => [1,1,0], 'tasks'    => [1,0,0],
                'communication' => [1,1,0],
                'hr'           => [1,1,0], 'cms'      => [1,1,0],
            ],
            Role::ACCOUNTS => [
                'finance'  => [1,1,0], 'reports' => [1,0,0],
                'patients' => [1,0,0],
                'hr'       => [1,1,0], 'cms'     => [1,0,0],
                'communication' => [1,1,0],
            ],
        ];

        foreach ($matrix as $roleSlug => $perms) {
            $role = $roles[$roleSlug];
            foreach ($perms as $moduleSlug => [$view, $edit, $delete]) {
                if (! isset($moduleMap[$moduleSlug])) continue;
                RoleModulePermission::updateOrCreate(
                    ['role_id' => $role->id, 'module_id' => $moduleMap[$moduleSlug]->id],
                    ['can_view' => $view, 'can_edit' => $edit, 'can_delete' => $delete]
                );
            }
        }

        // ── 4. Assign admin role to existing users who have role = 'admin' ────
        $adminRole = $roles[Role::ADMIN];
        User::where('role', 'admin')->whereNull('role_id')->update(['role_id' => $adminRole->id]);

        $this->command->info('✅ Roles, modules, and permissions seeded.');
    }
}
