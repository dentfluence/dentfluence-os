<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class FinanceExpenseCategory extends Model
{
    protected $table = 'finance_expense_categories';

    protected $fillable = [
        'clinic_id', 'parent_id', 'name', 'slug', 'icon', 'color',
        'is_system', 'is_active', 'sort_order',
    ];

    protected $casts = ['is_system' => 'boolean', 'is_active' => 'boolean'];

    public function parent()      { return $this->belongsTo(self::class, 'parent_id'); }
    public function children()    { return $this->hasMany(self::class, 'parent_id'); }
    public function expenses()    { return $this->hasMany(FinanceExpense::class, 'category_id'); }
}
