<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PatientAlert extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'patient_id',
        'alert',
        'severity',
        'is_active',
        'created_by',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}
