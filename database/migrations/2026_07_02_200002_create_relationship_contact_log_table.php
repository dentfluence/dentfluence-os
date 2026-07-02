<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 — Relationship Engine: CommunicationGuard
 *
 * Records every outbound contact attempt for a relationship.
 * CommunicationGuard::canContact() queries this table before allowing
 * any new outbound communication, enforcing:
 *   - No same channel twice in 24 hours
 *   - No more than 3 total contacts in 7 days
 *   - Birthday message today → block promotional messages
 */
return new class extends Migration
{
    public function up(): void
    {
        // Guard: table may already exist if migration was run manually or a previous
        // migrate run created the table but crashed before recording it.
        if (Schema::hasTable('relationship_contact_log')) {
            return;
        }

        Schema::create('relationship_contact_log', function (Blueprint $table) {
            $table->id();

            // The relationship that was contacted
            $table->foreignId('relationship_id')
                  ->constrained('relationships')
                  ->cascadeOnDelete();

            // Communication channel used
            $table->enum('channel', ['call', 'whatsapp', 'sms', 'email']);

            // Type of contact (appointment_reminder / recall / birthday / marketing / offer / etc.)
            $table->string('type');

            // Exact timestamp of the contact attempt
            $table->dateTime('contacted_at')->index();

            $table->timestamps();

            // Composite indexes for the guard's queries.
            // NOTE: explicit short names — the auto-generated names exceed MySQL's
            // 64-char identifier limit for this long table name (fresh-build fix).
            $table->index(['relationship_id', 'channel', 'contacted_at'], 'rcl_rel_channel_time_idx'); // same-channel cooldown
            $table->index(['relationship_id', 'contacted_at'], 'rcl_rel_time_idx');                    // total contacts window
            $table->index(['relationship_id', 'type', 'contacted_at'], 'rcl_rel_type_time_idx');       // birthday-block check
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relationship_contact_log');
    }
};
