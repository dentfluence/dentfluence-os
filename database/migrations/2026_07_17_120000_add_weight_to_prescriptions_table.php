<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional patient weight recorded on a prescription (e.g. "15 kg").
     * Kept as a short free-text string so it can hold a value with or without
     * a unit — useful for paediatric weight-based dosing. Nullable/optional.
     */
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('prescriptions', 'weight')) {
                $table->string('weight', 20)->nullable()->after('diagnosis');
            }
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            if (Schema::hasColumn('prescriptions', 'weight')) {
                $table->dropColumn('weight');
            }
        });
    }
};
