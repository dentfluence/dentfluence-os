<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7 — Integration Engine, Slice 1: dual-run shadow-comparison log.
 *
 * One row per outbound send where IntegrationEngine compared "what the
 * connector would have built" against what actually went out (either the
 * legacy WhatsAppCloudService call, or the connector itself once
 * `integration.whatsapp` is on). Purely observational — same pattern as
 * `workflow_shadow_log` (Phase 5) and `automation_shadow_log` (Phase 2).
 * Never read by anything that changes behaviour; only by the
 * `integration:parity` report.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_shadow_log', function (Blueprint $table) {
            $table->id();

            // 'whatsapp' today; 'google' | 'meta' | 'website' | 'abdm' | 'payments' in later slices.
            $table->string('provider', 30)->index();

            // 'text' | 'template' (send-shape, provider-specific values allowed later).
            $table->string('method', 20);

            // 'legacy' = integration.<provider> was OFF, direct vendor client sent.
            // 'cutover' = flag was ON, the connector sent.
            $table->string('action', 20);

            // See IntegrationEngine::log() docblock for what this does and doesn't prove.
            $table->boolean('agreed')->nullable();

            $table->json('preview_payload')->nullable();
            $table->json('result_payload')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['provider', 'agreed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_shadow_log');
    }
};
