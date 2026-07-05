<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NO-OP (2026-07-05, same day as creation): Anniversary tracking was removed
 * before this migration was ever run — Sumit's clinic never collects
 * anniversary dates and doesn't want the feature at all. Kept as an empty
 * migration for migration-history continuity only — do not delete.
 *
 * Original intent (never executed against any real database): add a
 * nullable anniversary_date column to patients, plus a
 * recall_anniversary_queued_at cooldown stamp mirroring
 * recall_birthday_queued_at.
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
