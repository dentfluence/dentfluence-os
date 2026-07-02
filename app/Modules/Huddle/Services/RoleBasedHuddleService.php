<?php

declare(strict_types=1);

namespace App\Modules\Huddle\Services;

class RoleBasedHuddleService
{
    /**
     * Column keys visible to each role.
     * Order here = render order on the board.
     */
    private const ROLE_COLUMNS = [
        'admin' => [
            'today_flow',
            'yesterday_flow',
            'critical_alerts',
            'tasks',
            'lab',
            'inventory',
            'marketing',
            'maintenance',
            'comms',
            'quick_actions',
        ],
        'doctor' => [
            'today_flow',
            'yesterday_flow',
            'critical_alerts',
            'tasks',
            'lab',
            'inventory',
            'marketing',
            'maintenance',
            'quick_actions',
        ],
        'front_desk' => [
            'today_flow',
            'comms',
            'tasks',
            'yesterday_flow',
            'quick_actions',
        ],
        'assistant' => [
            'today_flow',
            'assist_assignments',
            'tasks',
            'comments',
        ],
    ];

    /**
     * Human-readable labels for each column key.
     */
    private const COLUMN_LABELS = [
        'today_flow'        => 'Today\'s Flow',
        'yesterday_flow'    => 'Yesterday\'s Pending',
        'critical_alerts'   => 'Critical Alerts',
        'tasks'             => 'Tasks',
        'lab'               => 'Lab',
        'inventory'         => 'Inventory',
        'marketing'         => 'Marketing',
        'maintenance'       => 'Maintenance',
        'comms'             => 'Comms',
        'quick_actions'     => 'Quick Actions',
        'assist_assignments'=> 'My Assignments',
        'comments'          => 'Comments / Hurdles',
    ];

    /**
     * Returns the ordered list of column keys for a given role.
     * Falls back to front_desk columns for unknown roles.
     */
    public function columnsForRole(string $role): array
    {
        return self::ROLE_COLUMNS[$role] ?? self::ROLE_COLUMNS['front_desk'];
    }

    /**
     * Returns the label for a given column key.
     */
    public function labelForColumn(string $columnKey): string
    {
        return self::COLUMN_LABELS[$columnKey] ?? ucwords(str_replace('_', ' ', $columnKey));
    }

    /**
     * Returns true if the given role can see the given column.
     */
    public function canSeeColumn(string $role, string $columnKey): bool
    {
        return in_array($columnKey, $this->columnsForRole($role), strict: true);
    }

    /**
     * Returns true if the role has admin-level access.
     * Used for settings, locking boards, etc.
     */
    public function isAdmin(string $role): bool
    {
        return $role === 'admin';
    }

    /**
     * Returns true if the role is clinical (sees clinical columns).
     */
    public function isClinical(string $role): bool
    {
        return in_array($role, ['admin', 'doctor'], strict: true);
    }
}