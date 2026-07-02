<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrBonus extends Model
{
    protected $table = 'hr_bonuses';

    protected $fillable = [
        'user_id', 'bonus_name', 'bonus_type', 'amount', 'bonus_date', 'month_year', 'notes', 'created_by',
    ];

    protected $casts = [
        'bonus_date' => 'date',
        'amount'     => 'decimal:2',
    ];

    public static array $typeLabels = [
        'festival'    => 'Festival',
        'performance' => 'Performance',
        'annual'      => 'Annual',
        'joining'     => 'Joining',
        'retention'   => 'Retention',
        'other'       => 'Other',
    ];

    public static array $typeColors = [
        'festival'    => '#d97706',
        'performance' => '#059669',
        'annual'      => '#7c3aed',
        'joining'     => '#0891b2',
        'retention'   => '#db2777',
        'other'       => '#6b7280',
    ];

    public function getTypeLabelAttribute(): string
    {
        return self::$typeLabels[$this->bonus_type] ?? ucfirst($this->bonus_type);
    }

    public function getTypeColorAttribute(): string
    {
        return self::$typeColors[$this->bonus_type] ?? '#6b7280';
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
