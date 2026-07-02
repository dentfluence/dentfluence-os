<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;

class MarketingSetting extends Model
{
    protected $table = 'mkt_settings';

    protected $fillable = [
        'clinic_id',
        'key',
        'value',
        'type',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // -----------------------------------------------------------------------
    // Static helpers
    // -----------------------------------------------------------------------

    /**
     * Get a setting value for a clinic, cast to the correct PHP type.
     */
    public static function get(int $clinicId, string $key, mixed $default = null): mixed
    {
        $setting = static::where('clinic_id', $clinicId)->where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        return match ($setting->type) {
            'boolean' => (bool) $setting->value,
            'integer' => (int)  $setting->value,
            'json'    => json_decode($setting->value, true),
            default   => $setting->value,
        };
    }

    /**
     * Set (upsert) a setting value for a clinic.
     */
    public static function set(int $clinicId, string $key, mixed $value, string $type = 'string'): static
    {
        $storedValue = $type === 'json' ? json_encode($value) : (string) $value;

        return static::updateOrCreate(
            ['clinic_id' => $clinicId, 'key' => $key],
            ['value' => $storedValue, 'type' => $type]
        );
    }
}
