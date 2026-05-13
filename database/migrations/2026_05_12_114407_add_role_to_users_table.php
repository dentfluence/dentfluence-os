<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add Dentfluence OS columns to the default Laravel users table.
     *
     * Columns added:
     *   role         — single login phase: default 'admin'
     *                  future values: admin | doctor | front_desk | assistant | accounts
     *   branch_id    — default 1 (Dombivli). Ready for multi-branch expansion.
     *   is_active    — soft disable staff without deleting account
     *   last_login_at — audit trail, security monitoring
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $table->string('role')
                  ->default('admin')
                  ->after('password');

            $table->unsignedTinyInteger('branch_id')
                  ->default(1)
                  ->after('role')
                  ->comment('1 = Dombivli. Future multi-branch ready.');

            $table->boolean('is_active')
                  ->default(true)
                  ->after('branch_id');

            $table->timestamp('last_login_at')
                  ->nullable()
                  ->after('is_active');

        });
    }

    /**
     * Reverse the migrations.
     * Drop in reverse order of creation.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role',
                'branch_id',
                'is_active',
                'last_login_at',
            ]);
        });
    }
};