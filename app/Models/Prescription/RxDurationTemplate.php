<?php

namespace App\Models\Prescription;

use Illuminate\Database\Eloquent\Model;

class RxDurationTemplate extends Model
{
    protected $table = 'rx_duration_templates';
    protected $fillable = ['label', 'value', 'unit', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
}
