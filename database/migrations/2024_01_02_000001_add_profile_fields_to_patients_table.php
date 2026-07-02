<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            // Personal / lifestyle
            $table->string('occupation', 150)->nullable()->after('email');
            $table->json('habits')->nullable()->after('occupation');          // ['smoking','alcohol','tobacco','betel_nut']
            $table->json('allergies')->nullable()->after('habits');           // free-text array

            // Relationship intelligence
            $table->string('family_notes', 500)->nullable()->after('allergies');

            // Recall / lifecycle
            $table->enum('recall_status', ['active','due','overdue','inactive'])->default('active')->after('referred_by');
            $table->date('next_recall_date')->nullable()->after('recall_status');
            $table->date('last_visit_date')->nullable()->after('next_recall_date');

            // Financial summary (denormalised for quick header display)
            $table->decimal('outstanding_balance', 10, 2)->default(0)->after('last_visit_date');
            $table->decimal('lifetime_value', 10, 2)->default(0)->after('outstanding_balance');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn([
                'occupation','habits','allergies','family_notes',
                'recall_status','next_recall_date','last_visit_date',
                'outstanding_balance','lifetime_value',
            ]);
        });
    }
};
