<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make audit_logs.auditable_type nullable.
 *
 * Security events written via AuditLog::event() — login, login_failed, logout,
 * role changes, etc. — have no underlying model, so the code intentionally
 * passes a null auditable_type (auditable_id was already nullable). The original
 * create_audit_logs migration made auditable_type NOT NULL, which blocked those
 * events with a SQLSTATE[23000] 1048 "Column 'auditable_type' cannot be null"
 * error (seen on mobile login). This aligns the column with the code's intent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('auditable_type')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Revert to NOT NULL. Note: if any null auditable_type rows exist (e.g.
        // login events), this rollback will fail until they are backfilled.
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('auditable_type')->nullable(false)->change();
        });
    }
};
