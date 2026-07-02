<?php

namespace App\Models\Prescription;

use Illuminate\Database\Eloquent\Model;

class RxDrugInteractionRule extends Model
{
    protected $table = 'rx_drug_interaction_rules';
    protected $fillable = [
        'drug_a_molecule', 'drug_a_class', 'drug_b_molecule', 'drug_b_class',
        'severity', 'alert_message', 'is_active',
    ];
}
