<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;

class BrandKit extends Model
{
    protected $table = 'mkt_brand_kits';

    protected $fillable = [
        'clinic_id',
        'clinic_name',
        'tagline',
        'website',
        'phone',
        'email',
        'address',
        'logo_primary',
        'logo_light',
        'logo_dark',
        'logo_icon',
        'colors',
        'font_primary',
        'font_secondary',
        'instagram_handle',
        'facebook_page',
        'google_business_name',
        'whatsapp_number',
        'blog_url',
        'default_ctas',
        'default_hashtags',
        'ai_tone',
        'ai_focus_treatments',
        'ai_brand_voice_notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'colors'               => 'array',
        'default_ctas'         => 'array',
        'default_hashtags'     => 'array',
        'ai_focus_treatments'  => 'array',
        'created_at'           => 'datetime',
        'updated_at'           => 'datetime',
    ];

    // -----------------------------------------------------------------------
    // Static helpers
    // -----------------------------------------------------------------------

    /**
     * Get or create the brand kit for a clinic (one per clinic).
     */
    public static function forClinic(int $clinicId): static
    {
        return static::firstOrCreate(['clinic_id' => $clinicId]);
    }
}
