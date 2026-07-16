<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'code', 'name', 'kind', 'monthly_price', 'annual_price',
        'description', 'unlocks', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'unlocks'   => 'array',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
