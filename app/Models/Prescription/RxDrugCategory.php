<?php

namespace App\Models\Prescription;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RxDrugCategory extends Model
{
    use SoftDeletes;

    protected $table = 'rx_drug_categories';
    protected $fillable = ['name', 'description', 'is_active'];

    public function drugs()
    {
        return $this->hasMany(RxDrug::class, 'category_id');
    }
}
