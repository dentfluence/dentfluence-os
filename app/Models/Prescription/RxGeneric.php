<?php

namespace App\Models\Prescription;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RxGeneric extends Model
{
    use SoftDeletes;

    protected $table = 'rx_generics';
    protected $fillable = ['name', 'drug_class', 'notes', 'is_active'];

    public function drugs()
    {
        return $this->hasMany(RxDrug::class, 'generic_id');
    }
}
