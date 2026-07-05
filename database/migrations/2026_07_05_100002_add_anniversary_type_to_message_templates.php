<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * NO-OP (2026-07-05, same day as creation): Anniversary tracking was removed
 * before this migration was ever run — Sumit's clinic never collects
 * anniversary dates and doesn't want the feature at all. Kept as an empty
 * migration for migration-history continuity only — do not delete. The
 * message_templates.type enum stays exactly
 * appointment_reminder|followup|recall|birthday|custom — no 'anniversary'
 * value was ever added.
 *
 * Original intent (never executed against any real database): add
 * 'anniversary' as a distinct MessageTemplate type alongside the existing
 * enum values, via a raw ALTER TABLE (Laravel's schema builder can't MODIFY
 * an enum in place without doctrine/dbal).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Intentionally empty — see class doc-comment above.
    }

    public function down(): void
    {
        // Intentionally empty — see class doc-comment above.
    }
};
