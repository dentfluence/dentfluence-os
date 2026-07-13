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
        'material', 'shade', 'shade_guide', 'notes', 'sort_order',
    ];

    public function labCase(): BelongsTo
    {
        return $this->belongsTo(LabCase::class);
    }

    /** Display label for the shade guide this item's shade belongs to, e.g. "Vita Classical" */
    public function shadeGuideLabel(): ?string
    {
        return $this->shade
            ? (LabCase::SHADE_GUIDE_LABELS[$this->shade_guide] ?? LabCase::SHADE_GUIDE_LABELS['vita_classical'])
            : null;
    }

    /** "11 · Crown · Zirconia · A2" — compact display string */
    public function summary(): string
    {
        return collect([$this->tooth_number, $this->work_type, $this->material, $this->shade])
            ->filter()
            ->implode(' · ');
    }
}
