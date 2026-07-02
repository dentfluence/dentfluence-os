<?php

namespace App\Models\Prescription;

use Illuminate\Database\Eloquent\Model;

class RxAllergyRule extends Model
{
    protected $table = 'rx_allergy_rules';
    protected $fillable = [
        'allergy_keyword', 'blocks_molecule', 'blocks_class',
        'severity', 'alert_message', 'is_active',
    ];
}
