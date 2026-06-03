<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('type')->default('general'); // treatment, stage, tooth, general
            $table->string('color')->nullable();         // hex for UI pill
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_tags');
    }
};
