<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chunk 1 — Billing Workflow Update
 * Fine-grained billing action permissions per role, extending the existing
 * role -> module permission system (which is only view/edit/delete per module).
 *
 * Each row = one role's rule for one action key, e.g.:
 *   manual_discount      : is_allowed + limit_value (max % or ₹, null = unlimited)
 *   wallet_adjustment    : is_allowed
 *   wallet_refund        : is_allowed
 *   invoice_edit         : is_allowed
 *   advance_adjustment   : is_allowed
 *
 * `limit_type` tells us how to read limit_value ('percentage' | 'flat' | null).
 * Seeding of sensible defaults happens in Chunk 2 (where manual discount is
 * first enforced) so limits arrive alongside the feature that uses them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_billing_permissions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();

            // Action key, e.g. 'manual_discount', 'wallet_refund'
            $table->string('action_key', 50);

            $table->boolean('is_allowed')->default(false);

            // Optional cap for value-bearing actions (e.g. max discount)
            $table->decimal('limit_value', 12, 2)->nullable();
            // 'percentage' | 'flat' | null (null = no numeric limit / unlimited)
            $table->string('limit_type', 20)->nullable();

            $table->timestamps();

            $table->unique(['role_id', 'action_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_billing_permissions');
    }
};
