<?php

namespace App\Enums;

/**
 * StockStatus — the ONE canonical set of stock-status states for the whole
 * Inventory module (web, mobile API, AI, notifications).
 *
 * Definitions (approved 2026-07-20). Par level = the item's Minimum Stock
 * (`minimum_qty`); on-hand = clinic-wide available quantity across all locations:
 *
 *   In Stock              on_hand > minimum
 *   Low Stock             minimum/2 < on_hand <= minimum
 *   Critical Stock        0 < on_hand <= minimum/2
 *   Out of Stock          on_hand == 0
 *   Minimum Stock Not Set no Minimum Stock configured — EXCLUDED from the
 *                         Low / Critical / Out counts (shown as an informational
 *                         nudge to set a Minimum Stock so alerts can work).
 *
 * There is no other place in the codebase that decides stock status. Every
 * screen asks StockStatusService, which uses this enum.
 */
enum StockStatus: string
{
    case InStock   = 'in_stock';
    case Low       = 'low';
    case Critical  = 'critical';
    case Out       = 'out';
    case MinNotSet = 'min_not_set';

    /** Dentist-facing label. */
    public function label(): string
    {
        return match ($this) {
            self::InStock   => 'In Stock',
            self::Low       => 'Low Stock',
            self::Critical  => 'Critical Stock',
            self::Out       => 'Out of Stock',
            self::MinNotSet => 'Minimum Stock Not Set',
        };
    }

    /** Text / accent colour. */
    public function color(): string
    {
        return match ($this) {
            self::InStock   => '#1a7a45',
            self::Low       => '#a05c00',
            self::Critical  => '#d97706',
            self::Out       => '#b52020',
            self::MinNotSet => '#7a6884',
        };
    }

    /** Soft background for chips / dots. */
    public function bg(): string
    {
        return match ($this) {
            self::InStock   => '#e8f7ef',
            self::Low       => '#fff4e0',
            self::Critical  => '#fff1e0',
            self::Out       => '#fdeaea',
            self::MinNotSet => '#f3f0f6',
        };
    }

    /** The three states that need staff attention (drive alerts/badges). */
    public static function attentionStates(): array
    {
        return [self::Low, self::Critical, self::Out];
    }
}
