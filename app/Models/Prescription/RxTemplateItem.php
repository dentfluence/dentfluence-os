<?php

namespace App\Models\Prescription;

use Illuminate\Database\Eloquent\Model;

class RxTemplateItem extends Model
{
    protected $table = 'rx_template_items';
    protected $fillable = [
        'template_id', 'drug_id', 'strength', 'morning', 'afternoon', 'night',
        'is_sos', 'duration', 'duration_unit', 'food_instruction_id',
        'route', 'instructions', 'sort_order',
    ];

    public function drug() { return $this->belongsTo(RxDrug::class); }
    public function template() { return $this->belongsTo(RxTemplate::class); }
    public function foodInstruction() { return $this->belongsTo(RxFoodInstruction::class, 'food_instruction_id'); }
}
