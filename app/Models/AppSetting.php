<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Generic key-value settings store.
 * Usage:
 *   AppSetting::get('clinic_name', 'My Clinic')
 *   AppSetting::set('clinic_name', 'Tulip Dental')
 *   AppSetting::setMany(['clinic_name' => 'x', 'clinic_phone' => '9999'])
 *   AppSetting::group('clinic')  → ['clinic_name' => 'x', ...]
 */
class AppSetting extends Model
{
    protected $fillable = ['group', 'key', 'value'];

    /** Get a single value */
    public static function get(string $key, mixed $default = null): mixed
    {
        $row = static::where('key', $key)->first();
        return $row ? $row->value : $default;
    }

    /** Set a single value */
    public static function set(string $key, mixed $value, string $group = 'general'): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
    }

    /** Save multiple key-value pairs at once */
    public static function setMany(array $data, string $group = 'general'): void
    {
        foreach ($data as $key => $value) {
            static::set($key, $value, $group);
        }
    }

    /** Get all settings in a group as key => value array */
    public static function group(string $group): array
    {
        return static::where('group', $group)->pluck('value', 'key')->toArray();
    }

    /**
     * Resolve the four print margins configured in Settings → Printing
     * ("Use Printed Stationery — Leave Space") into ready-to-use CSS lengths.
     * Falls back per-side to the document's own default when not configured,
     * so nothing changes visually until a clinic actually sets a margin.
     *
     * Usage inside a standalone print view (before the closing </style>):
     *   @php $pm = \App\Models\AppSetting::printMargins(['top'=>'16mm','bottom'=>'16mm','left'=>'14mm','right'=>'14mm']); @endphp
     *   body { padding: {{ $pm['top'] }} {{ $pm['right'] }} {{ $pm['bottom'] }} {{ $pm['left'] }}; }
     *   @page { margin: 0; }   -- must stay 0, otherwise Chrome's own margin dropdown wins
     */
    public static function printMargins(array $defaults = []): array
    {
        $settings = static::group('print');
        $sides    = ['top', 'bottom', 'left', 'right'];
        $result   = [];

        foreach ($sides as $side) {
            $configured = $settings["print_margin_{$side}"] ?? null;
            $result[$side] = !empty($configured)
                ? $configured . 'in'
                : ($defaults[$side] ?? '14mm');
        }

        return $result;
    }
}
