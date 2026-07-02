<?php

namespace App\Models\Prescription;

use Illuminate\Database\Eloquent\Model;

class RxWarningRule extends Model
{
    protected $table = 'rx_warning_rules';
    protected $fillable = [
        'condition_keyword', 'drug_id', 'molecule_group', 'drug_class',
        'severity', 'alert_message', 'suggestion', 'blockable', 'is_active',
    ];
    protected $casts = ['blockable' => 'boolean', 'is_active' => 'boolean'];

    public function drug() { return $this->belongsTo(RxDrug::class); }
}
