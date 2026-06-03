<?php

namespace App\Models;

use Illuminate\Support\Facades\Storage;

class WatermarkSetting
{
    private static string $path = 'settings/watermark.json';

    public static function all(): array
    {
        if (!Storage::disk('public')->exists(self::$path)) {
            return [];
        }
        return json_decode(Storage::disk('public')->get(self::$path), true) ?? [];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    public static function save(array $data): void
    {
        $existing = self::all();
        $merged   = array_merge($existing, $data);
        Storage::disk('public')->put(self::$path, json_encode($merged, JSON_PRETTY_PRINT));
    }
}
