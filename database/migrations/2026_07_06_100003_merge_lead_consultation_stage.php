<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data-only migration — no schema change. `leads.stage` is a plain string
 * column (not a DB enum), so this just backfills existing rows.
 *
 * 'consultation' was merged into 'appointment' as a single Lead Pipeline stage
 * (2026-07-06) — see LeadPipelineController::STAGES. Any lead currently
 * sitting at 'consultation' needs to move to 'appointment' so it keeps
 * rendering on the board instead of silently falling out of every column
 * once 'consultation' stops being a recognised stage key.
 *
 * down() is best-effort only: it moves everything back to 'consultation',
 * which cannot distinguish leads that were originally 'appointment' from
 * ones that were 'consultation' before this ran. Rolling back is not
 * expected to be needed in practice — this is a one-way UX simplification.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('leads')
            ->where('stage', 'consultation')
            ->update(['stage' => 'appointment']);
    }

    public function down(): void
    {
        // Intentionally a no-op — see class docblock. Reversing this
        // correctly would require having recorded which rows were
        // 'consultation' before the merge, which up() does not do.
    }
};
