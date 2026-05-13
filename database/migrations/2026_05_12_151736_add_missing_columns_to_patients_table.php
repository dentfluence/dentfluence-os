<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->date('dob')->nullable()->after('phone');
            $table->string('gender')->nullable()->after('dob');
            $table->string('email')->nullable()->after('gender');
            $table->text('address')->nullable()->after('email');
            $table->string('city')->nullable()->after('address');
            $table->string('state')->nullable()->after('city');
            $table->string('pincode')->nullable()->after('state');
            $table->text('chief_complaint')->nullable()->after('pincode');
            $table->text('medical_alert')->nullable()->after('chief_complaint');
            $table->string('source')->nullable()->after('medical_alert');
            $table->string('referred_by')->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn([
                'dob',
                'gender',
                'email',
                'address',
                'city',
                'state',
                'pincode',
                'chief_complaint',
                'medical_alert',
                'source',
                'referred_by'
            ]);
        });
    }
};