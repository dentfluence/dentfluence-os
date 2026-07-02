<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CmsTag extends Model
{
    protected $table = 'cms_tags';

    protected $fillable = ['name', 'type', 'color', 'usage_count'];

    // Increment usage when tag is applied
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    // Common tag colors by type
    public static function colorForType(string $type): string
    {
        return match($type) {
            'treatment' => '#6a0f70',
            'stage'     => '#2563eb',
            'tooth'     => '#16a34a',
            'marketing' => '#dc2626',
            default     => '#6b7280',
        };
    }
}
