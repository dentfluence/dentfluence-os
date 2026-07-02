<?php

namespace App\Models\Prescription;

use Illuminate\Database\Eloquent\Model;

class RxDoseTemplate extends Model
{
    protected $table = 'rx_dose_templates';
    protected $fillable = ['name', 'abbreviation', 'morning', 'afternoon', 'night', 'is_sos', 'description', 'is_active'];
    protected $casts = ['is_sos' => 'boolean', 'is_active' => 'boolean'];
}
