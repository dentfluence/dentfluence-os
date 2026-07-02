<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadActivity extends Model
{
    protected $fillable = [
        'lead_id', 'type', 'label', 'outcome',
        'note', 'activity_date', 'activity_time', 'by',
    ];

    protected $casts = [
        'activity_date' => 'date',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    // Icon/colour helpers used by lead-detail view
    public function getIconAttribute(): string
    {
        return match ($this->type) {
            'call'         => 'phone',
            'whatsapp'     => 'brand-whatsapp',
            'note'         => 'note',
            'followup'     => 'calendar',
            'email'        => 'mail',
            'stage_change' => 'arrow-right',
            default        => 'activity',
        };
    }

    public function getIconBgAttribute(): string
    {
        return match ($this->type) {
            'call'     => '#E1F5EE',
            'whatsapp' => '#E1F5EE',
            'followup' => '#FAEEDA',
            default    => '#F1EFE8',
        };
    }

    public function getIconColorAttribute(): string
    {
        return match ($this->type) {
            'call'     => '#0F6E56',
            'whatsapp' => '#0F6E56',
            'followup' => '#854F0B',
            default    => '#5F5E5A',
        };
    }

    /**
     * Formatted date string for the view (e.g. "10 May 2025, 10:30 AM").
     */
    public function getDateAttribute(): string
    {
        if (! $this->activity_date) return '';
        $d = $this->activity_date->format('d M Y');
        return $this->activity_time ? "{$d}, {$this->activity_time}" : $d;
    }
}
