<?php

namespace App\Models\Prescription;

use Illuminate\Database\Eloquent\Model;

class RxFoodInstruction extends Model
{
    protected $table = 'rx_food_instructions';
    protected $fillable = ['code', 'label', 'label_mr', 'label_hi', 'is_active'];
}
