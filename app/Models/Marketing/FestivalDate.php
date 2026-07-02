<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class FestivalDate extends Model
{
    protected $table = 'mkt_festival_dates';

    // No clinic_id, no soft deletes on this global table
    protected $fillable = [
        'name',
        'local_name',
        'category',
        'month',
        'day',
        'festival_date',
        'is_recurring',
        'nth_week',
        'day_of_week',
        'description',
        'suggested_content_type',
        'suggested_hashtags',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'suggested_hashtags' => 'array',
        'is_recurring'       => 'boolean',
        'is_active'          => 'boolean',
        'festival_date'      => 'date',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

    // -----------------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------------

    /**
     * Festivals that fall in a given month (fixed-date recurring ones).
     */
    public function scopeForMonth(Builder $query, int $month, int $year): Builder
    {
        return $query->where('is_active', true)
                     ->where(function ($q) use ($month, $year) {
                         // Fixed recurring: match by month
                         $q->where(function ($inner) use ($month) {
                             $inner->where('is_recurring', true)
                                   ->where('month', $month);
                         })
                         // Non-recurring: match exact year + month
                         ->orWhere(function ($inner) use ($month, $year) {
                             $inner->where('is_recurring', false)
                                   ->whereYear('festival_date', $year)
                                   ->whereMonth('festival_date', $month);
                         });
                     });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }
}
