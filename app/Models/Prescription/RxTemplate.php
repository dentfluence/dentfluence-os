<?php

namespace App\Models\Prescription;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RxTemplate extends Model
{
    use SoftDeletes;

    protected $table = 'rx_templates';
    protected $fillable = ['name', 'category', 'description', 'instructions', 'is_active', 'created_by'];

    public function items()
    {
        return $this->hasMany(RxTemplateItem::class, 'template_id')->orderBy('sort_order');
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
