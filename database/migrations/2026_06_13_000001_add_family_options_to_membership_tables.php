<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add family membership support.
 *
 * Plans get a family_option field (none / addon / bundle) plus
 * pricing / limits for family enrollments.
 *
 * Enrollments get member_type (individual / head / addon),
 * a self-referencing FK to the head's enrollment, and a
 * family_name label (e.g. "Firke Family").
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Plans table ──────────────────────────────────────────────────────
        Schema::table('finance_membership_plans', function (Blueprint $table) {

            // none    = no family option (individual only)
            // addon   = head pays full price, each add-on pays addon_price
            // bundle  = one flat price covers up to max_family_members
            $table->enum('family_option', ['none', 'addon', 'bundle'])
                  ->default('none')
                  ->after('is_active')
                  ->comment('none=individual only, addon=per-member add-on, bundle=flat family price');

            // Per-add-on price (only used when family_option = addon)
            $table->decimal('addon_price', 10, 2)
                  ->nullable()
                  ->after('family_option')
                  ->comment('Price per add-on member — only for addon family_option');

            // Max family members allowed under this plan
            // For addon  : max number of add-ons (excluding head)
            // For bundle : max total members (including head)
            $table->unsignedTinyInteger('max_family_members')
                  ->default(4)
                  ->after('addon_price')
                  ->comment('Max add-ons (addon mode) or total members (bundle mode)');
        });

        // ── Patient memberships table ─────────────────────────────────────────
        Schema::table('finance_patient_memberships', function (Blueprint $table) {

            // individual = regular solo enrollment
            // head       = family head (main payer)
            // addon      = family add-on linked to a head's enrollment
            $table->enum('member_type', ['individual', 'head', 'addon'])
                  ->default('individual')
                  ->after('status')
                  ->comment('individual=solo, head=family head, addon=family add-on');

            // Points to the head's finance_patient_memberships.id
            // NULL for individual and head records
            $table->unsignedBigInteger('family_head_membership_id')
                  ->nullable()
                  ->after('member_type')
                  ->comment('FK to head enrollment — set for addon members only');

            // Display name for the family group, set on head enrollment
            // e.g. "Firke Family" — shown in member lists / communication
            $table->string('family_name', 100)
                  ->nullable()
                  ->after('family_head_membership_id')
                  ->comment('Family group label, set when enrolling as head');

            $table->foreign('family_head_membership_id')
                  ->references('id')
                  ->on('finance_patient_memberships')
                  ->onDelete('set null');  // if head is deleted, addons lose link but stay

            $table->index('family_head_membership_id');
        });
    }

    public function down(): void
    {
        Schema::table('finance_patient_memberships', function (Blueprint $table) {
            $table->dropForeign(['family_head_membership_id']);
            $table->dropIndex(['family_head_membership_id']);
            $table->dropColumn(['member_type', 'family_head_membership_id', 'family_name']);
        });

        Schema::table('finance_membership_plans', function (Blueprint $table) {
            $table->dropColumn(['family_option', 'addon_price', 'max_family_members']);
        });
    }
};
