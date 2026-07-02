<?php
// =============================================================================
// Wallet Campaigns
// Promotional money issued in bulk to patients matching filter criteria.
// Works like a broadcast: define filters → preview matching patients → apply.
// Each apply() call credits every matching patient's wallet individually.
// =============================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_campaigns', function (Blueprint $table) {
            $table->id();

            // ── Campaign identity ──────────────────────────────────────────────
            $table->string('name', 200);
            $table->text('description')->nullable();

            // ── Credit settings ────────────────────────────────────────────────
            $table->decimal('amount', 12, 2);                      // per-patient credit
            $table->date('expiry_date');                           // promo must have expiry
            $table->json('applicable_treatments')->nullable();     // null = all treatments

            // ── Patient filter criteria ────────────────────────────────────────
            // Each filter is nullable — null means "no filter on this dimension"
            $table->json('filter_gender')->nullable();             // ['male','female','other']
            $table->json('filter_area')->nullable();               // ['Baner','Kothrud',...]
            $table->json('filter_tag_ids')->nullable();            // [1,3,5] — Tag IDs
            $table->unsignedSmallInteger('filter_age_min')->nullable();
            $table->unsignedSmallInteger('filter_age_max')->nullable();
            $table->json('filter_membership')->nullable();         // ['active','not_enrolled','expired']
            $table->json('filter_source')->nullable();             // ['referral','google_ads',...]

            // ── Lifecycle ─────────────────────────────────────────────────────
            $table->enum('status', ['draft', 'applied', 'cancelled'])->default('draft');
            $table->unsignedInteger('patients_credited')->default(0);
            $table->decimal('total_amount_issued', 14, 2)->default(0);
            $table->timestamp('applied_at')->nullable();

            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_campaigns');
    }
};
