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
        $modules = [
            // section => [ [name, slug], ... ]
            'overview'    => [
                ['Dashboard',     'dashboard',     '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>', 1],
                ['Daily Huddle',  'daily_huddle',  '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 11l-4 4-4-4"/>', 2],
            ],
            'clinical'    => [
                ['Patients',      'patients',      '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>', 3],
                ['Appointments',  'appointments',  '<rect x="3" y="4" width="18" height="18"/><line x1="3" y1="10" x2="21" y2="10"/>', 4],
                ['Treatments',    'treatments',    '<path d="M12 22C12 22 5 17 5 11C5 7 7.5 4 12 4C16.5 4 19 7 19 11C19 17 12 22 12 22Z"/>', 5],
                ['CMS',           'cms',           '<path d="M14 2H6a2 2 0 0 0-2 2v16"/>', 6],
            ],
            'communication' => [
                ['PRM',           'prm',           '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>', 7],
            ],
            'operations'  => [
                ['Finance',       'finance',       '<line x1="12" y1="1" x2="12" y2="23"/>', 8],
                ['Inventory',     'inventory',     '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8"/>', 9],
                ['Lab',           'lab',           '<path d="M9 3H5a2 2 0 0 0-2 2v4"/>', 10],
                ['Tasks',         'tasks',         '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5"/>', 11],
                ['Marketing',     'marketing',     '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>', 12],
            ],
            'insights'    => [
                ['Reports',       'reports',       '<line x1="18" y1="20" x2="18" y2="10"/>', 13],
                ['Analytics',     'analytics',     '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/>', 14],
            ],
            'system'      => [
                ['Settings',      'settings',      '<circle cx="12" cy="12" r="3"/>', 15],
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
            ['name' => 'Admin',       'slug' => Role::ADMIN,      'color' => '#6a0f70', 'description' => 'Full access to all modules and settings.'],
            ['name' => 'Manager',     'slug' => Role::MANAGER,    'color' => '#1a5ea8', 'description' => 'Manages clinic operations. No dashboard or settings.'],
            ['name' => 'Doctor',      'slug' => Role::DOCTOR,     'color' => '#1a7a45', 'description' => 'Clinical access — patients, treatments, appointments.'],
            ['name' => 'Assistant',   'slug' => Role::ASSISTANT,  'color' => '#a05c00', 'description' => 'View-only: appointments and patients.'],
            ['name' => 'Front Desk',  'slug' => Role::FRONT_DESK, 'color' => '#c0392b', 'description' => 'Appointments, patients, billing.'],
            ['name' => 'Accounts',    'slug' => Role::ACCOUNTS,   'color' => '#2c3e50', 'description' => 'Finance and billing only.'],
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
                'prm'         => [1,1,1], 'finance'      => [1,1,1],
                'inventory'   => [1,1,1], 'lab'          => [1,1,1],
                'tasks'       => [1,1,1], 'marketing'    => [1,1,1],
                'reports'     => [1,1,1], 'analytics'    => [1,1,1],
                'settings'    => [1,1,1],
            ],
            Role::MANAGER => [
                'daily_huddle' => [1,1,0], 'patients'    => [1,1,0],
                'appointments' => [1,1,0], 'treatments'  => [1,1,0],
                'prm'          => [1,1,0], 'finance'     => [1,1,0],
                'inventory'    => [1,1,0], 'lab'         => [1,1,0],
                'tasks'        => [1,1,0], 'marketing'   => [1,1,0],
                'reports'      => [1,0,0],
            ],
            Role::DOCTOR => [
                'daily_huddle' => [1,1,0], 'patients'   => [1,1,0],
                'appointments' => [1,1,0], 'treatments' => [1,1,1],
                'cms'          => [1,0,0], 'lab'        => [1,1,0],
                'tasks'        => [1,1,0],
            ],
            Role::ASSISTANT => [
                'appointments' => [1,0,0], 'patients' => [1,0,0],
                'tasks'        => [1,0,0],
            ],
            Role::FRONT_DESK => [
                'appointments' => [1,1,0], 'patients' => [1,1,0],
                'finance'      => [1,1,0], 'tasks'    => [1,0,0],
                'prm'          => [1,1,0],
            ],
            Role::ACCOUNTS => [
                'finance'  => [1,1,0], 'reports' => [1,0,0],
                'patients' => [1,0,0],
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
