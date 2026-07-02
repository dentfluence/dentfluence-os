<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrIncentiveRule extends Model
{
    protected $table = 'hr_incentive_rules';

    protected $fillable = [
        'user_id', 'compensation_type', 'revenue_target', 'incentive_rate',
        'per_patient_rate', 'minimum_guarantee', 'target_appointments',
        'bonus_amount', 'notes',
    ];

    protected $casts = [
        'revenue_target'     => 'decimal:2',
        'incentive_rate'     => 'decimal:2',
        'per_patient_rate'   => 'decimal:2',
        'minimum_guarantee'  => 'decimal:2',
        'bonus_amount'       => 'decimal:2',
    ];

    public static array $typeLabels = [
        'fixed'          => 'Fixed Salary Only',
        'fixed_revenue'  => 'Fixed + Revenue % (above target)',
        'pure_revenue'   => 'Pure Revenue %',
        'per_patient'    => 'Per Patient Fixed',
        'fixed_bonus'    => 'Fixed + Target Bonus (Front Desk)',
    ];

    public function getTypeLabelAttribute(): string
    {
        return self::$typeLabels[$this->compensation_type] ?? ucfirst($this->compensation_type);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
