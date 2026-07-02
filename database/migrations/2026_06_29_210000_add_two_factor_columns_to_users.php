<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase A — Part 4: Two-factor authentication (MFA) columns on users.
 *
 *  - two_factor_secret         : the TOTP secret (encrypted at rest via cast)
 *  - two_factor_recovery_codes : one-time backup codes (encrypted JSON via cast)
 *  - two_factor_confirmed_at   : set once the user verifies a first code; until
 *                                then 2FA is "pending setup" and not enforced.
 *
 * All nullable — 2FA is opt-in per user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'two_factor_secret')) {
                $table->text('two_factor_secret')->nullable()->after('password');
            }
            if (! Schema::hasColumn('users', 'two_factor_recovery_codes')) {
                $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            }
            if (! Schema::hasColumn('users', 'two_factor_confirmed_at')) {
                $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['two_factor_confirmed_at', 'two_factor_recovery_codes', 'two_factor_secret'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
