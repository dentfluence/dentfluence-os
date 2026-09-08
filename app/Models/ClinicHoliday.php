<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicHoliday extends Model
{
    protected $fillable = [
        'branch_id',
        'holiday_date',
        'name',
        'recurs_annually',
        'created_by',
    ];

    protected $casts = [
        'holiday_date'    => 'date',
        'recurs_annually' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** This branch's holidays plus the ones that apply to every branch. */
    public function scopeForBranch(Builder $query, ?int $branchId): Builder
    {
        return $query->where(function ($q) use ($branchId) {
            $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
        });
    }

    /**
     * Matches a specific calendar date, honouring annual recurrence.
     *
     * A recurring holiday matches on day-and-month in ANY year; a one-off
     * matches only its own date. Written as two branches rather than one
     * clever expression because a wrong match here closes the clinic.
     */
    public function scopeOnDate(Builder $query, \DateTimeInterface $date): Builder
    {
        $ymd = $date->format('Y-m-d');
        $md  = $date->format('m-d');

        return $query->where(function ($q) use ($ymd, $md) {
            $q->where(function ($one) use ($ymd) {
                $one->where('recurs_annually', false)->whereDate('holiday_date', $ymd);
            })->orWhere(function ($rec) use ($md) {
                $rec->where('recurs_annually', true)
                    ->whereRaw("DATE_FORMAT(holiday_date, '%m-%d') = ?", [$md]);
            });
        });
    }
}
