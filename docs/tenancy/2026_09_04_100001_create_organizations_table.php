<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tenancy Step 2a — the tenant root.
 * -----------------------------------
 * `organizations` is THE tenant of Dentfluence OS. One row per practice that
 * owns data in this database. Everything clinic-owned hangs off it, directly
 * (organization_id) or through `branches`.
 *
 * NAMING RULING: this schema has exactly ONE tenant column name —
 * `organization_id`. Never `clinic_id`, never `tenant_id`.
 *
 * NOT to be confused with `clinics` (app/Modules/Hq): that is the Dentfluence
 * HQ sales CRM — the list of practices we sell to. It is not a tenant.
 *
 * The Tulip Dental row is seeded HERE, not in the Step 3 backfill, for one
 * reason: the very next migration puts a foreign key on `branches.organization_id`
 * with DEFAULT 1, and `branches` already holds live rows. Without organization
 * id=1 existing first, that FK cannot be created on a populated table. The seed
 * is guarded, so the Step 3 backfill stays idempotent and simply finds it here.
 *
 * `status` is a plain indexed string, not an enum, on purpose. Tenant lifecycle
 * states will grow (trial, past_due, suspended, churned) and altering an enum on
 * a table that ~80 tables point at is a bad surprise to inherit. Allowed values
 * are validated at the model layer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();

            // The practice owner. Nullable because users are created before the
            // tenant root exists; set explicitly, never guessed.
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Free-text plan label for now. The billing truth in V2 lives in
            // `subscriptions`, which today points at the HQ `clinics` table and
            // is deliberately NOT part of this tenancy retrofit.
            $table->string('subscription_plan')->nullable();

            // active | trial | suspended | cancelled  (model-validated)
            $table->string('status', 20)->default('active')->index();

            $table->timestamps();
            $table->softDeletes();
        });

        // Guarded seed — see the class docblock. Safe to re-run.
        if (! DB::table('organizations')->where('id', 1)->exists()) {
            DB::table('organizations')->insert([
                'id'         => 1,
                'name'       => 'Tulip Dental',
                'slug'       => 'tulip-dental',
                'status'     => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
