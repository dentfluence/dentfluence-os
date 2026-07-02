<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrSalaryComponent extends Model
{
    protected $table = 'hr_salary_components';

    protected $fillable = [
        'user_id', 'basic_salary', 'hra', 'conveyance', 'medical', 'special',
        'pf_applicable', 'esi_applicable', 'professional_tax', 'ot_multiplier',
    ];

    protected $casts = [
        'basic_salary' => 'decimal:2', 'hra' => 'decimal:2',
        'conveyance' => 'decimal:2', 'medical' => 'decimal:2', 'special' => 'decimal:2',
        'professional_tax' => 'decimal:2', 'ot_multiplier' => 'decimal:2',
        'pf_applicable' => 'boolean', 'esi_applicable' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /* ── Computed ── */

    /** Total gross = basic + all allowances */
    public function getGrossSalaryAttribute(): float
    {
        return (float) ($this->basic_salary + $this->hra + $this->conveyance + $this->medical + $this->special);
    }

    /** PF deduction = 12% of basic if applicable */
    public function getPfDeductionAttribute(): float
    {
        return $this->pf_applicable ? round((float) $this->basic_salary * 0.12, 2) : 0;
    }

    /** ESI deduction = 0.75% of gross if applicable (employee share) */
    public function getEsiDeductionAttribute(): float
    {
        return $this->esi_applicable ? round($this->gross_salary * 0.0075, 2) : 0;
    }

    /** Total deductions */
    public function getTotalDeductionsAttribute(): float
    {
        return $this->pf_deduction + $this->esi_deduction + (float) $this->professional_tax;
    }

    /** Hourly OT rate = (basic ÷ 26 ÷ 8) × multiplier */
    public function getOtHourlyRateAttribute(): float
    {
        if (! $this->basic_salary) return 0;
        return round((float) $this->basic_salary / 26 / 8 * (float) $this->ot_multiplier, 2);
    }
}
