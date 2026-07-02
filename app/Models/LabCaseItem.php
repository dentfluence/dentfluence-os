<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LabCaseItem — one line item (tooth/unit) of a lab case.
 *
 * Example rows for a bridge under one case:
 *   11 | Crown  | Zirconia | A2
 *   12 | Crown  | Zirconia | A2
 *   13 | Pontic | Zirconia | A2
 */
class LabCaseItem extends Model
{
    protected $fillable = [
        'lab_case_id', 'tooth_number', 'work_type',
        'material', 'shade', 'notes', 'sort_order',
    ];

    public function labCase(): BelongsTo
    {
        return $this->belongsTo(LabCase::class);
    }

    /** "11 · Crown · Zirconia · A2" — compact display string */
    public function summary(): string
    {
        return collect([$this->tooth_number, $this->work_type, $this->material, $this->shade])
            ->filter()
            ->implode(' · ');
    }
}
