<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dentfluence Patient Module — Full Spec Upgrade
 * Adds all fields required by the master specification.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {

            // ── Formatted Patient ID ─────────────────────────────────────────
            // e.g. DF-00142  (generated on creation)
            if (!Schema::hasColumn('patients', 'patient_id')) {
                $table->string('patient_id', 20)->nullable()->unique()->after('id');
            }

            // ── Name fields ──────────────────────────────────────────────────
            // Keep 'name' as the combined/display name
            if (!Schema::hasColumn('patients', 'title')) {
                $table->string('title', 10)->nullable()->after('patient_id')
                    ->comment('Mr. / Mrs. / Miss / Mast. / Dr.');
            }
            if (!Schema::hasColumn('patients', 'first_name')) {
                $table->string('first_name', 100)->nullable()->after('title');
            }
            if (!Schema::hasColumn('patients', 'middle_name')) {
                $table->string('middle_name', 100)->nullable()->after('first_name');
            }
            if (!Schema::hasColumn('patients', 'last_name')) {
                $table->string('last_name', 100)->nullable()->after('middle_name');
            }

            // ── DOB Unknown toggle ───────────────────────────────────────────
            if (!Schema::hasColumn('patients', 'dob_unknown')) {
                $table->boolean('dob_unknown')->default(false)->after('date_of_birth');
            }
            if (!Schema::hasColumn('patients', 'age_years')) {
                $table->unsignedTinyInteger('age_years')->nullable()->after('dob_unknown')
                    ->comment('Used when DOB is unknown');
            }

            // ── Contact ──────────────────────────────────────────────────────
            if (!Schema::hasColumn('patients', 'alternate_phone')) {
                $table->string('alternate_phone', 20)->nullable()->after('phone');
            }
            if (!Schema::hasColumn('patients', 'emergency_contact_name')) {
                $table->string('emergency_contact_name', 100)->nullable();
            }
            if (!Schema::hasColumn('patients', 'emergency_contact_relationship')) {
                $table->string('emergency_contact_relationship', 50)->nullable();
            }
            if (!Schema::hasColumn('patients', 'emergency_contact_number')) {
                $table->string('emergency_contact_number', 20)->nullable();
            }

            // ── Address — area/locality (separate from city) ─────────────────
            if (!Schema::hasColumn('patients', 'area')) {
                $table->string('area', 150)->nullable()->after('address')
                    ->comment('Area / Locality within the city');
            }

            // ── Clinical — tag-based JSON arrays ─────────────────────────────
            if (!Schema::hasColumn('patients', 'medical_conditions')) {
                $table->json('medical_conditions')->nullable()
                    ->comment('e.g. ["Diabetes","Hypertension"]');
            }
            if (!Schema::hasColumn('patients', 'current_medications')) {
                $table->text('current_medications')->nullable();
            }
            if (!Schema::hasColumn('patients', 'dental_conditions')) {
                $table->json('dental_conditions')->nullable()
                    ->comment('e.g. ["Missing Teeth","Caries"]');
            }

            // ── Habits with frequency ────────────────────────────────────────
            // 'habits' already exists as JSON in the base table
            // We add a separate frequency map: {"Smoking": "5 Cigarettes/Day", ...}
            if (!Schema::hasColumn('patients', 'habit_frequency')) {
                $table->json('habit_frequency')->nullable();
            }

            // ── Membership ───────────────────────────────────────────────────
            if (!Schema::hasColumn('patients', 'membership_status')) {
                $table->enum('membership_status', ['not_enrolled', 'active', 'expired'])
                    ->default('not_enrolled')->after('source');
            }
            if (!Schema::hasColumn('patients', 'membership_expires_at')) {
                $table->date('membership_expires_at')->nullable()->after('membership_status');
            }

            // ── Follow-up status ─────────────────────────────────────────────
            if (!Schema::hasColumn('patients', 'follow_up_status')) {
                $table->enum('follow_up_status', ['none', 'due', 'pending', 'completed'])
                    ->default('none')->after('membership_expires_at');
            }
            if (!Schema::hasColumn('patients', 'follow_up_date')) {
                $table->date('follow_up_date')->nullable()->after('follow_up_status');
            }

            // ── Source dynamic fields ────────────────────────────────────────
            if (!Schema::hasColumn('patients', 'source_referral_name')) {
                $table->string('source_referral_name', 150)->nullable();
            }
            if (!Schema::hasColumn('patients', 'source_camp_name')) {
                $table->string('source_camp_name', 150)->nullable();
            }
            if (!Schema::hasColumn('patients', 'source_campaign')) {
                $table->string('source_campaign', 150)->nullable()
                    ->comment('Instagram/Google campaign name');
            }

            // ── Photo ────────────────────────────────────────────────────────
            if (!Schema::hasColumn('patients', 'photo')) {
                $table->string('photo')->nullable()
                    ->comment('Storage path to patient photo');
            }

            // ── Financial summary (denormalized for quick display) ────────────
            if (!Schema::hasColumn('patients', 'total_billed')) {
                $table->decimal('total_billed', 10, 2)->default(0)->after('outstanding_balance');
            }
            if (!Schema::hasColumn('patients', 'total_received')) {
                $table->decimal('total_received', 10, 2)->default(0)->after('total_billed');
            }
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $cols = [
                'patient_id','title','first_name','middle_name','last_name',
                'dob_unknown','age_years','alternate_phone',
                'emergency_contact_name','emergency_contact_relationship','emergency_contact_number',
                'area','medical_conditions','current_medications','dental_conditions',
                'habit_frequency','membership_status','membership_expires_at',
                'follow_up_status','follow_up_date',
                'source_referral_name','source_camp_name','source_campaign',
                'photo','total_billed','total_received',
            ];
            // Only drop columns that exist
            $existing = array_filter($cols, fn($c) => Schema::hasColumn('patients', $c));
            if ($existing) {
                $table->dropColumn(array_values($existing));
            }
        });
    }
};
