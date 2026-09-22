<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Task Manager V2 — T-1b: maintenance types become clinic-editable.
 *
 * THE BLOCKER THIS REMOVES: tasks.maintenance_type was an ENUM of ten fixed
 * values. A clinic adding "Compressor service" or "RO membrane change" in
 * Settings would have seen it in the dropdown and then had the write rejected
 * by MySQL — a settings screen that visibly does nothing. So the column
 * becomes a plain string first; the option list is what constrains it now,
 * validated in the controller.
 *
 * The ten shipped values are seeded into action_option_lists
 * (option_type = 'maintenance_type', action_category = null — these are not
 * per task category) so the existing list survives verbatim and the same
 * Tasks > Settings screen edits them.
 *
 * WHY NOT JUST WIDEN THE ENUM each time: an enum change is a migration, and a
 * clinic cannot write one. Anything a clinic must be able to extend does not
 * belong in an enum.
 *
 * Idempotent; existing rows keep their values (the strings are identical).
 */
return new class extends Migration
{
    private const TYPES = [
        ['ac_service',    'AC Service'],
        ['pest_control',  'Pest Control'],
        ['deep_cleaning', 'Deep Cleaning'],
        ['autoclave',     'Autoclave Maintenance'],
        ['dental_chair',  'Dental Chair Servicing'],
        ['xray_machine',  'X-Ray Machine'],
        ['water_purifier','Water Purifier'],
        ['fire_safety',   'Fire Safety Check'],
        ['generator',     'Generator / UPS'],
        ['other',         'Other'],
    ];

    public function up(): void
    {
        // Widen first. Existing rows already hold these exact strings, so this
        // is a type change only — no data is rewritten.
        DB::statement("ALTER TABLE tasks MODIFY COLUMN maintenance_type VARCHAR(50) NULL");

        $now = now();

        foreach (self::TYPES as $i => [$key, $label]) {
            $exists = DB::table('action_option_lists')
                ->where('option_type', 'maintenance_type')
                ->where('key', $key)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('action_option_lists')->insert([
                'option_type'     => 'maintenance_type',
                'action_category' => null,
                'key'             => $key,
                'label'           => $label,
                'closes_task'     => false,   // meaningless for this type; stored false
                'requires_notes'  => false,
                'sort_order'      => $i,
                'is_active'       => true,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('action_option_lists')
            ->where('option_type', 'maintenance_type')
            ->whereIn('key', array_column(self::TYPES, 0))
            ->delete();

        // Anything a clinic added beyond the ten would not fit the enum, so
        // park those tasks before narrowing the column back.
        DB::table('tasks')
            ->whereNotNull('maintenance_type')
            ->whereNotIn('maintenance_type', array_column(self::TYPES, 0))
            ->update(['maintenance_type' => 'other']);

        DB::statement("
            ALTER TABLE tasks MODIFY COLUMN maintenance_type ENUM(
                'ac_service','pest_control','deep_cleaning','autoclave',
                'dental_chair','xray_machine','water_purifier','fire_safety',
                'generator','other'
            ) NULL
        ");
    }
};
