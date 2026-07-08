<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stock movements are an append-only ledger — nothing is ever edited or
     * deleted in place (see reverseLastGrn() for the same pattern already used
     * on GRN receiving). This adds the fields needed to "correct a mistake" on
     * a manual quick-adjust (+/-) the same safe way: by creating a compensating
     * entry rather than rewriting history.
     *
     * - reversal_of_id  → set on the NEW compensating entry, points back at the
     *                     original movement it cancels out.
     * - reversed_at/by  → set on the ORIGINAL movement once it has been
     *                     reversed, so it can't be reversed twice and the log
     *                     can show "corrected by X on Y".
     */
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('reversal_of_id')->nullable()->after('reference_id')
                ->constrained('stock_movements')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable()->after('notes');
            $table->foreignId('reversed_by')->nullable()->after('reversed_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reversal_of_id');
            $table->dropConstrainedForeignId('reversed_by');
            $table->dropColumn('reversed_at');
        });
    }
};
