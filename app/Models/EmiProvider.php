<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmiProvider extends Model
{
    protected $fillable = ['name', 'contact', 'is_active', 'created_by'];

    protected $casts = ['is_active' => 'boolean'];

    public function schemes(): HasMany
    {
        return $this->hasMany(EmiScheme::class);
    }

    public function activeSchemes(): HasMany
    {
        return $this->hasMany(EmiScheme::class)->where('is_active', true);
    }

    public static function allActive()
    {
        return self::where('is_active', true)->with('activeSchemes')->orderBy('name')->get();
    }
}
