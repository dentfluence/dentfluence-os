<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mkt_brand_kits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinic_id')->unique(); // one brand kit per clinic

            // Clinic identity
            $table->string('clinic_name')->nullable();
            $table->string('tagline')->nullable();
            $table->string('website')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();

            // Logos (paths to storage)
            $table->string('logo_primary')->nullable();       // main logo
            $table->string('logo_light')->nullable();         // white/light variant
            $table->string('logo_dark')->nullable();          // dark variant
            $table->string('logo_icon')->nullable();          // icon/favicon

            // Brand colors (hex strings)
            $table->json('colors')->nullable();
            // e.g. [{"name":"Primary","hex":"#1A73E8","use":"buttons"},...]

            // Typography
            $table->string('font_primary')->nullable();       // heading font
            $table->string('font_secondary')->nullable();     // body font

            // Social handles
            $table->string('instagram_handle')->nullable();
            $table->string('facebook_page')->nullable();
            $table->string('google_business_name')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->string('blog_url')->nullable();

            // Default CTAs and hashtags
            $table->json('default_ctas')->nullable();         // ["Book Now","Call Us",...]
            $table->json('default_hashtags')->nullable();     // ["#dentist","#smile",...]

            // AI content defaults
            $table->string('ai_tone', 50)->default('professional');
            // professional|friendly|educational|motivational
            $table->json('ai_focus_treatments')->nullable();  // ["implants","aligners",...]
            $table->text('ai_brand_voice_notes')->nullable(); // free text guidance for AI

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('clinic_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mkt_brand_kits');
    }
};
