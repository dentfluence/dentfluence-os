<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Module extends Model
{
    protected $fillable = [
        'name', 'slug', 'icon', 'section', 'sort_order',
    ];

    public function permissions(): HasMany
    {
        return $this->hasMany(RoleModulePermission::class);
    }
}
