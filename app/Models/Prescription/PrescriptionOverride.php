<?php

namespace App\Models\Prescription;

use Illuminate\Database\Eloquent\Model;

class PrescriptionOverride extends Model
{
    protected $table = 'prescription_overrides';
    protected $fillable = [
        'prescription_id', 'user_id', 'drug_id', 'alert_type',
        'alert_code', 'alert_message', 'override_reason',
    ];

    public function prescription() { return $this->belongsTo(Prescription::class); }
    public function drug()         { return $this->belongsTo(RxDrug::class, 'drug_id'); }
    public function user()         { return $this->belongsTo(\App\Models\User::class); }
}
