<?php

namespace App\Support;

use App\Models\User;

/**
 * DoctorColors
 * ------------
 * One source of truth for the per-doctor accent colour used on appointment
 * cards. The web calendar assigns colours client-side from this exact
 * palette, indexed by the doctor's position in the branch doctor list
 * (resources/views/appointments/index.blade.php — DOC_COLORS). The mobile
 * app can't replicate that ordering reliably, so the API stamps each
 * appointment with the resolved hex instead.
 *
 * Keep the palette and the doctor-list query in lockstep with the web
 * calendar, or web and mobile will disagree about a doctor's colour.
 */
class DoctorColors
{
    /** Same order as DOC_COLORS in the web appointment calendar. */
    public const PALETTE = [
        '#2563eb', '#059669', '#d97706', '#dc2626',
        '#7c3aed', '#0891b2', '#be185d', '#0284c7',
    ];

    public const FALLBACK = '#94a3b8';

    /** Per-request cache: branch_id => [doctor_id => hex]. */
    protected static array $maps = [];

    /** doctor_id => hex for one branch (query mirrors the web calendar's list). */
    public static function map(int $branchId): array
    {
        if (! isset(self::$maps[$branchId])) {
            $ids = User::where('branch_id', $branchId)
                ->where('is_active', true)
                ->where(fn ($q) => $q->whereIn('role', User::DOCTOR_ROLES)->orWhere('name', 'like', 'Dr.%'))
                ->orderBy('id')
                ->pluck('id');

            $map = [];
            foreach ($ids as $i => $id) {
                $map[$id] = self::PALETTE[$i % count(self::PALETTE)];
            }
            self::$maps[$branchId] = $map;
        }

        return self::$maps[$branchId];
    }

    /** Colour for one doctor (grey fallback for unknown/unassigned). */
    public static function for(?int $doctorId, ?int $branchId): string
    {
        if (! $doctorId || ! $branchId) {
            return self::FALLBACK;
        }

        return self::map($branchId)[$doctorId] ?? self::FALLBACK;
    }
}
